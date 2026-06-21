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

    public function getGamesByUserId($user_id, $status = null, $search = null) {
        $sql = "SELECT g.*, ug.status, ug.rating FROM games g JOIN user_games ug ON g.id = ug.game_id WHERE ug.user_id = ?";
        $params = [$user_id];

        if (!empty($search)) {
            $sql .= " AND g.title LIKE ?";
            $params[] = '%' . $search . '%';
        }

        if (!empty($status)) {
            $sql .= " AND ug.status = ?";
            $params[] = $status;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateGameStatus($user_id, $game_id, $status, $rating) {
        $sql = "UPDATE user_games SET status = ?, rating = ? WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$status, $rating, $user_id, $game_id]);
    }

    public function deleteGameFromUser($user_id, $game_id) {
        $sql = "DELETE FROM user_games WHERE user_id = ? AND game_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$user_id, $game_id]);
    }

    public function getUserGameInfo($user_id, $game_id) {
        $sql = "SELECT g.id, g.external_id, g.title, g.cover_image, g.description, ug.status, ug.rating, ug.review 
            FROM games g 
            JOIN user_games ug ON g.id = ug.game_id 
            WHERE ug.user_id = ? AND g.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id, $game_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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