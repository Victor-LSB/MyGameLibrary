<?php
/**
 * Refaz as descrições JÁ SALVAS no banco em português do Brasil.
 *
 * Contexto: até a correção do GameController, as descrições eram traduzidas com o destino
 * "PT" do DeepL, que é português de Portugal. Este script manda cada descrição de novo ao
 * DeepL com destino PT-BR (o idioma de origem é detectado sozinho, então textos que ficaram
 * em inglês porque o DeepL falhou na época também são traduzidos).
 *
 * Uso (no servidor, a partir da raiz do projeto):
 *   php scripts/retranslate_descriptions.php                  -> SIMULAÇÃO: só lista o que seria enviado
 *   php scripts/retranslate_descriptions.php --apply          -> traduz e grava
 *   php scripts/retranslate_descriptions.php --apply --limit=50
 *       -> processa só 50 jogos por execução (útil por causa da cota do DeepL Free)
 *   php scripts/retranslate_descriptions.php --apply --reset  -> ignora o controle de já processados
 *
 * Como funciona:
 *   - Os ids já processados ficam em storage/cache/retranslate_done.json, então dá para
 *     rodar de novo (ou continuar depois de bater a cota) sem pagar duas vezes pelo mesmo jogo.
 *   - Se o DeepL falhar para um jogo (cota, timeout...), a descrição antiga é mantida e o
 *     jogo NÃO entra na lista de processados, para tentar de novo na próxima execução.
 *   - Se a cota acabar (HTTP 456), o script para na hora.
 *
 * Cota: o DeepL Free dá 500.000 caracteres/mês. A simulação mostra quantos caracteres
 * seriam enviados. Faça backup antes do --apply:  mysqldump -u USER -p mygamelibrary > backup.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Victi\MyGameLibrary\Database\Database;

$apply = in_array('--apply', $argv, true);
$reset = in_array('--reset', $argv, true);
$limit = null;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
    }
}

$authKey = trim($_ENV['DEEPL_API_KEY'] ?? '');
if ($apply && $authKey === '') {
    fwrite(STDERR, "DEEPL_API_KEY não configurada no .env.\n");
    exit(1);
}

$stateDir = __DIR__ . '/../storage/cache';
$statePath = $stateDir . '/retranslate_done.json';
if (!is_dir($stateDir)) {
    @mkdir($stateDir, 0775, true);
}
$done = [];
if (!$reset && is_file($statePath)) {
    $decoded = json_decode((string) file_get_contents($statePath), true);
    $done = is_array($decoded) ? array_flip(array_map('intval', $decoded)) : [];
}
$saveState = function () use (&$done, $statePath) {
    file_put_contents($statePath, json_encode(array_keys($done)), LOCK_EX);
};

/**
 * Chama o DeepL (origem detectada automaticamente, destino PT-BR).
 * Devolve ['text' => string] em caso de sucesso, ou ['error' => msg, 'quota' => bool].
 */
function deeplToPtBr(string $html, string $authKey): array
{
    $url = str_ends_with($authKey, ':fx')
        ? 'https://api-free.deepl.com/v2/translate'
        : 'https://api.deepl.com/v2/translate';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'text' => $html,
            'target_lang' => 'PT-BR',
            'tag_handling' => 'html',
        ]),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Authorization: DeepL-Auth-Key ' . $authKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($curlError !== null) {
        return ['error' => 'cURL: ' . $curlError, 'quota' => false];
    }
    if ($code === 456) {
        return ['error' => 'cota do DeepL esgotada (HTTP 456)', 'quota' => true];
    }
    $decoded = json_decode((string) $response, true);
    if ($code >= 400 || empty($decoded['translations'][0]['text'])) {
        return ['error' => 'HTTP ' . $code, 'quota' => false];
    }
    return ['text' => $decoded['translations'][0]['text']];
}

$db = (new Database())->connect();
$games = $db->query(
    "SELECT id, title, description FROM games
     WHERE description IS NOT NULL AND TRIM(description) <> ''
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$pending = array_values(array_filter($games, fn($g) => !isset($done[(int) $g['id']])));
$totalPending = count($pending);
if ($limit !== null) {
    $pending = array_slice($pending, 0, $limit);
}

$chars = array_sum(array_map(fn($g) => mb_strlen($g['description']), $pending));

echo ($apply ? '[APLICANDO]' : '[SIMULAÇÃO]')
    . " Jogos com descrição: " . count($games)
    . " | já processados: " . (count($games) - $totalPending)
    . " | nesta execução: " . count($pending)
    . " | caracteres a enviar: " . number_format($chars, 0, ',', '.') . "\n\n";

if (!$apply) {
    foreach ($pending as $g) {
        printf("  #%d %s (%d caracteres)\n", $g['id'], $g['title'], mb_strlen($g['description']));
    }
    echo "\nNada foi gravado. Rode com --apply para traduzir.\n";
    exit(0);
}

$update = $db->prepare('UPDATE games SET description = ? WHERE id = ?');
$ok = 0;
$failed = 0;

foreach ($pending as $i => $g) {
    $result = deeplToPtBr($g['description'], $authKey);

    if (isset($result['text'])) {
        $update->execute([$result['text'], $g['id']]);
        $done[(int) $g['id']] = true;
        $ok++;
        printf("[%d/%d] OK   #%d %s\n", $i + 1, count($pending), $g['id'], $g['title']);
    } else {
        $failed++;
        printf("[%d/%d] FALHA #%d %s -> %s\n", $i + 1, count($pending), $g['id'], $g['title'], $result['error']);
        if ($result['quota']) {
            echo "\nParando: a cota acabou. Rode de novo quando renovar; os já feitos serão pulados.\n";
            break;
        }
    }

    $saveState();
    usleep(300000); // 0,3s entre chamadas, para não irritar o rate limit do DeepL
}

$saveState();
echo "\nConcluído: $ok traduzidas, $failed com falha.\n";
