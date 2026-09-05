<?php
require_once 'db.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $email;
    public $password_hash;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new user
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (email, password_hash) VALUES (:email, :password_hash)";
        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password_hash = htmlspecialchars(strip_tags($this->password_hash));

        // Bind values
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Find user by email
    public function findByEmail($email) {
        $query = "SELECT id, email, password_hash, created_at, updated_at FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }

        return false;
    }

    // Find user by ID
    public function findById($id) {
        $query = "SELECT id, email, password_hash, created_at, updated_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }

        return false;
    }

    // Update password
    public function updatePassword($new_password_hash) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Bind values (hash should not be altered with htmlspecialchars)
        $stmt->bindParam(":password_hash", $new_password_hash);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Validate password (used during login)
    public function validatePassword($password) {
        return password_verify($password, $this->password_hash);
    }

    // Get all users (for testing purposes only)
    public static function getAllUsers($db) {
        $query = "SELECT id, email, created_at FROM users ORDER BY id";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>