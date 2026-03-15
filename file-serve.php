<?php
/**
 * File Serve Handler
 * Routes uploads through PHP for password protection and MD rendering
 * Works both with and without database connection (for local dev)
 */

session_start();

$file = $_GET['file'] ?? '';

// Security: prevent empty file
if (empty($file)) {
    http_response_code(400);
    exit('Bad request');
}

$filePath = __DIR__ . '/uploads/' . $file;

// Security: prevent directory traversal
$realPath = realpath($filePath);
$uploadsDir = realpath(__DIR__ . '/uploads');

if (!$realPath || strpos($realPath, $uploadsDir) !== 0) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - File Not Found</h1></body></html>';
    exit;
}

// Check if file exists
if (!is_file($realPath)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - File Not Found</h1></body></html>';
    exit;
}

// Check if file has password protection via shortener link
// Try database connection - gracefully handle failure for local dev without DB
$bookmark = null;
$dbAvailable = false;
try {
    require_once __DIR__ . '/admin/includes/db.php';
    $pdo = getDB();
    $dbAvailable = true;
    $stmt = $pdo->prepare("SELECT id, name, password FROM bookmarks WHERE file_path = ? AND link_type = 'file'");
    $stmt->execute([$file]);
    $bookmark = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // No database connection - continue without password protection
    $bookmark = null;
}

// Password protection check
if ($bookmark && $bookmark['password']) {
    $sessionKey = 'unlocked_file_' . $bookmark['id'];
    $error = false;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (password_verify($_POST['password'] ?? '', $bookmark['password'])) {
            $_SESSION[$sessionKey] = true;
        } else {
            $error = true;
        }
    }

    // Show gate if not unlocked
    if (!isset($_SESSION[$sessionKey])) {
        require __DIR__ . '/templates/password-gate.php';
        exit;
    }
}

// Get file extension
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

// Handle based on file type
if ($ext === 'md') {
    // Render Markdown as styled HTML page
    require_once __DIR__ . '/includes/Parsedown.php';
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);

    $content = file_get_contents($realPath);
    $html = $parsedown->text($content);
    $pageTitle = $bookmark['name'] ?? pathinfo($file, PATHINFO_FILENAME);

    // Try to load full template with site settings, fallback to simple rendering
    if ($dbAvailable) {
        try {
            require_once __DIR__ . '/admin/includes/functions.php';
            require __DIR__ . '/templates/page.php';
            exit;
        } catch (Exception $e) {
            // Fallback below
        }
    }

    // Simple fallback template for local dev without DB
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="color-scheme" content="light dark">
    <style>
        :root { --bg-page: #f8fafc; --bg-card: #ffffff; --text-primary: #1a202c; --text-muted: #64748b; --border: #e2e8f0; --primary: #667eea; --code-bg: #f1f5f9; }
        @media (prefers-color-scheme: dark) { :root { --bg-page: #0f172a; --bg-card: #1e293b; --text-primary: #f1f5f9; --text-muted: #94a3b8; --border: #334155; --code-bg: #0f172a; } }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg-page); color: var(--text-primary); line-height: 1.7; }
        .page-container { max-width: 800px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        .page-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .page-header h1 { font-size: 2rem; font-weight: 700; }
        .page-content { background: var(--bg-card); border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        .page-content h1 { font-size: 1.875rem; margin: 1.5rem 0 1rem; font-weight: 700; }
        .page-content h2 { font-size: 1.5rem; margin: 1.5rem 0 0.75rem; font-weight: 600; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        .page-content h3 { font-size: 1.25rem; margin: 1.25rem 0 0.5rem; font-weight: 600; }
        .page-content p { margin: 0.75rem 0; }
        .page-content a { color: var(--primary); text-decoration: none; }
        .page-content a:hover { text-decoration: underline; }
        .page-content ul, .page-content ol { margin: 0.75rem 0; padding-left: 1.5rem; }
        .page-content li { margin: 0.25rem 0; }
        .page-content code { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.875em; background: var(--code-bg); padding: 0.2em 0.4em; border-radius: 4px; }
        .page-content pre { margin: 1rem 0; padding: 1rem; background: var(--code-bg); border-radius: 8px; overflow-x: auto; }
        .page-content pre code { background: none; padding: 0; }
        .page-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
        .page-content > *:first-child { margin-top: 0; }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="page-header">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </header>
        <article class="page-content">
            <?= $html ?>
        </article>
    </div>
</body>
</html>
    <?php
    exit;
}

if ($ext === 'html' || $ext === 'htm') {
    // Serve HTML directly
    header('Content-Type: text/html; charset=utf-8');
    readfile($realPath);
    exit;
}

// All other files - serve with proper MIME type
$mime = mime_content_type($realPath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: inline; filename="' . basename($file) . '"');

// Enable caching for static files
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

readfile($realPath);
