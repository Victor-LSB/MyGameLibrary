<?php

namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use PDO;

class FollowController {
    private $db;
    private $userId;

    public function __construct() {
        // Reaproveita a mesma classe de conexão usada pelo resto do projeto
        // (evita nomes de variáveis de ambiente diferentes entre local/prod)
        try {
            $database = new Database();
            $this->db = $database->connect();
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
            exit;
        }

        // Obter usuário da sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userId = $_SESSION['user_id'] ?? null;

        if (!$this->userId) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Toggle de follow/unfollow
     */
    public function toggle() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $followingId = $input['user_id'] ?? null;
        $isFollowing = $input['is_following'] ?? false;

        if (!$followingId) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido']);
            return;
        }

        if ($isFollowing) {
            $result = $this->unfollow($followingId);
        } else {
            $result = $this->follow($followingId);
        }

        echo json_encode($result);
    }

    /**
     * Seguir um usuário
     */
    private function follow($followingId) {
        // Validações
        if ($followingId == $this->userId) {
            return ['success' => false, 'message' => 'Não pode seguir a si mesmo'];
        }

        // Verificar se usuário existe
        $checkStmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$followingId]);
        if (!$checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Usuário não encontrado'];
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_follows (follower_id, following_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$this->userId, $followingId]);

            // Notifica o usuário que ganhou um novo seguidor
            $notificationController = new NotificationController($this->db);
            $notificationController->createNotification(
                $followingId,
                $this->userId,
                'new_follower',
                null,
                'Começou a seguir você'
            );

            return ['success' => true, 'message' => 'Você começou a seguir este usuário'];
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                return ['success' => false, 'message' => 'Você já segue este usuário'];
            }
            return ['success' => false, 'message' => 'Erro ao seguir usuário'];
        }
    }

    /**
     * Deixar de seguir um usuário
     */
    private function unfollow($followingId) {
        $stmt = $this->db->prepare("
            DELETE FROM user_follows 
            WHERE follower_id = ? AND following_id = ?
        ");
        $stmt->execute([$this->userId, $followingId]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Você deixou de seguir este usuário'];
        }

        return ['success' => false, 'message' => 'Você não segue este usuário'];
    }

    /**
     * Verificar se segue um usuário
     */
    public function isFollowing($followingId) {
        $stmt = $this->db->prepare("
            SELECT id FROM user_follows 
            WHERE follower_id = ? AND following_id = ?
            LIMIT 1
        ");
        $stmt->execute([$this->userId, $followingId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Obter lista de seguidores
     */
    public function getFollowers($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar
            FROM user_follows uf
            INNER JOIN users u ON u.id = uf.follower_id
            WHERE uf.following_id = ?
            ORDER BY uf.created_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obter lista de quem segue
     */
    public function getFollowing($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar
            FROM user_follows uf
            INNER JOIN users u ON u.id = uf.following_id
            WHERE uf.follower_id = ?
            ORDER BY uf.created_at DESC
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar seguidores
     */
    public function countFollowers($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM user_follows 
            WHERE following_id = ?
        ");
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    /**
     * Contar quem segue
     */
    public function countFollowing($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM user_follows 
            WHERE follower_id = ?
        ");
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
}