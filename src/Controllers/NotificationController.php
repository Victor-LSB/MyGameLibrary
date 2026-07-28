<?php

namespace Victi\MyGameLibrary\Controllers;

use PDO;

class NotificationController {
    private $db;
    private $userId;

    public function __construct() {
        try {
            $this->db = new PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
            exit;
        }

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

    public function getNotifications() {
        header('Content-Type: application/json');
        
        $stmt = $this->db->prepare("
            SELECT 
                n.id, 
                n.type,
                n.message,
                n.is_read,
                n.created_at,
                u.username as actor_name,
                u.avatar as actor_avatar
            FROM notifications n
            INNER JOIN users u ON u.id = n.actor_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$this->userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = $this->countUnreadCount();

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $count,
        ]);
    }

    public function countUnread() {
        header('Content-Type: application/json');
        $count = $this->countUnreadCount();
        echo json_encode(['unread_count' => $count]);
    }

    private function countUnreadCount() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    }

    public function markAsRead() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $notificationId = $input['notification_id'] ?? null;

        if (!$notificationId) {
            echo json_encode(['success' => false]);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $success = $stmt->execute([$notificationId, $this->userId]);

        echo json_encode(['success' => $success]);
    }

    public function markAllAsRead() {
        header('Content-Type: application/json');
        
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);

        echo json_encode(['success' => true]);
    }

    public function createNotification($userId, $actorId, $type, $relatedId = null, $message = '') {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, actor_id, type, related_id, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$userId, $actorId, $type, $relatedId, $message]);
    }

    public function notifyFollowers($actorId, $gameId, $message) {
        $stmt = $this->db->prepare("
            SELECT follower_id FROM user_follows 
            WHERE following_id = ?
        ");
        $stmt->execute([$actorId]);
        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($followers as $follower) {
            $this->createNotification(
                $follower['follower_id'],
                $actorId,
                'review_posted',
                $gameId,
                $message
            );
        }

        return true;
    }
}