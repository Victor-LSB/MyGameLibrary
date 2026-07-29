<?php

namespace Victi\MyGameLibrary\Models;

use PDO;

class Activity {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Registra uma atividade do usuário (jogo adicionado, status mudou, análise publicada).
     */
    public function log($userId, string $type, $gameId = null, $extra = null) {
        $sql = "INSERT INTO user_activity (user_id, game_id, type, extra) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$userId, $gameId, $type, $extra]);
    }

    /**
     * Feed de atividades de quem o usuário $userId segue (+ opcionalmente ele mesmo).
     */
    public function getFeedForUser($userId, $limit = 30, $includeSelf = true) {
        $limit = (int) $limit;

        $sql = "
            SELECT
                ua.id, ua.type, ua.extra, ua.created_at,
                u.id as actor_id, u.username as actor_username, u.display_name as actor_display_name, u.avatar as actor_avatar,
                g.id as game_id, g.title as game_title, g.cover_image as game_cover
            FROM user_activity ua
            INNER JOIN users u ON u.id = ua.user_id
            LEFT JOIN games g ON g.id = ua.game_id
            WHERE ua.user_id IN (
                SELECT following_id FROM user_follows WHERE follower_id = ?
            )" . ($includeSelf ? " OR ua.user_id = ?" : "") . "
            ORDER BY ua.created_at DESC
            LIMIT $limit
        ";

        $stmt = $this->conn->prepare($sql);
        $params = $includeSelf ? [$userId, $userId] : [$userId];
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
