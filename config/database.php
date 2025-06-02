<?php
class Database {
    private $host = '192.168.100.4:3306';
    private $db_name = 'sota';
    private $username = 'root';
    private $password = 'sotaR00t!';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die('Erreur de connexion: ' . $e->getMessage());
        }
        return $this->conn;
    }
}
?>
