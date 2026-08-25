<?php
namespace Victi\MyGameLibrary\Services;

/**
 * Centraliza o início da sessão PHP.
 *
 * Antes, cada controller chamava session_start() diretamente, o que
 * fazia o PHP emitir o cookie PHPSESSID com os atributos padrão (sem
 * Secure, sem HttpOnly, sem SameSite). Isso deixava o cookie de sessão:
 *   - legível por JavaScript (sem HttpOnly) -> roubável via um XSS;
 *   - enviável em conexão HTTP não criptografada (sem Secure);
 *   - enviável em requisições vindas de outros sites (sem SameSite) ->
 *     facilita CSRF.
 *
 * Ao trocar todo "session_start()" solto por "Session::start()",
 * garantimos que o cookie sempre saia com esses atributos, em um único
 * lugar para manter.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps, // só exige HTTPS quando a conexão já é HTTPS (não quebra o ambiente local em http)
            'httponly' => true,   // impede acesso ao cookie via JavaScript (document.cookie)
            'samesite' => 'Lax',  // bloqueia envio do cookie em requisições cross-site (CSRF), mantendo navegação normal
        ]);

        session_start();
    }
}
