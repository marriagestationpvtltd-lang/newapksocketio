<?php
require_once __DIR__ . '/../../config/db.php';
class Database {
    private $host = ADMINCHAT_DB_HOST;
    private $db_name = ADMINCHAT_DB_NAME;
    private $username = ADMINCHAT_DB_USER;
    private $password = ADMINCHAT_DB_PASS;
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>