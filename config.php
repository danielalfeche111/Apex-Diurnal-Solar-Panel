<?php
/**
 * Corsame Coffee Shop ☕ Centralized PDO Database Configuration
 * Pattern from: Souri-Dev/webdev1-midterm-discussion (database/config.php)
 * 
 * Usage: require 'config.php'; $pdo = getConnection();
 */

function getConnection(): PDO
{
    $host    = '127.0.0.1';
    $db      = 'solar_db';
    $user    = 'root';
    $pass    = '';
    $charset = 'utf8mb4';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=$charset",
            $user,
            $pass
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

        return $pdo;
    } catch (PDOException $e) {
        error_log('DB Connection Error: ' . $e->getMessage());
        die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
    }
}

/**
 * Compatibility wrapper for existing classes/modules expecting Database class
 */
class Database
{
    public function getConnection(): PDO
    {
        return getConnection();
    }
}