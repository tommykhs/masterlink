<?php
/**
 * Helper Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Branding Configuration
 * Update these values to change site-wide branding
 */
define('APP_VERSION', '2.0');
define('BRAND_NAME', 'Master Link');
define('BRAND_URL', 'https://github.com/mcailab/masterlink/');
define('BRAND_AUTHOR', 'Master Link');

/**
 * Get copyright HTML with link
 * Usage: <?= getCopyright() ?>
 */
function getCopyright(): string {
    $year = date('Y');
    return "&copy; {$year} <a href=\"" . BRAND_URL . "\" target=\"_blank\">" . BRAND_NAME . "</a>";
}

/**
 * Get plain copyright text (no link)
 * Usage: <?= getCopyrightText() ?>
 */
function getCopyrightText(): string {
    return "&copy; " . date('Y') . " " . BRAND_NAME;
}

/**
 * Generate a URL with the correct base path for subfolder installations
 * Example: url('/admin/') returns '/link/admin/' when BASE_PATH is '/link'
 */
function url(string $path = ''): string {
    $basePath = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
    // Ensure path starts with /
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }
    return $basePath . $path;
}

/**
 * Generate URL-friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'item';
}

/**
 * Generate short hash for file naming
 */
function shortHash(int $length = 6): string {
    return substr(bin2hex(random_bytes($length)), 0, $length);
}

/**
 * Generate unique filename: originalname_abc123.ext
 */
function generateFilename(string $originalName): string {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $slug = slugify($name);
    $hash = shortHash();
    return "{$slug}_{$hash}.{$ext}";
}

/**
 * Get file type (image or document)
 */
function getFileType(string $mimeType): string {
    $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    return in_array($mimeType, $imageTypes) ? 'image' : 'document';
}

/**
 * Handle file upload
 */
function handleUpload(array $file): ?array {
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($file['type'], $allowedTypes)) {
        return null;
    }

    $fileType = getFileType($file['type']);
    $subDir = $fileType === 'image' ? 'images' : 'docs';
    $filename = generateFilename($file['name']);
    $uploadDir = UPLOAD_DIR . $subDir . '/';
    $filePath = $uploadDir . $filename;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_type' => $fileType,
            'file_path' => "/uploads/{$subDir}/{$filename}",
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        ];
    }

    return null;
}

/**
 * Handle base64 image upload (for MCP/API)
 */
function handleBase64Upload(string $base64Data, string $filename): ?array {
    // Extract mime type and data
    if (preg_match('/^data:(\w+\/\w+);base64,/', $base64Data, $matches)) {
        $mimeType = $matches[1];
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
    } else {
        $mimeType = 'image/png'; // Default
    }

    $data = base64_decode($base64Data);
    if ($data === false) {
        return null;
    }

    $fileType = getFileType($mimeType);
    $subDir = $fileType === 'image' ? 'images' : 'docs';
    $newFilename = generateFilename($filename);
    $uploadDir = UPLOAD_DIR . $subDir . '/';
    $filePath = $uploadDir . $newFilename;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (file_put_contents($filePath, $data)) {
        return [
            'filename' => $newFilename,
            'original_name' => $filename,
            'file_type' => $fileType,
            'file_path' => "/uploads/{$subDir}/{$newFilename}",
            'file_size' => strlen($data),
            'mime_type' => $mimeType
        ];
    }

    return null;
}

/**
 * Save media to database
 */
