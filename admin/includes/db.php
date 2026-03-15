<?php
/**
 * Database Connection
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    static $failed = false;

    if ($failed) {
        throw new PDOException('Database connection failed');
    }

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $failed = true;
            // Re-throw so callers can handle gracefully
            throw $e;
        }
    }

    return $pdo;
}
