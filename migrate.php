<?php
/**
 * Web-based Migration Runner
 *
 * Usage: https://ts.page.gd/migrate.php?key=YOUR_MIGRATE_KEY
 *
 * Runs pending SQL migrations from /migrations/ folder.
 */

require_once __DIR__ . '/admin/includes/config.php';
require_once __DIR__ . '/admin/includes/db.php';

// Auth check
$migrateKey = defined('MIGRATE_KEY') ? MIGRATE_KEY : null;
if (!$migrateKey || !isset($_GET['key']) || $_GET['key'] !== $migrateKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDB();

    // Create migrations tracking table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_migrations` (
        `filename` VARCHAR(255) NOT NULL,
        `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Get already-run migrations
    $ran = $pdo->query("SELECT filename FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);

    // Get all migration files, sorted
    $migrationDir = __DIR__ . '/migrations';
    $files = glob($migrationDir . '/*.sql');
    sort($files);

    $results = [];

    foreach ($files as $file) {
        $filename = basename($file);

        if (in_array($filename, $ran)) {
            $results[] = ['file' => $filename, 'status' => 'skipped'];
            continue;
        }

        $sql = file_get_contents($file);

        // Split by semicolons, filter out comments and empty lines
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($s) {
                $s = preg_replace('/^--.*$/m', '', $s);
                return trim($s) !== '';
            }
        );

        try {
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }

            $pdo->prepare("INSERT INTO _migrations (filename) VALUES (?)")->execute([$filename]);

            $results[] = ['file' => $filename, 'status' => 'OK'];
        } catch (PDOException $e) {
            $results[] = ['file' => $filename, 'status' => 'FAILED: ' . $e->getMessage()];
        }
    }

    echo json_encode([
        'success' => true,
        'migrations' => $results,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
