<?php

namespace Victi\MyGameLibrary\Services;

/**
 * Integração com o Cloudflare Turnstile (CAPTCHA).
 *
 * Só colocar o widget no HTML NÃO protege nada: o bot manda o POST direto,
 * sem o widget. A proteção real é o verify() abaixo, chamado no servidor.
 *
 * Variáveis do .env:
 *   TURNSTILE_SITE_KEY    chave pública (vai no HTML)
 *   TURNSTILE_SECRET_KEY  chave secreta (só no servidor)
 *   APP_ENV               "local" libera o cadastro sem chaves (só para dev)
 *
 * Em produção, sem a secret key o verify() FALHA (fail closed), para que uma
 * configuração esquecida não deixe o formulário desprotegido sem ninguém notar.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private static function env(string $key): string
    {
        return (string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: '');
    }

    public static function isLocalEnv(): bool
    {
        return self::env('APP_ENV') === 'local';
    }

    /** Script do Turnstile (colocar uma vez, antes de </body>). */
    public static function script(): string
    {
        if (self::env('TURNSTILE_SITE_KEY') === '') {
            return '';
        }
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    /** Widget (colocar dentro do <form>). */
    public static function widget(): string
    {
        $siteKey = self::env('TURNSTILE_SITE_KEY');
        if ($siteKey === '') {
            return self::isLocalEnv() ? '' : '<p class="text-red-400 text-xs font-bold">Verificação de segurança não configurada.</p>';
        }
        return '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES) . '" data-theme="dark"></div>';
    }

    /** Valida o token enviado pelo formulário. true = humano. */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        $secret = self::env('TURNSTILE_SECRET_KEY');

        if ($secret === '') {
            if (self::isLocalEnv()) {
                return true;
            }
            error_log('Turnstile: TURNSTILE_SECRET_KEY não configurada; bloqueando por segurança.');
            return false;
        }

        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }

        $fields = ['secret' => $secret, 'response' => $token];
        if ($ip !== null && $ip !== '' && $ip !== 'unknown') {
            $fields['remoteip'] = $ip;
        }

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('Turnstile: falha ao contatar o siteverify: ' . $err);
            return false;
        }

        $data = json_decode($raw, true);
        return is_array($data) && !empty($data['success']);
    }
}
