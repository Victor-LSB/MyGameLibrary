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

if (!function_exists('mgl_genre_pt')) {
    /**
     * Traduz o nome de um gênero (como o IGDB devolve, guardado em minúsculas) para português.
     * Gênero desconhecido volta como veio, só com a primeira letra maiúscula.
     */
    function mgl_genre_pt(string $genre): string {
        $key = mb_strtolower(trim(html_entity_decode($genre, ENT_QUOTES, 'UTF-8')));
        $map = [
            'adventure'                       => 'Aventura',
            'arcade'                          => 'Arcade',
            'card & board game'               => 'Cartas e tabuleiro',
            'fighting'                        => 'Luta',
            "hack and slash/beat 'em up"      => 'Hack and slash',
            'indie'                           => 'Indie',
            'moba'                            => 'MOBA',
            'music'                           => 'Música',
            'pinball'                         => 'Pinball',
            'platform'                        => 'Plataforma',
            'point-and-click'                 => 'Point-and-click',
            'puzzle'                          => 'Quebra-cabeça',
            'quiz/trivia'                     => 'Quiz',
            'racing'                          => 'Corrida',
            'real time strategy (rts)'        => 'Estratégia em tempo real',
            'role-playing (rpg)'              => 'RPG',
            'shooter'                         => 'Tiro',
            'simulator'                       => 'Simulação',
            'sport'                           => 'Esporte',
            'strategy'                        => 'Estratégia',
            'tactical'                        => 'Tático',
            'turn-based strategy (tbs)'       => 'Estratégia por turnos',
            'visual novel'                    => 'Visual novel',
        ];

        if (isset($map[$key])) return $map[$key];
        return $key === '' ? '' : mb_strtoupper(mb_substr($key, 0, 1)) . mb_substr($key, 1);
    }
}

if (!function_exists('mgl_month_pt')) {
    function mgl_month_pt(int $month): string {
        $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return $months[$month - 1] ?? '';
    }
}

if (!function_exists('mgl_weekday_short_pt')) {
    function mgl_weekday_short_pt(int $weekday): string {
        $days = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        return $days[$weekday] ?? '';
    }
}

if (!function_exists('mgl_date_range_pt')) {
    /**
     * "5 a 11 de novembro", "28 de outubro a 3 de novembro" ou, para um dia só, "11 de novembro".
     * Acrescenta o ano quando o intervalo não está no ano atual ou atravessa anos.
     */
    function mgl_date_range_pt(string $start, string $end): string {
        $s = new DateTime($start);
        $e = new DateTime($end);
        $thisYear = (int) date('Y');
        $sameYear = $s->format('Y') === $e->format('Y');

        $fmt = function (DateTime $d, bool $withYear, bool $withMonth = true): string {
            $out = (int) $d->format('j');
            if ($withMonth) $out .= ' de ' . mgl_month_pt((int) $d->format('n'));
            if ($withYear) $out .= ' de ' . $d->format('Y');
            return $out;
        };

        if ($start === $end) {
            return $fmt($e, (int) $e->format('Y') !== $thisYear);
        }
        if ($sameYear && $s->format('n') === $e->format('n')) {
            return $fmt($s, false, false) . ' a ' . $fmt($e, (int) $e->format('Y') !== $thisYear);
        }
        $showYear = !$sameYear || (int) $e->format('Y') !== $thisYear;
        return $fmt($s, !$sameYear) . ' a ' . $fmt($e, $showYear);
    }
}
