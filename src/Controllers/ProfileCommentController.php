<?php
namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\ProfileComment;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Services\Csrf;

class ProfileCommentController {
    private $db;
    private $commentModel;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();

        if ($this->db) {
            $this->commentModel = new ProfileComment($this->db);
            $this->userModel = new User($this->db);
        }
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function add() {
        $this->startSession();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            return;
        }

        Csrf::verifyOrFail();

        $profileUserId = (int) ($_POST['profile_user_id'] ?? 0);
        $content = trim((string) ($_POST['content'] ?? ''));
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $authorId = (int) $_SESSION['user_id'];

        if (!$profileUserId || !$this->userModel->getUserById($profileUserId)) {
            echo json_encode(['success' => false, 'message' => 'Perfil inválido']);
            return;
        }

        if ($content === '') {
            echo json_encode(['success' => false, 'message' => 'O comentário não pode estar vazio']);
            return;
        }

        if (mb_strlen($content) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Comentário muito longo (máx. 1000 caracteres)']);
            return;
        }

        $commentId = $this->commentModel->add($profileUserId, $authorId, $content, $parentId);

        if ($commentId === false) {
            echo json_encode(['success' => false, 'message' => 'Não foi possível publicar o comentário']);
            return;
        }

        $this->notify($profileUserId, $authorId, $commentId, $parentId);

        $author = $this->userModel->getUserById($authorId);

        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $commentId,
                'author_id' => $authorId,
                'parent_id' => $parentId,
                'content' => $content,
                'username' => $author['username'],
                'display_name' => $author['display_name'],
                'avatar' => $author['avatar'],
            ],
        ]);
    }

    public function delete() {
        $this->startSession();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            return;
        }

        Csrf::verifyOrFail();

        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if (!$commentId) {
            echo json_encode(['success' => false, 'message' => 'Comentário inválido']);
            return;
        }

        $deleted = $this->commentModel->delete($commentId, $_SESSION['user_id']);

        if (!$deleted) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sem permissão para apagar este comentário']);
            return;
        }

        echo json_encode(['success' => true]);
    }

    /**
     * Notifica o dono do perfil sobre o novo comentário e, se for uma
     * resposta, também notifica o autor do comentário de topo — sem
     * duplicar notificação quando as duas pessoas forem a mesma.
     */
    private function notify($profileUserId, $authorId, $commentId, $parentId) {
        $notificationController = new NotificationController($this->db);
        $notified = [$authorId]; // nunca notifica quem comentou

        if (!in_array($profileUserId, $notified, true)) {
            $notificationController->createNotification(
                $profileUserId,
                $authorId,
                'profile_comment',
                $commentId,
                'comentou no seu perfil'
            );
            $notified[] = $profileUserId;
        }

        if ($parentId !== null) {
            $parentComment = $this->commentModel->find($parentId);
            if ($parentComment && !in_array((int) $parentComment['author_id'], $notified, true)) {
                $notificationController->createNotification(
                    $parentComment['author_id'],
                    $authorId,
                    'profile_comment_reply',
                    $commentId,
                    'respondeu ao seu comentário'
                );
            }
        }
    }
}
