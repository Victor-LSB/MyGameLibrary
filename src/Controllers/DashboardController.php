<?php
namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use PDO;
use Victi\MyGameLibrary\Services\Session;

class DashboardController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    private function startSession() {
        Session::start();
    }

    private function getPeriodConfig($period) {
        $periods = [
            'week' => [
                'label' => 'Semana Atual',
                'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
            ],
            'month' => [
                'label' => 'Mês Atual',
                'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
            ],
            'year' => [
                'label' => 'Ano Atual',
                'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
            ],
        ];

        return $periods[$period] ?? $periods['week'];
    }

    private function getTrendConfig($period) {
        $trendConfigs = [
            'week' => [
                'sql' => "DATE(ug.completion_date)",
                'label_format' => 'd/m',
                'date_step' => 'P1D',
            ],
            'month' => [
                'sql' => "DATE(ug.completion_date)",
                'label_format' => 'd/m',
                'date_step' => 'P1D',
            ],
            'year' => [
                'sql' => "DATE_FORMAT(ug.completion_date, '%Y-%m')",
                'label_format' => 'm/Y',
                'date_step' => 'P1M',
            ],
        ];

        return $trendConfigs[$period] ?? $trendConfigs['week'];
    }

    private function getAnchorDate($user_id) {
        $sql = "SELECT MAX(completion_date) AS anchor_date FROM user_games WHERE user_id = ? AND completion_date IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['anchor_date']) ? $row['anchor_date'] : date('Y-m-d');
    }

    private function getPeriodWindow($period, $anchorDate) {
        return match ($period) {
            'month' => [
                'start' => (new \DateTime($anchorDate))->sub(new \DateInterval('P29D'))->format('Y-m-d'),
                'end' => (new \DateTime($anchorDate))->format('Y-m-d'),
                'series_start' => (new \DateTime($anchorDate))->sub(new \DateInterval('P29D'))->format('Y-m-d'),
                'series_end' => (new \DateTime($anchorDate))->format('Y-m-d'),
            ],
            'year' => [
                'start' => (new \DateTime($anchorDate))->modify('first day of this month')->sub(new \DateInterval('P11M'))->format('Y-m-d'),
                'end' => (new \DateTime($anchorDate))->modify('last day of this month')->format('Y-m-d'),
                'series_start' => (new \DateTime($anchorDate))->modify('first day of this month')->sub(new \DateInterval('P11M'))->format('Y-m-d'),
                'series_end' => (new \DateTime($anchorDate))->modify('last day of this month')->format('Y-m-d'),
            ],
            default => [
                'start' => (new \DateTime($anchorDate))->sub(new \DateInterval('P6D'))->format('Y-m-d'),
                'end' => (new \DateTime($anchorDate))->format('Y-m-d'),
                'series_start' => (new \DateTime($anchorDate))->sub(new \DateInterval('P6D'))->format('Y-m-d'),
                'series_end' => (new \DateTime($anchorDate))->format('Y-m-d'),
            ],
        };
    }

    private function buildDateSeries($startDate, $endDate, $intervalSpec, $labelFormat) {
        $series = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->setTime(23, 59, 59);

        while ($current <= $end) {
            $key = $current->format(strpos($labelFormat, 'Y') !== false && strpos($labelFormat, 'm') !== false ? 'Y-m' : 'Y-m-d');
            $series[$key] = [
                'label' => $current->format($labelFormat),
                'completed' => 0,
            ];

            $current->add(new \DateInterval($intervalSpec));
        }

        return $series;
    }

    private function fetchAggregatedStatsForPeriod($user_id, $period) {
        $periodConfig = $this->getPeriodConfig($period);
        $trendConfig = $this->getTrendConfig($period);
        $anchorDate = $this->getAnchorDate($user_id);
        $window = $this->getPeriodWindow($period, $anchorDate);

        $summarySql = "SELECT COUNT(*) AS total_completed, COALESCE(AVG(time_spent_hours), 0) AS avg_time_spent
            FROM user_games
            WHERE user_id = ?
                AND completion_date IS NOT NULL
                AND completion_date BETWEEN ? AND ?";
        $summaryStmt = $this->db->prepare($summarySql);
        $summaryStmt->execute([$user_id, $window['start'], $window['end']]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_completed' => 0, 'avg_time_spent' => 0];

        $genreTopSql = "SELECT genre, total
                FROM (
                    SELECT 
                        LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(g.genre, ',', numbers.n), ',', -1))) AS genre,
                        COUNT(*) AS total
                    FROM user_games ug
                    INNER JOIN games g ON g.id = ug.game_id
                    INNER JOIN (
                        SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
                        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
                    ) AS numbers ON CHAR_LENGTH(g.genre) - CHAR_LENGTH(REPLACE(g.genre, ',', '')) >= numbers.n - 1
                    WHERE ug.user_id = ?
                        AND ug.completion_date IS NOT NULL
                        AND ug.completion_date BETWEEN ? AND ?
                        AND g.genre IS NOT NULL AND g.genre != ''
                    GROUP BY LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(g.genre, ',', numbers.n), ',', -1)))
                ) AS genre_counts
                WHERE genre != ''
                ORDER BY total DESC, genre ASC
                LIMIT 5";
        $genreStmt = $this->db->prepare($genreTopSql);
        $genreStmt->execute([$user_id, $window['start'], $window['end']]);
        $genres = $genreStmt->fetchAll(PDO::FETCH_ASSOC);

        $othersSql = "SELECT 'Outros' AS genre, COALESCE(SUM(rest.total), 0) AS total
            FROM (
                SELECT total
                FROM (
                    SELECT LOWER(TRIM(COALESCE(g.genre, 'Desconhecido'))) AS genre, COUNT(*) AS total
                    FROM user_games ug
                    INNER JOIN games g ON g.id = ug.game_id
                    WHERE ug.user_id = ?
                        AND ug.completion_date IS NOT NULL
                        AND ug.completion_date BETWEEN ? AND ?
                    GROUP BY LOWER(TRIM(COALESCE(g.genre, 'Desconhecido')))
                ) AS genre_counts
                ORDER BY total DESC, genre ASC
                LIMIT 18446744073709551615 OFFSET 5
            ) AS rest";
        $othersStmt = $this->db->prepare($othersSql);
        $othersStmt->execute([$user_id, $window['start'], $window['end']]);
        $othersRow = $othersStmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($othersRow) && (int) ($othersRow['total'] ?? 0) > 0) {
            $genres[] = [
                'genre' => 'Outros',
                'total' => (int) $othersRow['total'],
            ];
        }

        $trendSql = "SELECT " . $trendConfig['sql'] . " AS period_bucket, COUNT(*) AS total_completed
            FROM user_games ug
            WHERE ug.user_id = ?
                AND ug.completion_date IS NOT NULL
                AND ug.completion_date BETWEEN ? AND ?
            GROUP BY period_bucket
            ORDER BY period_bucket ASC";
        $trendStmt = $this->db->prepare($trendSql);
        $trendStmt->execute([$user_id, $window['start'], $window['end']]);
        $trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

        $timeTrendSql = "SELECT " . $trendConfig['sql'] . " AS period_bucket, COALESCE(AVG(time_spent_hours), 0) AS avg_time_spent
            FROM user_games ug
            WHERE ug.user_id = ?
                AND ug.completion_date IS NOT NULL
                AND ug.time_spent_hours IS NOT NULL
                AND ug.completion_date BETWEEN ? AND ?
            GROUP BY period_bucket
            ORDER BY period_bucket ASC";
        $timeTrendStmt = $this->db->prepare($timeTrendSql);
        $timeTrendStmt->execute([$user_id, $window['start'], $window['end']]);
        $timeTrendRows = $timeTrendStmt->fetchAll(PDO::FETCH_ASSOC);

        $statusSql = "SELECT status, COUNT(*) AS total
            FROM user_games
            WHERE user_id = ?
            GROUP BY status
            ORDER BY FIELD(status, 'Jogando', 'Zerado', 'Dropado')";
        $statusStmt = $this->db->prepare($statusSql);
        $statusStmt->execute([$user_id]);
        $statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

        $series = $this->buildDateSeries($window['series_start'], $window['series_end'], $trendConfig['date_step'], $trendConfig['label_format']);

        foreach ($trendRows as $row) {
            $bucket = $row['period_bucket'];
            if (!isset($series[$bucket])) {
                $series[$bucket] = [
                    'label' => $period === 'year' ? date('m/Y', strtotime($bucket . '-01')) : date('d/m', strtotime($bucket)),
                    'completed' => 0,
                ];
            }
            $series[$bucket]['completed'] = (int) $row['total_completed'];
        }

        $trend = [
            'labels' => array_map(static fn($item) => $item['label'], $series),
            'values' => array_map(static fn($item) => $item['completed'], $series),
        ];

        $timeSeries = $series;
        foreach ($timeSeries as $bucket => &$item) {
            $item['avg_time_spent'] = 0;
        }
        unset($item);

        foreach ($timeTrendRows as $row) {
            $bucket = $row['period_bucket'];
            if (!isset($timeSeries[$bucket])) {
                $timeSeries[$bucket] = [
                    'label' => $period === 'year' ? date('m/Y', strtotime($bucket . '-01')) : date('d/m', strtotime($bucket)),
                    'completed' => 0,
                    'avg_time_spent' => 0,
                ];
            }
            $timeSeries[$bucket]['avg_time_spent'] = (float) $row['avg_time_spent'];
        }

        $timeTrend = [
            'labels' => array_map(static fn($item) => $item['label'], $timeSeries),
            'values' => array_map(static fn($item) => (float) $item['avg_time_spent'], $timeSeries),
        ];

        return [
            'period' => $period,
            'period_label' => $periodConfig['label'],
            'anchor_date' => $anchorDate,
            'range' => $window,
            'summary' => [
                'total_completed' => (int) $summary['total_completed'],
                'avg_time_spent' => (float) $summary['avg_time_spent'],
            ],
            'genres' => $genres,
            'trend' => $trend,
            'time_trend' => $timeTrend,
            'status_breakdown' => $statusRows,
        ];
    }

    /**
     * Janela de calendário que contém $ref: semana (segunda a domingo), mês ou ano.
     * Devolve start/end (Y-m-d) e as datas de referência do período anterior e do seguinte.
     */
    private function calendarWindow(string $period, \DateTimeImmutable $ref): array {
        if ($period === 'year') {
            $start = $ref->setDate((int) $ref->format('Y'), 1, 1);
            $end = $ref->setDate((int) $ref->format('Y'), 12, 31);
            $prev = $start->modify('-1 year');
            $next = $start->modify('+1 year');
        } elseif ($period === 'month') {
            $start = $ref->modify('first day of this month');
            $end = $ref->modify('last day of this month');
            $prev = $start->modify('-1 month');
            $next = $start->modify('+1 month');
        } else {
            $start = $ref->modify('-' . ((int) $ref->format('N') - 1) . ' days');
            $end = $start->modify('+6 days');
            $prev = $start->modify('-7 days');
            $next = $start->modify('+7 days');
        }
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'prev' => $prev->format('Y-m-d'),
            'next' => $next->format('Y-m-d'),
        ];
    }

    /** Números de um período (para comparar com o atual). */
    private function periodTotals(int $user_id, string $start, string $end): array {
        $stmt = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM user_games WHERE user_id = ? AND completion_date BETWEEN ? AND ?) AS completed,
                (SELECT COALESCE(SUM(time_spent_hours), 0) FROM user_games WHERE user_id = ? AND completion_date BETWEEN ? AND ?) AS hours,
                (SELECT COUNT(*) FROM user_games WHERE user_id = ? AND platinum_at BETWEEN ? AND ?) AS platinums");
        $stmt->execute([$user_id, $start, $end, $user_id, $start, $end, $user_id, $start . ' 00:00:00', $end . ' 23:59:59']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'completed' => (int) ($row['completed'] ?? 0),
            'hours' => (float) ($row['hours'] ?? 0),
            'platinums' => (int) ($row['platinums'] ?? 0),
        ];
    }

    /**
     * Dados da tela do dashboard para um período de calendário (semana, mês ou ano):
     * números do topo com comparação, ritmo (gráfico), diário, biblioteca, jogando agora e gêneros.
     * $refDate escolhe qual semana/mês/ano; sem ela, usa o período do último jogo zerado.
     */
    private function buildDashboardView($user_id, $period, $refDate = null) {
        $user_id = (int) $user_id;
        $today = new \DateTimeImmutable('today');
        $ref = null;
        if ($refDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $refDate)) {
            $ref = \DateTimeImmutable::createFromFormat('!Y-m-d', $refDate) ?: null;
        }
        if (!$ref || $ref > $today) {
            $ref = new \DateTimeImmutable($this->getAnchorDate($user_id));
        }
        $window = $this->calendarWindow($period, $ref);

        // Jogos zerados dentro da janela, do mais recente para o mais antigo
        $entriesStmt = $this->db->prepare("
            SELECT ug.completion_date, ug.time_spent_hours, ug.rating, ug.platinum_at,
                   g.id AS game_id, g.title, g.cover_image, g.genre
            FROM user_games ug
            INNER JOIN games g ON g.id = ug.game_id
            WHERE ug.user_id = ?
              AND ug.completion_date IS NOT NULL
              AND ug.completion_date >= ?
              AND ug.completion_date < DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY ug.completion_date DESC, ug.id DESC
        ");
        $entriesStmt->execute([$user_id, $window['start'], $window['end']]);
        $allEntries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCompleted = count($allEntries);
        $totalHours = 0.0;
        $ratingSum = 0.0;
        $ratingCount = 0;
        $genreCounts = [];

        foreach ($allEntries as $row) {
            if ($row['time_spent_hours'] !== null) $totalHours += (float) $row['time_spent_hours'];
            if ($row['rating'] !== null && $row['rating'] !== '') {
                $ratingSum += (float) $row['rating'];
                $ratingCount++;
            }
            // Gêneros vêm como "adventure, puzzle": traduz e conta um por um
            foreach (explode(',', (string) $row['genre']) as $genre) {
                if (mb_strtolower(trim($genre)) === 'desconhecido') continue;
                $name = mgl_genre_pt($genre);
                if ($name !== '') $genreCounts[$name] = ($genreCounts[$name] ?? 0) + 1;
            }
        }

        arsort($genreCounts);
        $genres = [];
        $others = 0;
        $i = 0;
        foreach ($genreCounts as $name => $total) {
            if ($i < 5) $genres[] = ['name' => $name, 'total' => $total];
            else $others += $total;
            $i++;
        }
        if ($others > 0) $genres[] = ['name' => 'Outros', 'total' => $others];

        // Ritmo: um "balde" por dia (semana/mês) ou por mês (ano), com os jogos zerados nele
        $buckets = [];
        $cursor = new \DateTimeImmutable($window['start']);
        $last = new \DateTimeImmutable($window['end']);
        while ($cursor <= $last) {
            $key = $period === 'year' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $buckets[$key] = [
                'date' => $cursor->format('Y-m-d'),
                'games' => [],
                'hours' => 0.0,
                'future' => $cursor > $today,
            ];
            $cursor = $period === 'year' ? $cursor->modify('+1 month') : $cursor->modify('+1 day');
        }
        foreach (array_reverse($allEntries) as $row) {
            $key = $period === 'year' ? substr($row['completion_date'], 0, 7) : substr($row['completion_date'], 0, 10);
            if (!isset($buckets[$key])) continue;
            $buckets[$key]['games'][] = $row;
            $buckets[$key]['hours'] += (float) ($row['time_spent_hours'] ?? 0);
        }

        // Período anterior, para "+1 vs. semana anterior"
        $prevWindow = $this->calendarWindow($period, new \DateTimeImmutable($window['prev']));
        $previous = $this->periodTotals($user_id, $prevWindow['start'], $prevWindow['end']);
        $current = $this->periodTotals($user_id, $window['start'], $window['end']);

        // Limites da navegação: nada antes da primeira atividade, nada depois de hoje
        $firstStmt = $this->db->prepare("SELECT LEAST(
                COALESCE((SELECT MIN(completion_date) FROM user_games WHERE user_id = ?), CURDATE()),
                COALESCE((SELECT DATE(MIN(created_at)) FROM user_activity WHERE user_id = ?), CURDATE())
            ) AS first_day");
        $firstStmt->execute([$user_id, $user_id]);
        $firstDay = (string) ($firstStmt->fetchColumn() ?: $today->format('Y-m-d'));
        $hasPrev = $window['start'] > $firstDay;
        $hasNext = $window['next'] <= $today->format('Y-m-d');

        // O diário mostra no máximo 100 entradas; os números do topo consideram todas
        $diaryLimit = 100;
        $entries = array_slice($allEntries, 0, $diaryLimit);

        // Biblioteca inteira por status (não depende do período)
        $statusStmt = $this->db->prepare("SELECT status, COUNT(*) AS total FROM user_games WHERE user_id = ? GROUP BY status");
        $statusStmt->execute([$user_id]);
        $statusTotals = [];
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statusTotals[$row['status'] ?: 'Indefinido'] = (int) $row['total'];
        }

        $statusOrder = ['Zerado', 'Jogando', 'Dropado', 'Backlog', 'Quero jogar'];
        $library = [];
        foreach ($statusOrder as $status) {
            if (isset($statusTotals[$status])) {
                $library[] = ['status' => $status, 'total' => $statusTotals[$status]];
                unset($statusTotals[$status]);
            }
        }
        foreach ($statusTotals as $status => $total) {
            $library[] = ['status' => $status, 'total' => $total];
        }

        // Estimativa do backlog: jogos em Backlog x média de horas dos jogos que você já zerou
        $avgStmt = $this->db->prepare("SELECT AVG(time_spent_hours) FROM user_games
            WHERE user_id = ? AND status = 'Zerado' AND time_spent_hours > 0");
        $avgStmt->execute([$user_id]);
        $avgAllTime = (float) ($avgStmt->fetchColumn() ?: 0);
        $backlogCount = 0;
        foreach ($library as $row) {
            if ($row['status'] === 'Backlog') $backlogCount = (int) $row['total'];
        }

        // Jogando agora: status Jogando, com há quanto tempo (pela última troca de status no feed)
        $playingStmt = $this->db->prepare("
            SELECT g.id AS game_id, g.title, g.cover_image,
                   (SELECT MAX(ua.created_at) FROM user_activity ua
                     WHERE ua.user_id = ug.user_id AND ua.game_id = ug.game_id
                       AND ua.type = 'status_changed' AND ua.extra = 'Jogando') AS since
            FROM user_games ug JOIN games g ON g.id = ug.game_id
            WHERE ug.user_id = ? AND ug.status = 'Jogando'
            ORDER BY since DESC, ug.id DESC
            LIMIT 6");
        $playingStmt->execute([$user_id]);
        $playing = $playingStmt->fetchAll(PDO::FETCH_ASSOC);

        // Para o calendário: quantos jogos foram zerados em cada dia (marca os períodos com jogo)
        $daysStmt = $this->db->prepare("SELECT DATE(completion_date) AS d, COUNT(*) AS n FROM user_games
            WHERE user_id = ? AND completion_date IS NOT NULL GROUP BY DATE(completion_date)");
        $daysStmt->execute([$user_id]);
        $completionDays = [];
        foreach ($daysStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $completionDays[$row['d']] = (int) $row['n'];
        }

        return [
            'period' => $period,
            'range' => $window,
            'ref' => $ref->format('Y-m-d'),
            'first_day' => $firstDay,
            'completion_days' => $completionDays,
            'has_prev' => $hasPrev,
            'has_next' => $hasNext,
            'total_completed' => $totalCompleted,
            'total_hours' => $totalHours,
            'avg_hours' => $totalCompleted > 0 ? $totalHours / $totalCompleted : 0,
            'avg_rating' => $ratingCount > 0 ? $ratingSum / $ratingCount : null,
            'rating_count' => $ratingCount,
            'platinums' => $current['platinums'],
            'previous' => $previous,
            'buckets' => $buckets,
            'entries' => $entries,
            'entries_truncated' => $totalCompleted > $diaryLimit,
            'genres' => $genres,
            'library' => $library,
            'library_total' => array_sum(array_column($library, 'total')),
            'backlog_hours' => $backlogCount > 0 && $avgAllTime > 0 ? $backlogCount * $avgAllTime : null,
            'avg_hours_all_time' => $avgAllTime,
            'playing' => $playing,
        ];
    }

    private function fetchAllAggregatedStats($user_id) {
        $periods = ['week', 'month', 'year'];
        $payload = [];

        foreach ($periods as $period) {
            $payload[$period] = $this->fetchAggregatedStatsForPeriod($user_id, $period);
        }

        return $payload;
    }

    public function index() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'week';
        if (!in_array($period, ['week', 'month', 'year'], true)) {
            $period = 'week';
        }

        $refDate = isset($_GET['d']) ? (string) $_GET['d'] : null;
        $dash = $this->buildDashboardView($_SESSION['user_id'], $period, $refDate);

        $periodOptions = [
            'week' => 'Semanal',
            'month' => 'Mensal',
            'year' => 'Anual',
        ];

        include __DIR__ . '/../Views/dashboard.php';
    }

    public function data() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Usuário não autorizado']);
            exit();
        }

        $period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'week';
        $payload = [
            'default_period' => $period,
            'periods' => $this->fetchAllAggregatedStats($_SESSION['user_id']),
        ];

        header('Content-Type: application/json');
        echo json_encode($payload);
        exit();
    }
}
?>