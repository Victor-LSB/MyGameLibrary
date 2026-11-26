<?php

namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Activity;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\Session;

class FeedController {
    private $db;
    private $activityModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->activityModel = new Activity($this->db);
    }

    private function startSession() {
        Session::start();
    }

    public function index() {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        $userId = $_SESSION['user_id'];
        // Busca mais linhas do que o necessário: o agrupamento junta várias atividades em um item só.
        // "Carregar mais" só aumenta o limite (de 60 em 60, até 600): o agrupamento por dia
        // continua certo porque a lista inteira é montada de novo.
        $feedStep = 60;
        $feedLimit = min(600, max($feedStep, (int) ($_GET['limit'] ?? $feedStep)));
        $feedLimit -= $feedLimit % $feedStep;
        $activities = $this->activityModel->getFeedForUser($userId, $feedLimit, true);
        $feedGroups = Activity::groupFeed($activities);
        $feedHasMore = count($activities) >= $feedLimit && $feedLimit < 600;
        $feedNextLimit = $feedLimit + $feedStep;

        require_once __DIR__ . '/FollowController.php';
        $followController = new FollowController();
        $followSuggestions = $followController->getFollowSuggestions($userId, 5);

        // Card "você" da coluna da esquerda
        $me = (new User($this->db))->getUserById($userId);
        $meStats = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM user_games WHERE user_id = ?) AS games,
                (SELECT COUNT(*) FROM user_games WHERE user_id = ? AND completion_date >= ?) AS completed_year,
                (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) AS followers,
                (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) AS following");
        $meStats->execute([$userId, $userId, date('Y') . '-01-01', $userId, $userId]);
        $meStats = $meStats->fetch(\PDO::FETCH_ASSOC) ?: [];

        include __DIR__ . '/../Views/feed/index.php';
    }

    /**
     * Curte/descurte uma atividade do feed via AJAX.
     */
    public function toggleLike() {
        $this->startSession();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            return;
        }

        Csrf::verifyOrFail();

        $activityId = (int) ($_POST['activity_id'] ?? 0);
        if (!$activityId) {
            echo json_encode(['success' => false, 'message' => 'Atividade inválida']);
            return;
        }

        $result = $this->activityModel->toggleLike($activityId, $_SESSION['user_id']);
        echo json_encode(['success' => true] + $result);
    }
}
