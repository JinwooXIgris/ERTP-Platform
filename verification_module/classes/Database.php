<?php
// Connects to the group database (uiconf)
class Database {
    private $host = "localhost";
    private $dbname = "uiconf";
    private $username = "root";
    private $password = "";

    public function connect() {
        try {
            $conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->exec("SET time_zone = '+01:00'");   // Africa/Lagos, as the schema says
            return $conn;
        } catch (PDOException $e) {
            header("Content-Type: application/json");
            die(json_encode(["success" => false, "message" => "Database connection failed."]));
        }
    }
}
