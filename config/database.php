<?php
class Database {
    private $host = '192.168.100.4';
    private $port = '3306';
    private $db_name = 'sota';
    private $username = 'root';
    private $password = 'sotaR00t!';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $e) {
            error_log('Erreur de connexion DB: ' . $e->getMessage());
            die('Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.');
        }
        return $this->conn;
    }
}