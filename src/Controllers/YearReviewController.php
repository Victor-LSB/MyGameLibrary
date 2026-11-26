<?php
namespace Victi\MyGameLibrary\Controllers;

use PDO;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Services\Session;

/**
 * "Seu ano em jogos": retrospectiva pública de um usuário num ano
 * (index.php?action=year_review&u=victor&y=2026). Sem ?u= mostra a do usuário logado.
 */
class YearReviewController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->connect();
    }

    public function index() {
        Session::start();

        $username = trim((string) ($_GET['u'] ?? ''));
        if ($username === '') {
            if (empty($_SESSION['username'])) {
                header('Location: index.php?action=login');
                exit();
            }
            $username = $_SESSION['username'];
        }

        $profileUser = (new User($this->db))->getUserByUsername($username);
        if (!$profileUser) {
            mgl_not_found('Esse usuário não existe.');
        }
        $userId = (int) $profileUser['id'];

        $availableYears = $this->availableYears($userId);
        $currentYear = (int) date('Y');
        $year = (int) ($_GET['y'] ?? 0);
        if (!$year) {
            // Até fevereiro, o padrão é o ano que acabou de terminar
            $year = ((int) date('n') <= 2 && in_array($currentYear - 1, $availableYears, true)) ? $currentYear - 1 : $currentYear;
        }
        if ($year < 1980 || $year > $currentYear) {
            $year = $currentYear;
        }

        $review = $this->buildReview($userId, $year);
        $isOwner = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId;

        include __DIR__ . '/../Views/year_review/index.php';
    }

    /** Anos em que o usuário zerou algo ou mexeu na biblioteca (para o seletor de ano). */
    private function availableYears(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT y FROM (
                SELECT YEAR(completion_date) AS y FROM user_games WHERE user_id = ? AND completion_date IS NOT NULL
                UNION SELECT YEAR(created_at) FROM user_activity WHERE user_id = ?
            ) t WHERE y IS NOT NULL ORDER BY y DESC");
        $stmt->execute([$userId, $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function buildReview(int $userId, int $year): array {
        $start = "$year-01-01";
        $end = "$year-12-31";

        // Jogos zerados no ano (base de quase tudo)
        $stmt = $this->db->prepare("
            SELECT g.id, g.title, g.cover_image, g.genre, ug.rating, ug.time_spent_hours,
                   ug.completion_date, ug.platinum_at
            FROM user_games ug JOIN games g ON g.id = ug.game_id
            WHERE ug.user_id = ? AND ug.completion_date BETWEEN ? AND ?
            ORDER BY ug.completion_date ASC");
        $stmt->execute([$userId, $start, $end]);
        $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hours = 0.0;
        $ratings = [];
        $genres = [];
        $byMonth = array_fill(1, 12, 0);
        $longest = null;
        foreach ($completed as $g) {
            $h = (float) ($g['time_spent_hours'] ?? 0);
            $hours += $h;
            if ($h > 0 && ($longest === null || $h > (float) $longest['time_spent_hours'])) {
                $longest = $g;
            }
            if ($g['rating']) {
                $ratings[] = (int) $g['rating'];
            }
            foreach (array_filter(array_map('trim', explode(',', (string) $g['genre']))) as $genre) {
                if (mb_strtolower($genre) === 'desconhecido') continue;
                $key = mgl_genre_pt($genre);
                $genres[$key] = ($genres[$key] ?? 0) + 1;
            }
            $byMonth[(int) substr($g['completion_date'], 5, 2)]++;
        }
        arsort($genres);

        // Favoritos: maior nota primeiro, depois mais horas
        $favorites = array_values(array_filter($completed, fn($g) => (int) $g['rating'] >= 4));
        usort($favorites, fn($a, $b) => ((int) $b['rating'] <=> (int) $a['rating'])
            ?: ((float) $b['time_spent_hours'] <=> (float) $a['time_spent_hours']));

        $platinums = $this->db->prepare("SELECT COUNT(*) FROM user_games WHERE user_id = ? AND platinum_at BETWEEN ? AND ?");
        $platinums->execute([$userId, "$start 00:00:00", "$end 23:59:59"]);

        $activity = $this->db->prepare("SELECT type, COUNT(*) FROM user_activity
            WHERE user_id = ? AND created_at BETWEEN ? AND ? AND type IN ('game_added', 'review_posted') GROUP BY type");
        $activity->execute([$userId, "$start 00:00:00", "$end 23:59:59"]);
        $activityCounts = $activity->fetchAll(PDO::FETCH_KEY_PAIR);

        $busiestMonth = max($byMonth) > 0 ? array_search(max($byMonth), $byMonth, true) : null;

        return [
            'completed'      => $completed,
            'completedCount' => count($completed),
            'hours'          => $hours,
            'avgRating'      => $ratings ? array_sum($ratings) / count($ratings) : null,
            'topGenres'      => array_slice($genres, 0, 3, true),
            'favorites'      => array_slice($favorites, 0, 5),
            'longest'        => $longest,
            'platinums'      => (int) $platinums->fetchColumn(),
            'added'          => (int) ($activityCounts['game_added'] ?? 0),
            'reviews'        => (int) ($activityCounts['review_posted'] ?? 0),
            'byMonth'        => $byMonth,
            'busiestMonth'   => $busiestMonth,
        ];
    }
}
