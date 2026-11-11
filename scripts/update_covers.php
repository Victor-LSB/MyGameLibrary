<?php
/**
 * Atualiza as capas dos jogos JÁ SALVOS no banco para a capa oficial da IGDB
 * (as antigas são screenshots da RAWG/Steam ou da primeira versão da IGDB).
 *
 * Uso (no servidor, a partir da raiz do projeto):
 *   php scripts/update_covers.php                 -> SIMULAÇÃO: só mostra o que mudaria
 *   php scripts/update_covers.php --apply         -> grava as novas capas
 *   php scripts/update_covers.php --apply --migrate-ids
 *       -> além da capa, troca o external_id antigo (RAWG/Steam) pelo da IGDB nos jogos
 *          encontrados. Isso evita jogo duplicado quando alguém adicionar de novo pela
 *          busca. Só troca se nenhum outro jogo já usa aquele id.
 *
 * Como funciona:
 *   - Jogos com id da IGDB (adicionados depois da migração): busca direta pelo id.
 *   - Jogos com id antigo da RAWG ou da Steam: procura na IGDB pelo TÍTULO e só aceita
 *     se o nome for igual (o ano de lançamento desempata). Na dúvida, não mexe.
 *   - Quem já tem capa oficial (URL com "t_cover_") é pulado, então pode rodar de novo
 *     sem problema.
 *
 * Faça backup do banco antes do --apply:  mysqldump -u USER -p mygamelibrary > backup.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Services\GameAPI;

$apply = in_array('--apply', $argv, true);
$migrateIds = in_array('--migrate-ids', $argv, true);

if ($migrateIds && !$apply) {
    echo "Aviso: --migrate-ids só tem efeito junto com --apply.\n";
}

$igdbMin = 500000000;
$igdbMax = 900000000; // exclusivo (a partir daqui é Steam)

$db = (new Database())->connect();
$api = new GameAPI();

$games = $db->query("SELECT id, external_id, title, release_date, cover_image FROM games ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);

echo ($apply ? '[APLICANDO]' : '[SIMULAÇÃO]') . ' Jogos no banco: ' . count($games) . "\n\n";

// external_ids já em uso, para o --migrate-ids nunca criar colisão
$usedExternalIds = [];
foreach ($games as $g) {
    $usedExternalIds[(string) $g['external_id']] = true;
}

$byIgdbId = [];  // jogos com id da IGDB  -> lookup direto
$byTitle  = [];  // jogos com id RAWG/Steam -> lookup por título
$skipped  = 0;

foreach ($games as $g) {
    if (strpos((string) $g['cover_image'], 't_cover_') !== false) {
        $skipped++;
        continue;
    }
    $ext = (int) $g['external_id'];
    if ($ext >= $igdbMin && $ext < $igdbMax) {
        $byIgdbId[$ext - $igdbMin][] = $g;
    } else {
        $byTitle[] = $g;
    }
}

echo "Já com capa oficial (pulados): {$skipped}\n";
echo 'Busca direta por id da IGDB: ' . array_sum(array_map('count', $byIgdbId)) . "\n";
echo 'Busca por título (ids antigos): ' . count($byTitle) . "\n\n";

$updates = [];    // [ ['id'=>, 'title'=>, 'cover'=>, 'new_external_id'=>?], ... ]
$notFound = [];
$errors = [];

// ---- 1) ids da IGDB: em lote ----
if (!empty($byIgdbId)) {
    try {
        $covers = $api->getCoverUrlsByIgdbIds(array_keys($byIgdbId));
    } catch (RuntimeException $e) {
        fwrite(STDERR, "ERRO: " . $e->getMessage() . " Nada foi alterado.\n");
        exit(1);
    }
    foreach ($byIgdbId as $igdbId => $rows) {
        foreach ($rows as $g) {
            if (isset($covers[$igdbId])) {
                $updates[] = ['id' => $g['id'], 'title' => $g['title'], 'cover' => $covers[$igdbId], 'new_external_id' => null];
            } else {
                $notFound[] = $g['title'] . ' (sem capa na IGDB)';
            }
        }
    }
}

// ---- 2) ids antigos: um por um, pelo título ----
$i = 0;
foreach ($byTitle as $g) {
    $i++;
    $match = null;
    $ok = false;
    for ($attempt = 1; $attempt <= 2 && !$ok; $attempt++) {
        try {
            $match = $api->findIgdbMatchByTitle($g['title'], $g['release_date']);
            $ok = true;
        } catch (RuntimeException $e) {
            sleep(2); // possível rate limit; espera e tenta mais uma vez
        }
    }
    usleep(300000); // 4 req/s é o limite da IGDB

    if (!$ok) {
        $errors[] = $g['title'];
        continue;
    }
    if ($match === null) {
        $notFound[] = $g['title'];
        continue;
    }

    $newExt = $api->toExternalId($match['igdb_id']);
    $update = ['id' => $g['id'], 'title' => $g['title'], 'cover' => $match['cover'], 'new_external_id' => null];

    if ($migrateIds && empty($usedExternalIds[(string) $newExt])) {
        $update['new_external_id'] = $newExt;
        $usedExternalIds[(string) $newExt] = true; // reserva, pra dois jogos não pegarem o mesmo id
    }
    $updates[] = $update;

    if ($i % 25 === 0) {
        echo "  ... {$i}/" . count($byTitle) . " pesquisados\n";
    }
}

// ---- Relatório ----
echo "\nCapas a atualizar: " . count($updates) . "\n";
foreach ($updates as $u) {
    echo "  #{$u['id']}  {$u['title']}" . ($u['new_external_id'] ? "  [external_id -> {$u['new_external_id']}]" : '') . "\n";
}
if (!empty($notFound)) {
    echo "\nSem correspondência segura (ficam como estão): " . count($notFound) . "\n";
    foreach ($notFound as $t) echo "  - {$t}\n";
}
if (!empty($errors)) {
    echo "\nFalharam por erro na IGDB (rode de novo): " . count($errors) . "\n";
    foreach ($errors as $t) echo "  - {$t}\n";
}

// ---- Gravação ----
if ($apply) {
    $withId = $db->prepare("UPDATE games SET cover_image = ?, external_id = ? WHERE id = ?");
    $onlyCover = $db->prepare("UPDATE games SET cover_image = ? WHERE id = ?");
    $done = 0;
    foreach ($updates as $u) {
        if ($u['new_external_id']) {
            $withId->execute([$u['cover'], $u['new_external_id'], $u['id']]);
        } else {
            $onlyCover->execute([$u['cover'], $u['id']]);
        }
        $done++;
    }
    echo "\nGravado: {$done} jogo(s) atualizado(s).\n";
} else {
    echo "\nNada foi alterado. Rode com --apply para confirmar.\n";
}
