<?php
namespace Victi\MyGameLibrary\Database;

use PDO;
use PDOException;
use Exception;

class Database {
    private $conn = null;

    public function connect() {
        try {
            if ($this->conn === null) {
                $host     = $_ENV['DB_HOST']     ?? $_SERVER['DB_HOST']     ?? '127.0.0.1';
                $dbname   = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'mygamelibrary';
                $user     = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'gameloggd_user';
                $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';

                $this->conn = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            return $this->conn;
        } catch (PDOException $e) {
            error_log("Erro de conexão com o banco de dados: " . $e->getMessage());

            throw new Exception("Não foi possível conectar ao banco de dados.");
        }
    }
}