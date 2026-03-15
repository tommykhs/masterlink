<?php
/**
 * Files Management
 * Browse, upload and manage files with folder sidebar
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo = getDB();
$message = '';
$error = '';

$uploadsDir = realpath(__DIR__ . '/../uploads');
$currentFolder = $_GET['folder'] ?? '';

// Sanitize and validate path
$currentFolder = trim($currentFolder, '/');
$currentFolder = preg_replace('/\.\./', '', $currentFolder);
$fullPath = $uploadsDir . ($currentFolder ? '/' . $currentFolder : '');

// Ensure we're still within uploads directory
if (!is_dir($fullPath) || strpos(realpath($fullPath), $uploadsDir) !== 0) {
    $currentFolder = '';
    $fullPath = $uploadsDir;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && !empty($_FILES['files']['name'][0])) {
        $targetDir = $fullPath . '/';
        $uploaded = 0;
        $failed = 0;

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'md', 'html', 'htm', 'txt', 'css', 'js', 'json', 'xml', 'csv', 'zip'];

        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $filename = basename($name);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExt)) {
                $targetPath = $targetDir . $filename;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetPath)) {
                    $uploaded++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        }
        if ($uploaded > 0) $message = "$uploaded file(s) uploaded successfully!";
        if ($failed > 0) $error = "$failed file(s) failed (invalid type or permission error).";
    }

    if ($action === 'create_folder' && !empty($_POST['folder_name'])) {
        $folderName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['folder_name']);
        // Create in current folder (or root if at root)
        $newFolder = $fullPath . '/' . $folderName;
        if (!is_dir($newFolder)) {
            if (mkdir($newFolder, 0755, true)) {
                $message = 'Folder created successfully!';
            } else {
                $error = 'Failed to create folder.';
            }
        } else {
            $error = 'Folder already exists.';
        }
    }

    if ($action === 'delete_file' && !empty($_POST['filename'])) {
        $filename = basename($_POST['filename']);
        $filePath = $fullPath . '/' . $filename;
        if (is_file($filePath) && strpos(realpath($filePath), $uploadsDir) === 0) {
            if (unlink($filePath)) {
                $message = 'File deleted!';
            } else {
                $error = 'Delete failed.';
            }
        }
    }

    if ($action === 'delete_folder' && !empty($_POST['folder_name'])) {
        // Support both simple folder name (in current folder) and full path (from sidebar)
        $folderInput = $_POST['folder_name'];
        if (strpos($folderInput, '/') !== false) {
            // Full path provided
            $folderPath = $uploadsDir . '/' . $folderInput;
        } else {
            // Simple name - delete from current folder
            $folderPath = $fullPath . '/' . $folderInput;
        }
        if (is_dir($folderPath) && strpos(realpath($folderPath), $uploadsDir) === 0) {
            $files = array_diff(scandir($folderPath), ['.', '..']);
            if (empty($files)) {
                if (rmdir($folderPath)) {
                    $message = 'Folder deleted!';
                } else {
                    $error = 'Delete failed.';
                }
            } else {
                $error = 'Folder is not empty.';
            }
        }
    }

    if ($action === 'rename' && !empty($_POST['old_name']) && !empty($_POST['new_name'])) {
        $oldName = basename($_POST['old_name']);
        $newName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['new_name']);
        $oldPath = $fullPath . '/' . $oldName;
        $newPath = $fullPath . '/' . $newName;

        if (file_exists($oldPath) && strpos(realpath($oldPath), $uploadsDir) === 0) {
            if (!file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    $message = 'Renamed successfully!';
                } else {
                    $error = 'Rename failed.';
                }
            } else {
                $error = 'A file with that name already exists.';
            }
        }
    }

    // Rename folder with full path (from sidebar)
    if ($action === 'rename_folder' && !empty($_POST['folder_path']) && !empty($_POST['new_name'])) {
        $folderPath = $_POST['folder_path'];
        $newName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['new_name']);
        $oldFullPath = $uploadsDir . '/' . $folderPath;
        $parentDir = dirname($oldFullPath);
        $newFullPath = $parentDir . '/' . $newName;

        if (is_dir($oldFullPath) && strpos(realpath($oldFullPath), $uploadsDir) === 0) {
            if (!file_exists($newFullPath)) {
                if (rename($oldFullPath, $newFullPath)) {
                    $message = 'Folder renamed successfully!';
                    // Update current folder path if we renamed a parent folder
                    if (strpos($currentFolder, $folderPath) === 0) {
                        $newCurrentFolder = str_replace($folderPath, dirname($folderPath) . '/' . $newName, $currentFolder);
                        $newCurrentFolder = ltrim($newCurrentFolder, '/');
                        header('Location: ?folder=' . urlencode($newCurrentFolder));
                        exit;
                    }
                } else {
                    $error = 'Rename failed.';
                }
            } else {
                $error = 'A folder with that name already exists.';
            }
        }
    }
}

// Recursive function to build folder tree
function buildFolderTree($dir, $basePath = '', $excludeFolders = []) {
    $tree = [];
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if ($basePath === '' && in_array($entry, $excludeFolders)) continue;
        $entryPath = $dir . '/' . $entry;
        if (is_dir($entryPath)) {
            $relativePath = $basePath ? $basePath . '/' . $entry : $entry;
            $fileCount = count(array_filter(scandir($entryPath), function($f) use ($entryPath) {
                return $f !== '.' && $f !== '..' && is_file($entryPath . '/' . $f);
            }));
            $children = buildFolderTree($entryPath, $relativePath, []);
            $tree[] = [
                'name' => $entry,
                'path' => $relativePath,
                'count' => $fileCount,
                'children' => $children,
                'hasChildren' => !empty($children)
            ];
        }
    }
    usort($tree, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $tree;
}

// Build full folder tree for sidebar
$folderTree = buildFolderTree($uploadsDir);

// Also keep flat list for mobile dropdown (backwards compatibility)
$folders = [];
function flattenFolderTree($tree, &$result, $indent = 0) {
    foreach ($tree as $folder) {
        $folder['indent'] = $indent;
        $result[] = $folder;
        if (!empty($folder['children'])) {
            flattenFolderTree($folder['children'], $result, $indent + 1);
        }
    }
}
flattenFolderTree($folderTree, $folders);

// Count files at root level
$rootFileCount = count(array_filter(scandir($uploadsDir), function($f) use ($uploadsDir) {
    return $f !== '.' && $f !== '..' && is_file($uploadsDir . '/' . $f);
}));

// Get files AND folders in current folder
$files = [];
$subfolders = [];
if (is_dir($fullPath)) {
    $entries = scandir($fullPath);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $itemPath = $fullPath . '/' . $entry;
        if (is_dir($itemPath)) {
            $subfolders[] = [
                'name' => $entry,
                'path' => $currentFolder ? $currentFolder . '/' . $entry : $entry,
                'modified' => filemtime($itemPath),
                'type' => 'folder'
            ];
        } elseif (is_file($itemPath)) {
            $files[] = [
                'name' => $entry,
                'path' => $currentFolder ? $currentFolder . '/' . $entry : $entry,
                'size' => filesize($itemPath),
                'modified' => filemtime($itemPath),
                'ext' => strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
                'type' => 'file'
            ];
        }
    }
}
usort($subfolders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
// Combine: folders first, then files
$allItems = array_merge($subfolders, $files);

// File type icons
$fileIcons = [
    'md' => 'file-text', 'html' => 'file-code', 'htm' => 'file-code',
    'pdf' => 'file-text', 'doc' => 'file-text', 'docx' => 'file-text', 'txt' => 'file-text',
    'css' => 'file-code', 'js' => 'file-code', 'json' => 'file-json', 'xml' => 'file-code',
    'csv' => 'file-spreadsheet', 'zip' => 'file-archive',
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image', 'svg' => 'image',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Files | <?= htmlspecialchars($siteTitle) ?> Admin</title>
    <style>
    /* Files Layout - matches Bookmarks */
    .files-layout { display: flex; gap: 1rem; }
    .folder-sidebar {
        width: 200px; flex-shrink: 0;
        background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;
        padding: 0.75rem 0; height: fit-content; position: sticky; top: calc(var(--header-height) + 1.5rem);
    }
    .folder-sidebar-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 0.75rem 0.5rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem;
    }
    .folder-sidebar-header h3 { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
    .folder-add-btn {
        display: flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border: none; background: transparent;
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .folder-add-btn:hover { background: var(--bg-card-hover); color: var(--primary); }
    .folder-add-btn i { width: 16px; height: 16px; }

    .folder-list { list-style: none; margin: 0; padding: 0; }
    .folder-item {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.5rem 0.75rem; cursor: pointer; transition: background 0.15s;
        border-left: 3px solid transparent; position: relative;
    }
    .folder-item:hover { background: var(--bg-card-hover); }
    .folder-item.active { background: var(--bg-card-hover); border-left-color: var(--primary); }
    .folder-item-icon {
        width: 24px; height: 24px; border-radius: 4px;
        display: flex; align-items: center; justify-content: center;
        color: var(--warning); flex-shrink: 0;
    }
    .folder-item-icon i { width: 16px; height: 16px; }
    .folder-item-icon.root-icon { color: var(--text-muted); }
    .folder-item-name { flex: 1; font-size: 0.8125rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .folder-item-count { font-size: 0.6875rem; color: var(--text-muted); background: var(--gray-100); padding: 0.125rem 0.375rem; border-radius: 10px; }
    .folder-item-actions { display: none; position: absolute; right: 0.5rem; gap: 0.125rem; }
    .folder-item:hover .folder-item-actions { display: flex; }
    .folder-item:hover .folder-item-count { display: none; }
    .folder-action-btn {
        display: flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border: none; background: var(--bg-card);
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .folder-action-btn:hover { background: var(--gray-200); color: var(--text-primary); }
    .folder-action-btn.btn-danger:hover { color: var(--danger); }
    .folder-action-btn i { width: 12px; height: 12px; }

    /* Nested folder tree */
    .folder-tree { list-style: none; margin: 0; padding: 0; }
    .folder-tree .folder-tree { padding-left: 0.75rem; display: none; }
    .folder-tree .folder-tree.expanded { display: block; }
    .folder-tree-item { position: relative; }
    .folder-tree-row {
        display: flex; align-items: center; gap: 0.25rem;
        padding: 0.375rem 0.5rem 0.375rem 0.75rem; cursor: pointer; transition: background 0.15s;
        border-left: 3px solid transparent;
    }
    .folder-tree-row:hover { background: var(--bg-card-hover); }
    .folder-tree-row.active { background: var(--bg-card-hover); border-left-color: var(--primary); }
    .folder-expand-btn {
        width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;
        border: none; background: transparent; color: var(--text-muted); cursor: pointer;
        border-radius: 3px; flex-shrink: 0; transition: all 0.15s;
    }
    .folder-expand-btn:hover { background: var(--gray-200); color: var(--text-primary); }
    .folder-expand-btn i { width: 12px; height: 12px; transition: transform 0.15s; }
    .folder-expand-btn.expanded i { transform: rotate(90deg); }
    .folder-expand-btn.no-children { visibility: hidden; }
    .folder-tree-icon { width: 16px; height: 16px; color: var(--warning); flex-shrink: 0; margin-left: 0.125rem; }
    .folder-tree-icon.root-icon { color: var(--text-muted); }
    .folder-tree-name { flex: 1; font-size: 0.8125rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .folder-tree-count { font-size: 0.625rem; color: var(--text-muted); background: var(--gray-100); padding: 0.0625rem 0.25rem; border-radius: 8px; }
    .folder-tree-actions { display: none; gap: 0.125rem; margin-left: auto; }
    .folder-tree-row:hover .folder-tree-actions { display: flex; }
    .folder-tree-row:hover .folder-tree-count { display: none; }

    .files-content { flex: 1; min-width: 0; }

    /* Files Table */
    .files-table {
        width: 100%; border-collapse: collapse;
        background: var(--bg-card); border-radius: 8px; overflow: hidden;
    }
    .files-table th, .files-table td {
        padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border);
    }
    .files-table th {
        background: var(--bg-secondary); font-weight: 600; font-size: 0.75rem;
        text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
    }
    .files-table tr:last-child td { border-bottom: none; }
    .files-table tr:hover td { background: var(--bg-secondary); }
    .file-name { display: flex; align-items: center; gap: 0.75rem; }
    .file-name i { width: 20px; height: 20px; color: var(--text-muted); flex-shrink: 0; }
    .file-name a { color: var(--text-primary); text-decoration: none; }
    .file-name a:hover { color: var(--primary); }
    .file-size, .file-date { color: var(--text-muted); font-size: 0.875rem; }
    .file-actions { display: flex; gap: 0.25rem; opacity: 0.5; transition: opacity 0.15s; }
    .files-table tr:hover .file-actions { opacity: 1; }

    .empty-folder { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-folder i { width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5; }

    /* Folder rows in content */
    .folder-row:hover td { background: var(--bg-card-hover); }
    .folder-row .file-name span { font-weight: 500; }

    /* Breadcrumb navigation */
    .breadcrumb { display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.75rem; font-size: 0.875rem; flex-wrap: wrap; }
    .breadcrumb a { color: var(--text-muted); text-decoration: none; }
    .breadcrumb a:hover { color: var(--primary); }
    .breadcrumb .separator { color: var(--text-muted); }
    .breadcrumb .current { color: var(--text-primary); font-weight: 500; }

    /* Mobile Dropdown */
    .folder-dropdown-mobile { display: none; margin-bottom: 0.75rem; }
    .folder-dropdown-row { display: flex; gap: 0.5rem; align-items: center; }
    .folder-dropdown-row select {
        flex: 1; height: 32px; padding: 0 0.75rem; border: 1px solid var(--border);
        border-radius: 6px; font-size: 0.8125rem; background: var(--bg-card); color: var(--text-primary);
    }
    .folder-dropdown-row .folder-mobile-actions { display: flex; gap: 0.25rem; }
    .folder-mobile-btn {
        display: flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border: 1px solid var(--border); background: var(--bg-card);
        color: var(--text-secondary); cursor: pointer; border-radius: 6px; transition: all 0.15s;
    }
    .folder-mobile-btn:hover { border-color: var(--primary); color: var(--primary); }
    .folder-mobile-btn i { width: 16px; height: 16px; }

    @media (max-width: 992px) {
        .folder-sidebar { display: none; }
        .folder-dropdown-mobile { display: block; }
        .files-layout { display: block; }
    }
    @media (max-width: 768px) {
        .files-table th:nth-child(3), .files-table td:nth-child(3) { display: none; }
    }
    @media (max-width: 480px) {
        .files-table th:nth-child(2), .files-table td:nth-child(2) { display: none; }
    }

    /* View Toggle */
    .view-toggle {
        display: flex; gap: 0.25rem; background: var(--bg-secondary);
        padding: 0.25rem; border-radius: 6px; border: 1px solid var(--border);
    }
    .view-toggle-btn {
        display: flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border: none; background: transparent;
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .view-toggle-btn:hover { color: var(--text-primary); }
    .view-toggle-btn.active { background: var(--bg-card); color: var(--primary); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .view-toggle-btn i { width: 16px; height: 16px; }

    /* Grid View */
    .files-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1rem; padding: 1rem; background: var(--bg-card); border-radius: 8px;
    }
    .file-grid-item {
        display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
        padding: 1rem 0.5rem; border-radius: 8px; cursor: pointer; position: relative;
        transition: background 0.15s; text-align: center;
    }
    .file-grid-item:hover { background: var(--bg-secondary); }
    .file-grid-item-icon {
        width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
        color: var(--text-muted); flex-shrink: 0;
    }
    .file-grid-item-icon.folder-icon { color: var(--warning); }
    .file-grid-item-icon i { width: 40px; height: 40px; }
    .file-grid-item-icon img {
        width: 48px; height: 48px; object-fit: cover; border-radius: 6px;
    }
    .file-grid-item-name {
        font-size: 0.75rem; color: var(--text-primary);
        max-width: 100%; overflow: hidden; text-overflow: ellipsis;
        white-space: nowrap; word-break: break-all;
    }
    .file-grid-item-size { font-size: 0.6875rem; color: var(--text-muted); }
    .file-grid-item-actions {
        position: absolute; top: 0.25rem; right: 0.25rem;
        display: none; gap: 0.125rem; background: var(--bg-card);
        padding: 0.25rem; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .file-grid-item:hover .file-grid-item-actions { display: flex; }
    .grid-action-btn {
        display: flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border: none; background: transparent;
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .grid-action-btn:hover { background: var(--gray-200); color: var(--text-primary); }
    .grid-action-btn.btn-danger:hover { color: var(--danger); }
    .grid-action-btn i { width: 12px; height: 12px; }
    .files-grid .empty-folder { grid-column: 1 / -1; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>Files<?= $currentFolder ? ' / ' . htmlspecialchars($currentFolder) : '' ?></h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <div class="view-toggle">
                        <button type="button" class="view-toggle-btn" id="gridViewBtn" onclick="setFilesView('grid')" title="Grid view">
                            <i data-lucide="grid-3x3"></i>
                        </button>
                        <button type="button" class="view-toggle-btn active" id="listViewBtn" onclick="setFilesView('list')" title="List view">
                            <i data-lucide="list"></i>
                        </button>
                    </div>
                    <button onclick="document.getElementById('uploadModal').showModal()" class="btn btn-primary btn-icon-mobile">
                        <i data-lucide="upload"></i>
                        <span class="btn-text">Upload</span>
                    </button>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Mobile Folder Dropdown -->
            <div class="folder-dropdown-mobile">
                <div class="folder-dropdown-row">
                    <select id="folderDropdownMobile" onchange="navigateFolder(this.value)">
                        <option value="" <?= $currentFolder === '' ? 'selected' : '' ?>>Home (<?= $rootFileCount ?>)</option>
                        <?php foreach ($folders as $folder): ?>
                        <option value="<?= htmlspecialchars($folder['path']) ?>" <?= $currentFolder === $folder['path'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', $folder['indent']) ?><?= htmlspecialchars($folder['name']) ?> (<?= $folder['count'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="folder-mobile-actions">
                        <button type="button" class="folder-mobile-btn" onclick="openFolderModal()" title="New Folder">
                            <i data-lucide="folder-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="files-layout">
                <!-- Folder Sidebar (Desktop) -->
                <aside class="folder-sidebar">
                    <div class="folder-sidebar-header">
                        <h3>Folders</h3>
                        <button type="button" class="folder-add-btn" onclick="openFolderModal()" title="New Folder">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                    <?php
                    // Recursive function to render folder tree
                    function renderFolderTree($tree, $currentFolder, $expandedPaths) {
                        if (empty($tree)) return;
                        echo '<ul class="folder-tree' . (!empty($expandedPaths) ? ' expanded' : '') . '">';
                        foreach ($tree as $folder) {
                            $isActive = $currentFolder === $folder['path'];
                            $isInPath = strpos($currentFolder . '/', $folder['path'] . '/') === 0;
                            $shouldExpand = $isInPath || $isActive;
                            ?>
                            <li class="folder-tree-item">
                                <div class="folder-tree-row <?= $isActive ? 'active' : '' ?>" data-path="<?= htmlspecialchars($folder['path']) ?>">
                                    <button type="button" class="folder-expand-btn <?= $folder['hasChildren'] ? ($shouldExpand ? 'expanded' : '') : 'no-children' ?>" onclick="event.stopPropagation(); toggleFolderTree(this)">
                                        <i data-lucide="chevron-right"></i>
                                    </button>
                                    <i data-lucide="folder" class="folder-tree-icon"></i>
                                    <span class="folder-tree-name" onclick="navigateFolder('<?= htmlspecialchars($folder['path']) ?>')"><?= htmlspecialchars($folder['name']) ?></span>
                                    <span class="folder-tree-count"><?= $folder['count'] ?></span>
                                    <div class="folder-tree-actions" onclick="event.stopPropagation()">
                                        <button type="button" class="folder-action-btn" onclick="renameFolderSidebar('<?= htmlspecialchars($folder['path']) ?>', '<?= htmlspecialchars($folder['name']) ?>')" title="Rename">
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <button type="button" class="folder-action-btn btn-danger" onclick="deleteFolderSidebar('<?= htmlspecialchars($folder['path']) ?>', '<?= htmlspecialchars($folder['name']) ?>')" title="Delete">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if ($folder['hasChildren']): ?>
                                    <?php renderFolderTree($folder['children'], $currentFolder, $shouldExpand); ?>
                                <?php endif; ?>
                            </li>
                            <?php
                        }
                        echo '</ul>';
                    }
                    ?>
                    <ul class="folder-tree expanded">
                        <li class="folder-tree-item">
                            <div class="folder-tree-row <?= $currentFolder === '' ? 'active' : '' ?>" onclick="navigateFolder('')">
                                <button type="button" class="folder-expand-btn no-children">
                                    <i data-lucide="chevron-right"></i>
                                </button>
                                <i data-lucide="home" class="folder-tree-icon root-icon"></i>
                                <span class="folder-tree-name">Home</span>
                                <span class="folder-tree-count"><?= $rootFileCount ?></span>
                            </div>
                        </li>
                    </ul>
                    <?php renderFolderTree($folderTree, $currentFolder, true); ?>
                </aside>

                <!-- Files Content -->
                <div class="files-content">
                    <?php if ($currentFolder): ?>
                    <nav class="breadcrumb">
                        <a href="files.php"><i data-lucide="home" style="width:14px;height:14px;"></i></a>
                        <span class="separator">/</span>
                        <?php
                        $pathParts = explode('/', $currentFolder);
                        $buildPath = '';
                        foreach ($pathParts as $i => $part):
                            $buildPath .= ($buildPath ? '/' : '') . $part;
                            $isLast = ($i === count($pathParts) - 1);
                        ?>
                            <?php if ($isLast): ?>
                                <span class="current"><?= htmlspecialchars($part) ?></span>
                            <?php else: ?>
                                <a href="?folder=<?= urlencode($buildPath) ?>"><?= htmlspecialchars($part) ?></a>
                                <span class="separator">/</span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                    <!-- List View -->
                    <table class="files-table" id="filesListView">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Modified</th>
                                <th style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allItems)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-folder">
                                            <i data-lucide="folder-open"></i>
                                            <p>No files in this folder</p>
                                            <button onclick="document.getElementById('uploadModal').showModal()" class="btn btn-primary" style="margin-top:1rem;">
                                                <i data-lucide="upload"></i> Upload Files
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allItems as $item): ?>
                                    <?php if ($item['type'] === 'folder'): ?>
                                    <tr class="folder-row" onclick="navigateFolder('<?= htmlspecialchars($item['path']) ?>')" style="cursor:pointer;">
                                        <td>
                                            <div class="file-name">
                                                <i data-lucide="folder" style="color:var(--warning);"></i>
                                                <span><?= htmlspecialchars($item['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="file-size">—</td>
                                        <td class="file-date"><?= date('M j, Y', $item['modified']) ?></td>
                                        <td>
                                            <div class="file-actions" onclick="event.stopPropagation()">
                                                <button onclick="renameFolder('<?= htmlspecialchars($item['name']) ?>')" class="btn-icon-action" title="Rename">
                                                    <i data-lucide="pencil"></i>
                                                </button>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete folder? (must be empty)')">
                                                    <input type="hidden" name="action" value="delete_folder">
                                                    <input type="hidden" name="folder_name" value="<?= htmlspecialchars($item['name']) ?>">
                                                    <button type="submit" class="btn-icon-action btn-danger" title="Delete">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <td>
                                            <div class="file-name">
                                                <i data-lucide="<?= $fileIcons[$item['ext']] ?? 'file' ?>"></i>
                                                <a href="<?= url('/uploads/' . $item['path']) ?>" target="_blank"><?= htmlspecialchars($item['name']) ?></a>
                                            </div>
                                        </td>
                                        <td class="file-size"><?= number_format($item['size'] / 1024, 1) ?> KB</td>
                                        <td class="file-date"><?= date('M j, Y', $item['modified']) ?></td>
                                        <td>
                                            <div class="file-actions">
                                                <button onclick="copyFileUrl('<?= htmlspecialchars($item['path']) ?>')" class="btn-icon-action" title="Copy URL">
                                                    <i data-lucide="copy"></i>
                                                </button>
                                                <button onclick="createShortLink('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')" class="btn-icon-action" title="Create Short Link">
                                                    <i data-lucide="scissors"></i>
                                                </button>
                                                <button onclick="renameFile('<?= htmlspecialchars($item['name']) ?>')" class="btn-icon-action" title="Rename">
                                                    <i data-lucide="pencil"></i>
                                                </button>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this file?')">
                                                    <input type="hidden" name="action" value="delete_file">
                                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($item['name']) ?>">
                                                    <button type="submit" class="btn-icon-action btn-danger" title="Delete">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Grid View -->
                    <div class="files-grid" id="filesGridView" style="display:none;">
                        <?php if (empty($allItems)): ?>
                            <div class="empty-folder">
                                <i data-lucide="folder-open"></i>
                                <p>No files in this folder</p>
                                <button onclick="document.getElementById('uploadModal').showModal()" class="btn btn-primary" style="margin-top:1rem;">
                                    <i data-lucide="upload"></i> Upload Files
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($allItems as $item): ?>
                                <?php if ($item['type'] === 'folder'): ?>
                                <div class="file-grid-item" onclick="navigateFolder('<?= htmlspecialchars($item['path']) ?>')">
                                    <div class="file-grid-item-icon folder-icon">
                                        <i data-lucide="folder"></i>
                                    </div>
                                    <div class="file-grid-item-name" title="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="file-grid-item-actions" onclick="event.stopPropagation()">
                                        <button onclick="renameFolder('<?= htmlspecialchars($item['name']) ?>')" class="grid-action-btn" title="Rename">
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete folder? (must be empty)')">
                                            <input type="hidden" name="action" value="delete_folder">
                                            <input type="hidden" name="folder_name" value="<?= htmlspecialchars($item['name']) ?>">
                                            <button type="submit" class="grid-action-btn btn-danger" title="Delete">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php else: ?>
                                <?php
                                    $isImage = in_array($item['ext'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                    $thumbUrl = $isImage ? url('/uploads/' . $item['path']) : null;
                                ?>
                                <div class="file-grid-item" onclick="window.open('<?= url('/uploads/' . $item['path']) ?>', '_blank')">
                                    <div class="file-grid-item-icon">
                                        <?php if ($thumbUrl): ?>
                                            <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                        <?php else: ?>
                                            <i data-lucide="<?= $fileIcons[$item['ext']] ?? 'file' ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-grid-item-name" title="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="file-grid-item-size"><?= number_format($item['size'] / 1024, 1) ?> KB</div>
                                    <div class="file-grid-item-actions" onclick="event.stopPropagation()">
                                        <button onclick="copyFileUrl('<?= htmlspecialchars($item['path']) ?>')" class="grid-action-btn" title="Copy URL">
                                            <i data-lucide="copy"></i>
                                        </button>
                                        <button onclick="createShortLink('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')" class="grid-action-btn" title="Create Short Link">
                                            <i data-lucide="scissors"></i>
                                        </button>
                                        <button onclick="renameFile('<?= htmlspecialchars($item['name']) ?>')" class="grid-action-btn" title="Rename">
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this file?')">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($item['name']) ?>">
                                            <button type="submit" class="grid-action-btn btn-danger" title="Delete">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upload Modal -->
            <dialog id="uploadModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Upload Files</h2>
                        <button onclick="document.getElementById('uploadModal').close()" class="btn-close">&times;</button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">

                        <div class="upload-dropzone" id="dropzone">
                            <i data-lucide="upload-cloud"></i>
                            <p>Drag & drop files here or click to browse</p>
                            <input type="file" name="files[]" id="fileInput" multiple required>
                        </div>

                        <p class="help-text">Upload to: /uploads/<?= htmlspecialchars($currentFolder) ?></p>

                        <div class="form-actions">
                            <button type="button" onclick="document.getElementById('uploadModal').close()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <!-- New Folder Modal -->
            <dialog id="folderModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>New Folder</h2>
                        <button onclick="document.getElementById('folderModal').close()" class="btn-close">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_folder">

                        <div class="form-group">
                            <label for="folder_name">Folder Name</label>
                            <input type="text" id="folder_name" name="folder_name" required placeholder="my-folder" pattern="[a-zA-Z0-9_\-]+">
                            <small class="text-muted">Create in: /uploads/<?= htmlspecialchars($currentFolder ?: '(root)') ?></small>
                        </div>

                        <div class="form-actions">
                            <button type="button" onclick="document.getElementById('folderModal').close()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Folder</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <!-- Rename Modal (for files/folders in current view) -->
            <dialog id="renameModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="renameModalTitle">Rename</h2>
                        <button onclick="document.getElementById('renameModal').close()" class="btn-close">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="old_name" id="rename_old_name">

                        <div class="form-group">
                            <label for="new_name">New Name</label>
                            <input type="text" id="new_name" name="new_name" required>
                        </div>

                        <div class="form-actions">
                            <button type="button" onclick="document.getElementById('renameModal').close()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Rename</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <!-- Rename Folder Modal (for sidebar with full path) -->
            <dialog id="renameFolderModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Rename Folder</h2>
                        <button onclick="document.getElementById('renameFolderModal').close()" class="btn-close">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="rename_folder">
                        <input type="hidden" name="folder_path" id="rename_folder_path">

                        <div class="form-group">
                            <label for="rename_folder_new_name">New Name</label>
                            <input type="text" id="rename_folder_new_name" name="new_name" required pattern="[a-zA-Z0-9_\-]+">
                            <small class="text-muted">Letters, numbers, hyphens and underscores only</small>
                        </div>

                        <div class="form-actions">
                            <button type="button" onclick="document.getElementById('renameFolderModal').close()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Rename</button>
                        </div>
                    </form>
                </div>
            </dialog>

            <!-- Hidden form for sidebar folder delete -->
            <form id="deleteFolderForm" method="POST" style="display:none;">
                <input type="hidden" name="action" value="delete_folder">
                <input type="hidden" name="folder_name" id="delete_folder_path">
            </form>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // View toggle - load from localStorage
        let filesView = localStorage.getItem('filesManagerView') || 'list';

        function setFilesView(view) {
            filesView = view;
            localStorage.setItem('filesManagerView', view);
            updateFilesView();
        }

        function updateFilesView() {
            const listView = document.getElementById('filesListView');
            const gridView = document.getElementById('filesGridView');
            const listBtn = document.getElementById('listViewBtn');
            const gridBtn = document.getElementById('gridViewBtn');

            if (filesView === 'grid') {
                listView.style.display = 'none';
                gridView.style.display = 'grid';
                listBtn.classList.remove('active');
                gridBtn.classList.add('active');
            } else {
                listView.style.display = 'table';
                gridView.style.display = 'none';
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
            lucide.createIcons();
        }

        // Initialize view on page load
        updateFilesView();

        function navigateFolder(folder) {
            window.location.href = folder ? '?folder=' + encodeURIComponent(folder) : 'files.php';
        }

        function openFolderModal() {
            document.getElementById('folderModal').showModal();
        }

        function copyFileUrl(path) {
            const fullUrl = window.location.origin + '/uploads/' + path;
            navigator.clipboard.writeText(fullUrl).then(() => {
                alert('URL copied to clipboard!');
            });
        }

        function createShortLink(filePath, fileName) {
            const slug = fileName.replace(/\.[^/.]+$/, '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
            window.location.href = 'shortener.php?create&file=' + encodeURIComponent(filePath) + '&name=' + encodeURIComponent(fileName) + '&slug=' + encodeURIComponent(slug);
        }

        function renameFile(name) {
            document.getElementById('renameModalTitle').textContent = 'Rename File';
            document.getElementById('rename_old_name').value = name;
            document.getElementById('new_name').value = name;
            document.getElementById('renameModal').showModal();
        }

        function renameFolder(name) {
            document.getElementById('renameModalTitle').textContent = 'Rename Folder';
            document.getElementById('rename_old_name').value = name;
            document.getElementById('new_name').value = name;
            document.getElementById('renameModal').showModal();
        }

        // Folder tree expand/collapse
        function toggleFolderTree(btn) {
            const item = btn.closest('.folder-tree-item');
            const subTree = item.querySelector(':scope > .folder-tree');
            if (subTree) {
                const isExpanded = subTree.classList.contains('expanded');
                subTree.classList.toggle('expanded');
                btn.classList.toggle('expanded');
            }
        }

        // Rename folder from sidebar (with full path support)
        function renameFolderSidebar(path, name) {
            document.getElementById('rename_folder_path').value = path;
            document.getElementById('rename_folder_new_name').value = name;
            document.getElementById('renameFolderModal').showModal();
        }

        // Delete folder from sidebar (with full path support)
        function deleteFolderSidebar(path, name) {
            if (confirm('Delete folder "' + name + '"? (must be empty)')) {
                document.getElementById('delete_folder_path').value = path;
                document.getElementById('deleteFolderForm').submit();
            }
        }

        // Drag and drop
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
        });
    </script>
</body>
</html>
