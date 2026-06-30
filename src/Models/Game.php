<?php
namespace Victi\MyGameLibrary\Models;
use PDO;

class Game {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addGame($external_id, $title, $platform, $genre, $release_date, $cover_image) {
        $sql = "INSERT INTO games (external_id, title, platform, genre, release_date, cover_image) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt->execute([$external_id, $title, $platform, $genre, $release_date, $cover_image])) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function findGameByExternalId($external_id) {
        $sql = "SELECT id FROM games WHERE external_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$external_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getGameById($game_id) {
        $sql = "SELECT * FROM games WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$game_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // A FUNÇÃO QUE ESTAVA A FALTAR E CAUSOU O ERRO FATAL!
    public function addGameToUser($user_id, $game_id, $status = 'Backlog') {
        $sql = "INSERT INTO user_games (user_id, game_id, status) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$user_id, $game_id, $status]);
    }

    public function checkUserGame($user_id, $game_id) {
        $sql = "SELECT 1 FROM user_games WHERE user_id = ? AND game_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id, $game_id]);
        return $stmt->fetch() !== false;
    }

    public function getGamesByUserId($user_id, $status = null, $search = null, $tag = null) {
        $sql = "SELECT g.*, ug.status, ug.rating, ug.completion_date, ug.time_spent_hours FROM games g JOIN user_games ug ON g.id = ug.game_id WHERE ug.user_id = ?";
        $params = [$user_id];

        if (!empty($search)) {
            $sql .= " AND g.title LIKE ?";
            $params[] = '%' . $search . '%';
        }

        if (!empty($status)) {
            $sql .= " AND ug.status = ?";
            $params[] = $status;
        }

        if (!empty($tag)) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM game_tags gt
                INNER JOIN tags t ON t.id = gt.tag_id
                WHERE gt.user_id = ug.user_id
                    AND gt.game_id = g.id
                    AND t.name = ?
            )";
            $params[] = $tag;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeTags($tags) {
        $cleanTags = [];
        $seen = [];

        foreach ($tags as $tag) {
            $tag = trim(strip_tags((string) $tag));

            if ($tag === '') {
                continue;
            }

            $tag = preg_replace('/\s+/', ' ', $tag);

            if (function_exists('mb_substr')) {
                $tag = mb_substr($tag, 0, 50);
                $tagKey = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
            } else {
                $tag = substr($tag, 0, 50);
                $tagKey = strtolower($tag);
            }

            if (isset($seen[$tagKey])) {
                continue;
            }

            $seen[$tagKey] = true;
            $cleanTags[] = $tag;
        }

        return $cleanTags;
    }

    private function getOrCreateTagId($user_id, $tagName) {
        $sql = "INSERT INTO tags (user_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id, $tagName]);

        return $this->conn->lastInsertId();
    }

