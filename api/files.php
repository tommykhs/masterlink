<?php
/**
 * Files API for File Picker
 * Lists files and folders from /uploads/ directory
 * Handles file uploads
 * Works without database connection for local dev
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Helper function for JSON response (doesn't require database)
function filesApiResponse($data) {
    echo json_encode($data);
    exit;
}

$uploadsDir = realpath(__DIR__ . '/../uploads');

// Ensure uploads directory exists
if (!$uploadsDir) {
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $uploadsDir = realpath($uploadsDir);
}

// Handle POST requests (file uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $folder = $_POST['folder'] ?? '';
        $folder = trim($folder, '/');
        $folder = preg_replace('/\.\./', '', $folder); // Security: prevent directory traversal

        $targetDir = $uploadsDir . ($folder ? '/' . $folder : '');

        // Create folder if doesn't exist
        if ($folder && !is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Validate target is within uploads
        if ($folder && (!is_dir($targetDir) || strpos(realpath($targetDir), $uploadsDir) !== 0)) {
            filesApiResponse(['success' => false, 'error' => 'Invalid folder']);
        }

        $uploadedFiles = [];

        if (!empty($_FILES['files']['name'][0])) {
            $fileCount = count($_FILES['files']['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = basename($_FILES['files']['name'][$i]);
                    // Sanitize filename
                    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                    $targetPath = $targetDir . '/' . $filename;

                    // Handle duplicate names
                    $counter = 1;
                    $pathInfo = pathinfo($filename);
                    while (file_exists($targetPath)) {
                        $filename = $pathInfo['filename'] . '_' . $counter . '.' . ($pathInfo['extension'] ?? '');
                        $targetPath = $targetDir . '/' . $filename;
                        $counter++;
                    }

                    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetPath)) {
                        $relativePath = $folder ? $folder . '/' . $filename : $filename;
                        $uploadedFiles[] = $relativePath;
                    }
                }
            }
        }

        if (count($uploadedFiles) > 0) {
            filesApiResponse(['success' => true, 'files' => $uploadedFiles]);
        } else {
            filesApiResponse(['success' => false, 'error' => 'No files uploaded']);
        }
    }

    filesApiResponse(['success' => false, 'error' => 'Invalid action']);
}

// Get folders only (for sidebar)
if (isset($_GET['folders'])) {
    $folders = [];
    $entries = scandir($uploadsDir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir($uploadsDir . '/' . $entry)) {
            $folders[] = $entry;
        }
    }
    sort($folders);
    filesApiResponse(['folders' => $folders]);
}

// Get files and folders in a path
$path = $_GET['path'] ?? '';
$path = trim($path, '/');
$path = preg_replace('/\.\./', '', $path); // Security: prevent directory traversal

$fullPath = $uploadsDir . ($path ? '/' . $path : '');

// Validate path is within uploads
if (!is_dir($fullPath) || strpos(realpath($fullPath), $uploadsDir) !== 0) {
    filesApiResponse(['items' => [], 'error' => 'Invalid path']);
}

$items = [];
$entries = scandir($fullPath);

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;

    $itemPath = $fullPath . '/' . $entry;
    $relativePath = $path ? $path . '/' . $entry : $entry;

    if (is_dir($itemPath)) {
        $items[] = [
            'name' => $entry,
            'path' => $relativePath,
            'type' => 'folder'
        ];
    } else {
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $items[] = [
            'name' => $entry,
            'path' => $relativePath,
            'type' => 'file',
            'ext' => $ext,
            'size' => filesize($itemPath)
        ];
    }
}

// Sort: folders first, then files, both alphabetically
usort($items, function($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'folder' ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

filesApiResponse(['items' => $items, 'path' => $path]);
