<?php
/**
 * Une jogos duplicados no banco (o mesmo jogo salvo duas vezes, ex.: um pela IGDB e outro
 * pelo fallback da Steam, ou um com id antigo da RAWG).
 *
 * Uso (no servidor, a partir da raiz do projeto):
 *   php scripts/merge_duplicate_games.php           -> SIMULAÇÃO: só lista os grupos encontrados
 *   php scripts/merge_duplicate_games.php --apply   -> une os duplicados
 *
 * Como decide que é o mesmo jogo:
 *   - Título igual depois de normalizar (maiúsculas, pontuação, ™/®, "III" = "3").
 *   - Mesmo ano de lançamento (ou sem ano em um dos dois).
 *
 * Qual registro fica:
 *   - O que tem id da IGDB (é a fonte principal hoje). Empate: o mais antigo (menor id).
 *   - Capa e descrição vazias no que fica são completadas com as do duplicado.
 *
 * O que é movido para o registro que fica:
 *   - Bibliotecas (user_games). Se a mesma pessoa tinha as duas versões, as informações
 *     são combinadas (nota, análise, data, horas, platina) e o status mais avançado vale.
 *   - Tags, Top 5, comentários de análise, atividades do feed (com curtidas) e notificações.
 *
 * Tudo roda numa transação por grupo: se algo falhar, aquele grupo fica como estava.
 * Faça backup antes do --apply:  mysqldump -u USER -p mygamelibrary > backup.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Game;

$apply = in_array('--apply', $argv, true);

$igdbMin = 500000000;
$igdbMax = 900000000;
$isIgdb = fn($externalId) => (int) $externalId >= $igdbMin && (int) $externalId < $igdbMax;

$db = (new Database())->connect();

$games = $db->query("SELECT id, external_id, title, release_date, cover_image, description,
        (SELECT COUNT(*) FROM user_games ug WHERE ug.game_id = games.id) AS owners
    FROM games ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por título normalizado; dentro do grupo, separa por ano
$byKey = [];
foreach ($games as $g) {
    $byKey[Game::titleMatchKey($g['title'])][] = $g;
}

$groups = [];
foreach ($byKey as $key => $list) {
    if ($key === '' || count($list) < 2) {
        continue;
    }
    // Junta por ano; quem não tem ano entra no primeiro grupo com ano (ou fica sozinho)
    $byYear = [];
    $noYear = [];
    foreach ($list as $g) {
        $year = $g['release_date'] ? substr((string) $g['release_date'], 0, 4) : null;
        if ($year === null) {
            $noYear[] = $g;
        } else {
            $byYear[$year][] = $g;
        }
    }
    if ($noYear) {
        if ($byYear) {
            $firstYear = array_key_first($byYear);
            $byYear[$firstYear] = array_merge($byYear[$firstYear], $noYear);
        } else {
            $byYear['?'] = $noYear;
        }
    }
    foreach ($byYear as $sameGame) {
        if (count($sameGame) < 2) {
            continue;
        }
        usort($sameGame, function ($a, $b) use ($isIgdb) {
            $ai = $isIgdb($a['external_id']) ? 0 : 1;
            $bi = $isIgdb($b['external_id']) ? 0 : 1;
            return $ai <=> $bi ?: (int) $a['id'] <=> (int) $b['id'];
        });
        $groups[] = $sameGame;
    }
}

echo ($apply ? '[APLICANDO]' : '[SIMULAÇÃO]') . ' Jogos no banco: ' . count($games)
    . ' | grupos de duplicados: ' . count($groups) . "\n\n";

foreach ($groups as $group) {
    $keep = $group[0];
    echo "Fica  #{$keep['id']} {$keep['title']} (external_id {$keep['external_id']}, {$keep['owners']} bibliotecas)\n";
    foreach (array_slice($group, 1) as $dup) {
        echo "  une #{$dup['id']} {$dup['title']} (external_id {$dup['external_id']}, {$dup['owners']} bibliotecas)\n";
    }
}

if (!$apply) {
    echo "\nNada foi gravado. Rode com --apply para unir.\n";
    exit(0);
}

// Quanto maior, mais "avançado" o status (vale o maior quando a pessoa tinha as duas versões)
$statusRank = ['Quero jogar' => 0, 'Backlog' => 1, 'Dropado' => 2, 'Jogando' => 3, 'Zerado' => 4];

$merged = 0;
foreach ($groups as $group) {
    $keepId = (int) $group[0]['id'];

    foreach (array_slice($group, 1) as $dup) {
        $dupId = (int) $dup['id'];
        try {
            $db->beginTransaction();

            // 1) Bibliotecas
            $rows = $db->prepare("SELECT * FROM user_games WHERE game_id = ?");
            $rows->execute([$dupId]);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $dupRow) {
                $existing = $db->prepare("SELECT * FROM user_games WHERE user_id = ? AND game_id = ?");
                $existing->execute([$dupRow['user_id'], $keepId]);
                $keepRow = $existing->fetch(PDO::FETCH_ASSOC);

                if (!$keepRow) {
                    $db->prepare("UPDATE user_games SET game_id = ? WHERE id = ?")->execute([$keepId, $dupRow['id']]);
                    continue;
                }

                // A pessoa tinha as duas versões: combina e apaga a do duplicado
                $status = ($statusRank[$dupRow['status']] ?? -1) > ($statusRank[$keepRow['status']] ?? -1)
                    ? $dupRow['status'] : $keepRow['status'];
                $pick = fn($field) => ($keepRow[$field] !== null && $keepRow[$field] !== '') ? $keepRow[$field] : $dupRow[$field];
                $platinum = $status === 'Zerado' ? $pick('platinum_at') : null;

                $db->prepare("UPDATE user_games SET status = ?, rating = ?, review = ?, completion_date = ?,
                        time_spent_hours = ?, platinum_at = ? WHERE id = ?")
                   ->execute([$status, $pick('rating'), $pick('review'), $pick('completion_date'),
                        $pick('time_spent_hours'), $platinum, $keepRow['id']]);
                $db->prepare("DELETE FROM user_games WHERE id = ?")->execute([$dupRow['id']]);
            }

            // 2) Tags (a chave inclui game_id: move o que não colide, apaga o resto)
            $db->prepare("UPDATE IGNORE game_tags SET game_id = ? WHERE game_id = ?")->execute([$keepId, $dupId]);
            $db->prepare("DELETE FROM game_tags WHERE game_id = ?")->execute([$dupId]);

            // 3) Top 5 (um jogo por pessoa: se já tinha o que fica, descarta o duplicado)
            $db->prepare("DELETE d FROM user_top_games d
                    JOIN user_top_games k ON k.user_id = d.user_id AND k.game_id = ?
                    WHERE d.game_id = ?")->execute([$keepId, $dupId]);
            $db->prepare("UPDATE user_top_games SET game_id = ? WHERE game_id = ?")->execute([$keepId, $dupId]);

            // 4) Comentários de análise, feed e notificações de análise
            $db->prepare("UPDATE review_comments SET game_id = ? WHERE game_id = ?")->execute([$keepId, $dupId]);
            $db->prepare("UPDATE user_activity SET game_id = ? WHERE game_id = ?")->execute([$keepId, $dupId]);
            $db->prepare("UPDATE notifications SET related_id = ? WHERE type = 'review_posted' AND related_id = ?")
               ->execute([$keepId, $dupId]);

            // Feed: "adicionou" e "publicou análise" repetidos para o mesmo jogo -> fica o mais antigo
            $db->prepare("DELETE ua FROM user_activity ua
                    JOIN user_activity keep ON keep.user_id = ua.user_id AND keep.game_id = ua.game_id
                     AND keep.type = ua.type AND keep.id < ua.id
                    WHERE ua.game_id = ? AND ua.type IN ('game_added', 'review_posted')")->execute([$keepId]);

            // 5) Completa capa/descrição do que fica, se estiverem vazias
            $db->prepare("UPDATE games SET
                    cover_image = IF(cover_image IS NULL OR cover_image = '', ?, cover_image),
                    description = IF(description IS NULL OR description = '', ?, description)
                WHERE id = ?")->execute([$dup['cover_image'], $dup['description'], $keepId]);

            $db->prepare("DELETE FROM games WHERE id = ?")->execute([$dupId]);

            $db->commit();
            $merged++;
            echo "OK    #$dupId -> #$keepId\n";
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "FALHA #$dupId -> #$keepId: " . $e->getMessage() . "\n";
        }
    }
}

// O cache da landing pode ter capas de jogos que não existem mais
@unlink(sys_get_temp_dir() . '/mgl_popular_games.json');

echo "\nConcluído: $merged registros duplicados unidos.\n";
