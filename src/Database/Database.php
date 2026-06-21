<?php
namespace Victi\MyGameLibrary\Database;
use PDO;
use PDOException;

class Database {
    private $host = "127.0.0.1";
    private $dbname = "gameloggd";
    private $user = "root";
    private $password = "";
    private $conn = null;


    public function connect() {
        try {
            if ($this->conn == null) {
                $this->conn = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->user, $this->password);
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