<?php
/**
 * Database connection configuration
 * Conforms to Week 7 Module: Connecting with PDO (Slide 9)
 */

$host = 'localhost';
$db   = 'solar_db';
$user = 'root';
$pass = '';

class Database {
    private $host = 'localhost';
    private $db_name = 'solar_db';
    private $username = 'root';
    private $password = '';
    /**
     * @var \PDO|null
     */
    public ?PDO $conn = null;

    /**
     * Get PDO database connection
     *
     * @return \PDO|null
     */
    public function getConnection(): ?PDO {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $exception) {
            die("Connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}

// Procedural $pdo instance directly matching Slide 9
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>