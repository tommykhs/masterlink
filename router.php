<?php
/**
 * Dynamic URL Router
 * Handles redirect, embed, and file link types
 */

session_start();

require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/admin/includes/functions.php';

// Get path from query param (Apache) or REQUEST_URI (PHP built-in server)
$requestPath = $_GET['path'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip BASE_PATH from the beginning if present (for subfolder installations)
$basePath = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
if ($basePath && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$pathParts = explode('/', trim($requestPath, '/'));

// Handle PWA assets for embed bookmarks: {slug}/manifest.json, {slug}/sw.js
if (count($pathParts) === 2 && in_array($pathParts[1], ['manifest.json', 'sw.js'])) {
    $pwaBookmark = getBookmarkBySlug($pathParts[0]);
    if ($pwaBookmark && $pwaBookmark['link_type'] === 'embed' && !empty($pwaBookmark['is_pwa'])) {
        if ($pathParts[1] === 'manifest.json') {
            require __DIR__ . '/templates/pwa-manifest.php';
        } else {
            require __DIR__ . '/templates/pwa-sw.php';
        }
        exit;
    }
}

$slug = $pathParts[0] ?? '';

if (empty($slug)) {
    // No path, show homepage
    require __DIR__ . '/index.php';
    exit;
}

// Skip reserved paths (handled by Apache in production, needed for PHP built-in server)
$reservedPaths = ['admin', 'api', 'mcp', 'assets', 'uploads'];
if (in_array($slug, $reservedPaths)) {
    // Let PHP built-in server handle static files, or include directly
    $filePath = __DIR__ . '/' . $requestPath;
    if (is_file($filePath)) {
        return false; // Let built-in server handle it
    }
    // Try index.php in directory
    $indexPath = rtrim($filePath, '/') . '/index.php';
    if (is_file($indexPath)) {
        require $indexPath;
        exit;
    }
    // Not found
    http_response_code(404);
    require __DIR__ . '/templates/404.php';
    exit;
}

// Look up bookmark by slug (hidden links still work, just not shown on homepage)
$bookmark = getBookmarkBySlug($slug);

if (!$bookmark) {
    // Not found
    http_response_code(404);
    require __DIR__ . '/templates/404.php';
    exit;
}

// Handle based on link_type
switch ($bookmark['link_type']) {
    case 'redirect':
        // Permanent redirect
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $bookmark['target_url']);
        exit;

    case 'embed':
        // Ensure trailing slash for PWA relative URL resolution
        if (!empty($bookmark['is_pwa'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            if (substr($requestUri, -1) !== '/' && strpos($requestUri, '?') === false) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $requestUri . '/');
                exit;
            }
        }
        // Show in iframe with bookmark metadata
        $bookmarkName = htmlspecialchars($bookmark['name']);
        $bookmarkDescription = htmlspecialchars($bookmark['description'] ?? '');
        $bookmarkIconType = $bookmark['icon_type'];
        $bookmarkIconValue = $bookmark['icon_value'];
        $targetUrl = htmlspecialchars($bookmark['target_url']);
        $siteLogo = getSetting('site_logo');
        $isPwa = !empty($bookmark['is_pwa']);
        require __DIR__ . '/templates/embed.php';
        exit;

    case 'file':
        // Serve uploaded file with password protection and MD rendering
        $file = $bookmark['file_path'];
        $filePath = __DIR__ . '/uploads/' . $file;

        // Check file exists
        if (!$file || !is_file($filePath)) {
            http_response_code(404);
            require __DIR__ . '/templates/404.php';
            exit;
        }

        // Password protection check
        if ($bookmark['password']) {
            $sessionKey = 'unlocked_' . $bookmark['id'];
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
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Handle based on file type
        if ($ext === 'md') {
            // Render Markdown as styled HTML page
            require_once __DIR__ . '/includes/Parsedown.php';
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);

            $content = file_get_contents($filePath);
            $html = $parsedown->text($content);
            $pageTitle = $bookmark['name'] ?: pathinfo($file, PATHINFO_FILENAME);

            require __DIR__ . '/templates/page.php';
            exit;
        }

        if ($ext === 'html' || $ext === 'htm') {
            // Serve HTML directly
            header('Content-Type: text/html; charset=utf-8');
            readfile($filePath);
            exit;
        }

        // All other files - serve with proper MIME type
        $mime = mime_content_type($filePath);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;

    case 'url':
    default:
        // URL links are handled by frontend JavaScript, not router
        // If someone navigates directly, redirect to homepage
        header('Location: ' . url('/'));
        exit;
}
