<?php
/**
 * Limpa contas criadas por bots (nunca confirmaram o e-mail) e registros
 * antigos do rate limiter.
 *
 * Uso (no servidor, a partir da raiz do projeto):
 *   php scripts/cleanup_unverified.php            -> SIMULAÇÃO: só mostra o que apagaria
 *   php scripts/cleanup_unverified.php --apply    -> apaga de verdade
 *   php scripts/cleanup_unverified.php --days=3   -> muda o prazo (padrão: 7 dias)
 *
 * Só apaga contas SEM e-mail confirmado cujo último token de verificação
 * expirou há mais de N dias. Quem não confirmou o e-mail nunca conseguiu
 * entrar, então essas contas não têm dados de uso.
 *
 * Sugestão de cron (todo dia às 4h):
 *   0 4 * * * cd /var/www/html && php scripts/cleanup_unverified.php --apply >> /var/log/mgl_cleanup.log 2>&1
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
$days = 7;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, (int) $m[1]);
    }
}

$db = (new Database())->connect();

$where = "email_verified_at IS NULL
          AND (verification_token_expires_at IS NULL
               OR verification_token_expires_at < DATE_SUB(NOW(), INTERVAL {$days} DAY))";

$count = (int) $db->query("SELECT COUNT(*) FROM users WHERE {$where}")->fetchColumn();
echo ($apply ? '[APLICANDO]' : '[SIMULAÇÃO]') . " Contas não confirmadas há mais de {$days} dia(s): {$count}\n";

if ($count > 0) {
    $sample = $db->query("SELECT id, username, email FROM users WHERE {$where} ORDER BY id DESC LIMIT 10")
                 ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sample as $u) {
        echo "  #{$u['id']}  {$u['username']}  <{$u['email']}>\n";
    }
    if ($count > count($sample)) {
        echo '  ... e mais ' . ($count - count($sample)) . "\n";
    }
}

if ($apply) {
    $deleted = $count > 0 ? $db->exec("DELETE FROM users WHERE {$where}") : 0;
    $rl = $db->exec("DELETE FROM rate_limit_hits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    echo "Contas apagadas: {$deleted} | registros de rate limit apagados: {$rl}\n";
} else {
    echo "Nada foi apagado. Rode com --apply para confirmar.\n";
}
