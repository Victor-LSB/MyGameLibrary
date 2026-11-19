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
     * Traz também nota e trecho da análise (quando existirem) para o feed mostrar mais do que uma linha.
     */
    public function getFeedForUser($userId, $limit = 30, $includeSelf = true) {
        $limit = (int) $limit;

        $sql = "
            SELECT
                ua.id, ua.type, ua.extra, ua.created_at,
                u.id as actor_id, u.username as actor_username, u.display_name as actor_display_name, u.avatar as actor_avatar,
                g.id as game_id, g.title as game_title, g.cover_image as game_cover,
                (SELECT ug.rating FROM user_games ug WHERE ug.user_id = ua.user_id AND ug.game_id = ua.game_id LIMIT 1) as rating,
                (SELECT LEFT(ug2.review, 600) FROM user_games ug2 WHERE ug2.user_id = ua.user_id AND ug2.game_id = ua.game_id LIMIT 1) as review_excerpt,
                (SELECT CHAR_LENGTH(ug3.review) FROM user_games ug3 WHERE ug3.user_id = ua.user_id AND ug3.game_id = ua.game_id LIMIT 1) as review_length,
                (SELECT COUNT(*) FROM activity_likes al WHERE al.activity_id = ua.id) as like_count,
                EXISTS(SELECT 1 FROM activity_likes al2 WHERE al2.activity_id = ua.id AND al2.user_id = ?) as liked_by_me
            FROM user_activity ua
            INNER JOIN users u ON u.id = ua.user_id
            LEFT JOIN games g ON g.id = ua.game_id
            WHERE (
                ua.user_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)
                " . ($includeSelf ? "OR ua.user_id = ?" : "") . "
            )
            ORDER BY ua.created_at DESC
            LIMIT $limit
        ";

        $stmt = $this->conn->prepare($sql);
        $params = $includeSelf ? [$userId, $userId, $userId] : [$userId, $userId];
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agrupa atividades repetidas para o feed não virar uma fileira de cards iguais:
     * mesmo usuário + mesmo tipo (e mesmo status) + mesmo dia viram um único item com vários jogos.
     * Análises nunca são agrupadas. A ordem segue a do item mais recente de cada grupo.
     *
     * Cada grupo: ['type', 'extra', 'day', 'actor_id', 'items' => [linhas originais, da mais recente à mais antiga]]
     */
    public static function groupFeed(array $rows): array {
        $groups = [];
        $index = [];

        foreach ($rows as $row) {
            $day = substr($row['created_at'], 0, 10);
            $groupable = in_array($row['type'], ['game_added', 'status_changed'], true) && !empty($row['game_id']);

            if ($groupable) {
                $key = $row['actor_id'] . '|' . $row['type'] . '|' . ($row['extra'] ?? '') . '|' . $day;
                if (isset($index[$key])) {
                    $groups[$index[$key]]['items'][] = $row;
                    continue;
                }
                $index[$key] = count($groups);
            }

            $groups[] = [
                'type'     => $row['type'],
                'extra'    => $row['extra'],
                'day'      => $day,
                'actor_id' => $row['actor_id'],
                'items'    => [$row],
            ];
        }

        return $groups;
    }

    /**
     * Curte/descurte uma atividade do feed. Retorna o novo estado e a contagem atualizada.
     */
    public function toggleLike($activityId, $userId) {
        $stmt = $this->conn->prepare("SELECT id FROM activity_likes WHERE activity_id = ? AND user_id = ?");
        $stmt->execute([$activityId, $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $del = $this->conn->prepare("DELETE FROM activity_likes WHERE id = ?");
            $del->execute([$existing['id']]);
            $liked = false;
        } else {
            $ins = $this->conn->prepare("INSERT IGNORE INTO activity_likes (activity_id, user_id) VALUES (?, ?)");
            $ins->execute([$activityId, $userId]);
            $liked = true;
        }

        $countStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM activity_likes WHERE activity_id = ?");
        $countStmt->execute([$activityId]);
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

        return ['liked' => $liked, 'count' => (int) $count];
    }
}