    public function saveTagsForGame($user_id, $game_id, $tags) {
        $cleanTags = $this->normalizeTags($tags);

        try {
            $this->conn->beginTransaction();

            $deleteSql = "DELETE FROM game_tags WHERE user_id = ? AND game_id = ?";
            $deleteStmt = $this->conn->prepare($deleteSql);
            $deleteStmt->execute([$user_id, $game_id]);

            if (!empty($cleanTags)) {
                $insertSql = "INSERT IGNORE INTO game_tags (user_id, game_id, tag_id) VALUES (?, ?, ?)";
                $insertStmt = $this->conn->prepare($insertSql);

                foreach ($cleanTags as $tagName) {
                    $tagId = $this->getOrCreateTagId($user_id, $tagName);
                    $insertStmt->execute([$user_id, $game_id, $tagId]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Erro ao salvar tags do jogo: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteSavedTagsForGame($user_id, $game_id) {
        try {
            $this->conn->beginTransaction();

            $tagsSql = "SELECT DISTINCT tag_id
                FROM game_tags
                WHERE user_id = ? AND game_id = ?";
            $tagsStmt = $this->conn->prepare($tagsSql);
            $tagsStmt->execute([$user_id, $game_id]);
            $tagIds = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);

            $deleteLinksSql = "DELETE FROM game_tags WHERE user_id = ? AND game_id = ?";
            $deleteLinksStmt = $this->conn->prepare($deleteLinksSql);
            $deleteLinksStmt->execute([$user_id, $game_id]);

            if (!empty($tagIds)) {
                $cleanupSql = "DELETE FROM tags
                    WHERE user_id = ?
                        AND id IN (" . implode(',', array_fill(0, count($tagIds), '?')) . ")
                        AND NOT EXISTS (
                            SELECT 1
                            FROM game_tags
                            WHERE game_tags.tag_id = tags.id
                                AND game_tags.user_id = ?
                        )";
                $cleanupStmt = $this->conn->prepare($cleanupSql);
                $cleanupStmt->execute(array_merge([$user_id], $tagIds, [$user_id]));
            }

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Erro ao apagar tags salvas do jogo: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteSavedTagForUser($user_id, $tag_id) {
        try {
            $this->conn->beginTransaction();

            $deleteLinksSql = "DELETE FROM game_tags WHERE user_id = ? AND tag_id = ?";
            $deleteLinksStmt = $this->conn->prepare($deleteLinksSql);
            $deleteLinksStmt->execute([$user_id, $tag_id]);

            $cleanupSql = "DELETE FROM tags
                WHERE id = ?
                    AND user_id = ?
                    AND NOT EXISTS (
                        SELECT 1
                        FROM game_tags
                        WHERE game_tags.tag_id = tags.id
                            AND game_tags.user_id = ?
                    )";
            $cleanupStmt = $this->conn->prepare($cleanupSql);
            $cleanupStmt->execute([$tag_id, $user_id, $user_id]);

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Erro ao apagar tag salva: ' . $e->getMessage());
            return false;
        }
    }

    public function getTagsForGame($user_id, $game_id) {
        $sql = "SELECT t.id, t.name
            FROM tags t
            INNER JOIN game_tags gt ON gt.tag_id = t.id
            WHERE gt.user_id = ? AND gt.game_id = ?
            ORDER BY t.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id, $game_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeCustomTagFromGame($user_id, $game_id, $tag_id) {
        try {
            $this->conn->beginTransaction();

            $deleteLinkSql = "DELETE FROM game_tags WHERE user_id = ? AND game_id = ? AND tag_id = ?";
            $deleteLinkStmt = $this->conn->prepare($deleteLinkSql);
            $deleteLinkStmt->execute([$user_id, $game_id, $tag_id]);

            $cleanupSql = "DELETE FROM tags
                WHERE id = ?
                    AND user_id = ?
                    AND NOT EXISTS (
                        SELECT 1
                        FROM game_tags
                        WHERE tag_id = tags.id AND user_id = ?
                    )";
            $cleanupStmt = $this->conn->prepare($cleanupSql);
            $cleanupStmt->execute([$tag_id, $user_id, $user_id]);

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Erro ao remover tag personalizada: ' . $e->getMessage());
            return false;
        }
    }

    public function getUniqueTagsForUser($user_id) {
        $sql = "SELECT t.id, t.name, COUNT(DISTINCT gt.game_id) AS usage_count
            FROM tags t
            INNER JOIN game_tags gt ON gt.tag_id = t.id
            WHERE gt.user_id = ?
            GROUP BY t.id, t.name
            ORDER BY usage_count DESC, t.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateGameStatus($user_id, $game_id, $status, $rating, $completion_date = null, $time_spent_hours = null) {
        $sql = "UPDATE user_games SET status = ?, rating = ?, completion_date = ?, time_spent_hours = ? WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$status, $rating, $completion_date, $time_spent_hours, $user_id, $game_id]);
    }

    public function deleteGameFromUser($user_id, $game_id) {
        $sql = "DELETE FROM user_games WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$user_id, $game_id]);
    }

    public function getUserGameInfo($user_id, $game_id) {
        $sql = "SELECT g.id, g.external_id, g.title, g.cover_image, g.description, ug.status, ug.rating, ug.review, ug.completion_date, ug.time_spent_hours 
            FROM games g 
            JOIN user_games ug ON g.id = ug.game_id 
            WHERE ug.user_id = ? AND g.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id, $game_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateReviewWithCompletionData($review, $completion_date, $time_spent_hours, $user_id, $game_id) {
        $sql = "UPDATE user_games SET review = ?, completion_date = ?, time_spent_hours = ? WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$review, $completion_date, $time_spent_hours, $user_id, $game_id]);
    }

    public function updateGameDescription($game_id, $description) {
        $sql = "UPDATE games SET description = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$description, $game_id]);
    }

    public function updateReview($review, $user_id, $game_id) {
        $sql = "UPDATE user_games SET review = ? WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$review, $user_id, $game_id]);
    }

    public function getRecentGamesByUserId($user_id, $limit = 5) {
        $sql = "SELECT g.*, ug.status, ug.rating FROM games g JOIN user_games ug ON g.id = ug.game_id WHERE ug.user_id = ? ORDER BY ug.id DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>