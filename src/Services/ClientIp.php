<?php

namespace Victi\MyGameLibrary\Services;

/**
 * Descobre o IP REAL do visitante.
 *
 * Por que isto existe: atrás do Cloudflare, o REMOTE_ADDR é sempre um IP do
 * próprio Cloudflare, e o header X-Forwarded-For é escolhido por quem faz a
 * requisição (um bot pode mandar um valor diferente a cada tentativa e nunca
 * cair no mesmo bucket do rate limit).
 *
 * Regra: só confiamos no header CF-Connecting-IP quando a conexão TCP veio de
 * fato de uma faixa de IPs do Cloudflare. Qualquer outro caso usa REMOTE_ADDR.
 *
 * Se você configurou o mod_remoteip no Apache (veja deploy/), o REMOTE_ADDR já
 * chega com o IP real e este código simplesmente o devolve.
 *
 * Confira a lista oficial de tempos em tempos: https://www.cloudflare.com/ips/
 */
class ClientIp
{
    private const CLOUDFLARE_RANGES = [
        // IPv4
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public static function get(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($remote === '') {
            return 'unknown';
        }

        $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP) && self::isCloudflare($remote)) {
            return $cfIp;
        }

        return $remote;
    }

    public static function isCloudflare(string $ip): bool
    {
        foreach (self::CLOUDFLARE_RANGES as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private static function inRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        // Famílias diferentes (IPv4 vs IPv6) nunca casam.
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }
}