function saveMedia(array $mediaData): int {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO media (filename, original_name, file_type, file_path, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $mediaData['filename'],
        $mediaData['original_name'],
        $mediaData['file_type'],
        $mediaData['file_path'],
        $mediaData['file_size'],
        $mediaData['mime_type']
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Delete media file and database record
 */
function deleteMedia(int $id): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT file_path FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();

    if ($media) {
        $fullPath = __DIR__ . '/../../' . ltrim($media['file_path'], '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
        return $stmt->execute([$id]);
    }

    return false;
}

/**
 * Get all categories
 */
function getCategories(bool $visibleOnly = false): array {
    $pdo = getDB();
    $sql = "SELECT * FROM categories";
    if ($visibleOnly) {
        $sql .= " WHERE is_visible = 1";
    }
    $sql .= " ORDER BY sort_order, name";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Get all contacts
 */
function getContacts(): array {
    $pdo = getDB();
    return $pdo->query("SELECT * FROM contacts ORDER BY sort_order, id")->fetchAll();
}

/**
 * Get all bookmarks with category info
 */
function getBookmarks(bool $visibleOnly = false, ?int $categoryId = null): array {
    $pdo = getDB();
    $sql = "SELECT t.*, c.name as category_name, c.slug as category_slug
            FROM bookmarks t
            LEFT JOIN categories c ON t.category_id = c.id";

    $conditions = [];
    $params = [];

    if ($visibleOnly) {
        $conditions[] = "t.is_visible = 1";
        $conditions[] = "(c.is_visible = 1 OR t.category_id IS NULL)"; // Show uncategorized bookmarks too
    }
    if ($categoryId !== null) {
        $conditions[] = "t.category_id = ?";
        $params[] = $categoryId;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY c.sort_order, t.sort_order, t.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Alias for backward compatibility
function getTools(bool $visibleOnly = false, ?int $categoryId = null): array {
    return getBookmarks($visibleOnly, $categoryId);
}

/**
 * Get bookmark by ID
 */
function getBookmarkById(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Alias for backward compatibility
function getToolById(int $id): ?array {
    return getBookmarkById($id);
}

/**
 * Get bookmark by slug
 */
function getBookmarkBySlug(string $slug): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

// Alias for backward compatibility
function getToolBySlug(string $slug): ?array {
    return getBookmarkBySlug($slug);
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * CSRF token generation
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token verification
 */
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the latest modification time of code files
 * Excludes: uploads, media, .git, node_modules, etc.
 * Returns timestamp in HKT (Asia/Hong_Kong)
 */
function getLastCodeUpdate(): array {
    $rootDir = realpath(__DIR__ . '/../../');
    $latestTime = 0;
    $latestFile = '';

    // Directories to scan for code files
    $scanDirs = [
        $rootDir,
        $rootDir . '/admin',
        $rootDir . '/admin/includes',
        $rootDir . '/api',
        $rootDir . '/mcp',
        $rootDir . '/templates',
        $rootDir . '/assets/css',
        $rootDir . '/assets/js',
    ];

    // Code file extensions to check
    $codeExtensions = ['php', 'css', 'js', 'json', 'html', 'htaccess'];

    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $dir . '/' . $file;
            if (!is_file($filePath)) continue;

            // Check extension
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($file === '.htaccess') $ext = 'htaccess';

            if (in_array($ext, $codeExtensions)) {
                $mtime = filemtime($filePath);
                if ($mtime > $latestTime) {
                    $latestTime = $mtime;
                    $latestFile = str_replace($rootDir . '/', '', $filePath);
                }
            }
        }
    }

    // Convert to HKT
    $hkt = new DateTimeZone('Asia/Hong_Kong');
    $dt = new DateTime('@' . $latestTime);
    $dt->setTimezone($hkt);

    return [
        'timestamp' => $latestTime,
        'datetime' => $dt->format('Y-m-d H:i:s'),
        'date' => $dt->format('Y-m-d'),
        'time' => $dt->format('H:i:s'),
        'relative' => getRelativeTime($latestTime),
        'file' => $latestFile,
        'timezone' => 'HKT'
    ];
}

/**
 * Get relative time string (e.g., "2 hours ago")
 */
function getRelativeTime(int $timestamp): string {
    $diff = time() - $timestamp;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';

    return date('M j', $timestamp);
}

/**
 * Default settings with fallback values
 */
function getDefaultSettings(): array {
    return [
        'site_title' => 'MCAI',
        'site_description' => 'AI Tools Directory',
        'site_logo' => 'https://masterconcept.ai/wp-content/uploads/2020/08/MCI_logo_original-1.png',
        'theme_mode' => 'auto',  // auto, light, dark, mc
    ];
}

/**
 * Get a setting value with fallback to default
 */
function getSetting(string $key, ?string $default = null): string {
    static $settings = null;

    // Load settings once (cached for request)
    if ($settings === null) {
        try {
            $pdo = getDB();
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }

    // Return setting value or default
    if (isset($settings[$key]) && $settings[$key] !== '') {
        return $settings[$key];
    }

    // Use provided default or fall back to system defaults
    if ($default !== null) {
        return $default;
    }

    $defaults = getDefaultSettings();
    return $defaults[$key] ?? '';
}
