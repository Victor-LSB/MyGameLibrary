<?php
// Funções auxiliares globais, carregadas por public/index.php em toda requisição.

if (!function_exists('mgl_asset')) {
    /**
     * Endereço de um arquivo de public/ com ?v=<data de modificação>.
     * Quando o arquivo muda, o endereço muda, e o Cloudflare/navegador baixam a
     * versão nova em vez de servir a que estava guardada em cache.
     */
    function mgl_asset(string $path): string {
        $path = ltrim($path, './');
        $file = __DIR__ . '/../public/' . $path;
        return './' . $path . '?v=' . (int) @filemtime($file);
    }
}

if (!function_exists('mgl_avatar_url')) {
    /**
     * Endereço da foto de perfil: aceita URL completa ou nome de arquivo salvo em uploads/profile.
     * Retorna null quando o usuário não tem foto.
     */
    function mgl_avatar_url($avatar): ?string {
        if (empty($avatar)) return null;
        return str_starts_with($avatar, 'http') ? $avatar : './uploads/profile/' . basename($avatar);
    }
}

if (!function_exists('mgl_time_ago')) {
    /**
     * Tempo relativo curto ("agora", "há 5 min", "há 3 h", "há 2 d"). Passada uma semana, mostra a data.
     */
    function mgl_time_ago(string $datetime): string {
        $ts = strtotime($datetime);
        if ($ts === false) return '';
        $diff = max(0, time() - $ts);

        if ($diff < 60)     return 'agora';
        if ($diff < 3600)   return 'há ' . intdiv($diff, 60) . ' min';
        if ($diff < 86400)  return 'há ' . intdiv($diff, 3600) . ' h';
        if ($diff < 604800) return 'há ' . intdiv($diff, 86400) . ' d';
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('mgl_day_label')) {
    /**
     * Rótulo do dia para separar o feed: Hoje, Ontem, dia da semana (últimos 6 dias) ou data por extenso.
     */
    function mgl_day_label(string $datetime): string {
        $ts = strtotime($datetime);
        if ($ts === false) return '';

        $day = date('Y-m-d', $ts);
        if ($day === date('Y-m-d')) return 'Hoje';
        if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Ontem';

        $weekdays = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        if ($ts > strtotime('-6 days', strtotime('today'))) {
            return $weekdays[(int) date('w', $ts)];
        }

        $label = (int) date('j', $ts) . ' de ' . $months[(int) date('n', $ts) - 1];
        if (date('Y', $ts) !== date('Y')) {
            $label .= ' de ' . date('Y', $ts);
        }
        return $label;
    }
}
