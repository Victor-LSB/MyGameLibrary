<?php

namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Activity;
use Victi\MyGameLibrary\Services\Csrf;

class FeedController {
    private $db;
    private $activityModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->activityModel = new Activity($this->db);
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index() {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        $userId = $_SESSION['user_id'];
        $activities = $this->activityModel->getFeedForUser($userId, 30, true);

        require_once __DIR__ . '/FollowController.php';
        $followController = new FollowController();
        $followSuggestions = $followController->getFollowSuggestions($userId, 5);

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
