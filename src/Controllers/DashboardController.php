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
     * Dados da tela do dashboard para um período (semana, mês ou ano):
     * números do topo, diário de jogos zerados, biblioteca por status e gêneros.
     */
    private function buildDashboardView($user_id, $period) {
        $anchorDate = $this->getAnchorDate($user_id);
        $window = $this->getPeriodWindow($period, $anchorDate);

        // Jogos zerados dentro da janela, do mais recente para o mais antigo
        $entriesStmt = $this->db->prepare("
            SELECT ug.completion_date, ug.time_spent_hours, ug.rating,
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

        return [
            'period' => $period,
            'range' => $window,
            'total_completed' => $totalCompleted,
            'total_hours' => $totalHours,
            'avg_hours' => $totalCompleted > 0 ? $totalHours / $totalCompleted : 0,
            'avg_rating' => $ratingCount > 0 ? $ratingSum / $ratingCount : null,
            'entries' => $entries,
            'entries_truncated' => $totalCompleted > $diaryLimit,
            'genres' => $genres,
            'library' => $library,
            'library_total' => array_sum(array_column($library, 'total')),
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

        $dash = $this->buildDashboardView($_SESSION['user_id'], $period);

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