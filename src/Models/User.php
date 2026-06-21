<?php
namespace Victi\MyGameLibrary\Models;

use PDO;

class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function emailExists($email) {
        $sql = "SELECT 1 FROM " . $this->table . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);
       return $stmt->fetch() !== false;
    }

    public function usernameExists($username) {
        $sql = "SELECT 1 FROM " . $this->table . " WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }

    public function register($username, $email, $password) {
        if ($this->emailExists($email) || $this->usernameExists($username)) {
            return false;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO " . $this->table . " (username, email, password) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$username, $email, $hashed_password]);
    }

    public function login($email, $password) {
        $sql = "SELECT * FROM " . $this->table . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        } else {
            return false;
        }
    }

    
    public function getUserByUsername($username) {
        $sql = "SELECT id, username, email, display_name, bio, avatar, banner FROM " . $this->table . " WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

   
    public function getUserById($id) {
        $sql = "SELECT id, username, email, display_name, bio, avatar, banner FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
    public function updateProfile($id, $displayName, $bio, $avatar, $banner) {
        $sql = "UPDATE " . $this->table . " SET display_name = ?, bio = ?, avatar = ?, banner = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$displayName, $bio, $avatar, $banner, $id]);
    }


    public function getUserByResetToken($token) {
        $sql = "SELECT id, email FROM " . $this->table . " WHERE reset_token = ? AND reset_token_expires_at > NOW() LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function savePasswordResetToken($email, $token, $expires_at) {
        $sql = "UPDATE " . $this->table . " SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$token, $expires_at, $email]);
    }

    public function updatePassword($user_id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE " . $this->table . " SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$hashed_password, $user_id]);
    }
}
?>