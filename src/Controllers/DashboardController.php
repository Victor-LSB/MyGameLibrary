<?php
namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use PDO;

class DashboardController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function getPeriodConfig($period) {
        $periods = [
            'week' => [
                'label' => 'Current Week',
                'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
            ],
            'month' => [
                'label' => 'Current Month',
                'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
            ],
            'year' => [
                'label' => 'Current Year',
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
                SELECT LOWER(TRIM(COALESCE(g.genre, 'Desconhecido'))) AS genre, COUNT(*) AS total
                FROM user_games ug
                INNER JOIN games g ON g.id = ug.game_id
                WHERE ug.user_id = ?
                    AND ug.completion_date IS NOT NULL
                    AND ug.completion_date BETWEEN ? AND ?
                GROUP BY LOWER(TRIM(COALESCE(g.genre, 'Desconhecido')))
            ) AS genre_counts
            ORDER BY total DESC, genre ASC
            LIMIT 5";
        $genreStmt = $this->db->prepare($genreTopSql);
        $genreStmt->execute([$user_id, $window['start'], $window['end']]);
        $genres = $genreStmt->fetchAll(PDO::FETCH_ASSOC);

        $othersSql = "SELECT 'Others' AS genre, COALESCE(SUM(rest.total), 0) AS total
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
                'genre' => 'Others',
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
        $periodConfig = $this->getPeriodConfig($period);

        $dashboardStats = $this->fetchAggregatedStatsForPeriod($_SESSION['user_id'], $period);

        $periodOptions = [
            'week' => 'Weekly',
            'month' => 'Monthly',
            'year' => 'Yearly',
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