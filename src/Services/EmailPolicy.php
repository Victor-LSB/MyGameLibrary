<?php

namespace Victi\MyGameLibrary\Services;

/**
 * Bloqueia e-mails de provedores descartáveis (10minutemail e similares),
 * muito usados por bots de cadastro em massa.
 *
 * A lista cobre os mais comuns; para estender sem mexer no código, defina
 * BLOCKED_EMAIL_DOMAINS no .env (domínios separados por vírgula).
 */
class EmailPolicy
{
    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
        'guerrillamail.biz', 'guerrillamail.de', 'sharklasers.com', 'grr.la', 'spam4.me',
        '10minutemail.com', '10minutemail.net', '10minutemail.org', '20minutemail.com',
        'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmail.net', 'tempmailo.com',
        'tempail.com', 'tempr.email', 'throwawaymail.com', 'trashmail.com', 'trashmail.net',
        'trashmail.de', 'yopmail.com', 'yopmail.net', 'yopmail.fr', 'getnada.com',
        'nada.email', 'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'mohmal.com',
        'emailondeck.com', 'fakeinbox.com', 'dispostable.com', 'discard.email',
        'discardmail.com', 'mailcatch.com', 'mytemp.email', 'spamgourmet.com',
        'getairmail.com', 'moakt.com', 'burnermail.io', 'inboxkitten.com', 'mail.tm',
        'emailfake.com', 'fakemailgenerator.com', 'mailpoof.com', 'tmpmail.org',
        'tmpmail.net', 'tmail.ws', 'minuteinbox.com', 'dropmail.me', 'anonbox.net',
        'harakirimail.com', 'mailforspam.com', 'spambox.us', 'tempinbox.com',
        'crazymailing.com', 'luxusmail.org', 'emltmp.com',
    ];

    public static function isBlocked(string $email): bool
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return true;
        }

        $domain = mb_strtolower(substr($email, $at + 1));

        if (in_array($domain, self::DISPOSABLE, true)) {
            return true;
        }

        $extra = (string) ($_ENV['BLOCKED_EMAIL_DOMAINS'] ?? $_SERVER['BLOCKED_EMAIL_DOMAINS'] ?? '');
        if ($extra !== '') {
            $list = array_filter(array_map(fn($d) => mb_strtolower(trim($d)), explode(',', $extra)));
            if (in_array($domain, $list, true)) {
                return true;
            }
        }

        return false;
    }
}
