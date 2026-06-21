<?php
namespace Victi\MyGameLibrary\Database;
use PDO;
use PDOException;

class Database {
    private $conn = null;

    public function connect() {
        try {
            if ($this->conn == null) {
                $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
                $dbname = $_ENV['DB_DATABASE'] ?? 'mygamelibrary';
                $user = $_ENV['DB_USERNAME'] ?? 'root';
                $password = $_ENV['DB_PASSWORD'] ?? '';

                $this->conn = new PDO("mysql:host={$host};dbname={$dbname}", $user, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
            throw new \Exception("Não foi possível conectar ao banco de dados.");
        }
    }
}
?>