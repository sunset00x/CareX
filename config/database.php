<?php
/**
 * Database Connection Engine (PDO Singleton Pattern)
 * CarePlus Smart Hospital Management System
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $host = '127.0.0.1';
    private static $db   = 'smart_hospital';
    private static $user = 'root';
    private static $pass = '';
    private static $charset = 'utf8mb4';
    private static $pdo = null;

    private function __construct() {} // Block direct instantiation

    public static function getConnection() {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db . ";charset=" . self::$charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];

            try {
                self::$pdo = new PDO($dsn, self::$user, self::$pass, $options);
            } catch (\PDOException $e) {
                if (ENVIRONMENT === 'development') {
                    die("Database Connection Error: " . $e->getMessage());
                } else {
                    die("Database Service Unavailable. Please contact system administrator.");
                }
            }
        }
        return self::$pdo;
    }
}