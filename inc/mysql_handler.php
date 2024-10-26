<?php
// inc/mysql_handler.php

class mysql_handler {
    private string $host = "localhost";
    private string $user = "root";
    private string $password = "";
    private string $database = "rhr";
    protected ?mysqli $conn = null;
    protected PDO $pdo;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->database);
        $this->conn->set_charset("utf8");
        if ( $this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        $this->pdo = new PDO("mysql:host=$this->host;dbname=$this->database;charset=utf8mb4", $this->user, $this->password);
        // Set PDO error mode to exception for better error handling
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function __destruct() {
        mysqli_close($this->conn);
    }

    public function Query($sql): mysqli_result|bool
    {
        return $this->conn->query($sql);
    }

    public function getConnection(): mysqli {
        return $this->conn;
    }

    public function getPDO(): mysqli {
        return $this->pdo;
    }
}
?>