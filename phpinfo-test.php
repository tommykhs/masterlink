<?php
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br>";

try {
    require_once __DIR__ . '/admin/includes/config.php';
    echo "Config loaded OK<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "DB connected OK<br>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . (count($tables) ? implode(', ', $tables) : '(none)') . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}
