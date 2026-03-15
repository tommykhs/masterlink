<?php
/**
 * Shortener Management - Redirect and Embed Links Only
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/asset-picker.php';

requireLogin();

// Helper function to scan files recursively
function scanFilesRecursive($dir, $base = '') {
    $result = [];
    if (!is_dir($dir)) return $result;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'images') continue;
        $path = $dir . $item;
        $relativePath = $base ? $base . '/' . $item : $item;
        if (is_file($path)) {
            $result[] = $relativePath;
        } elseif (is_dir($path)) {
            $result = array_merge($result, scanFilesRecursive($path . '/', $relativePath));
        }
    }
    return $result;
}

$pdo = getDB();
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'update_order') {
        $orders = json_decode($_POST['orders'], true);
        $stmt = $pdo->prepare("UPDATE bookmarks SET sort_order = ? WHERE id = ?");
        foreach ($orders as $order) {
            $stmt->execute([$order['sort_order'], $order['id']]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle_visibility') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE bookmarks SET is_visible = NOT is_visible WHERE id = ?")->execute([$id]);
        $stmt = $pdo->prepare("SELECT is_visible FROM bookmarks WHERE id = ?");
        $stmt->execute([$id]);
        $visible = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'is_visible' => (bool)$visible]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM bookmarks WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'update_icon') {
        $id = (int)$_POST['id'];
        $iconType = $_POST['icon_type'];
        $iconValue = trim($_POST['icon_value']);
        $pdo->prepare("UPDATE bookmarks SET icon_type = ?, icon_value = ? WHERE id = ?")->execute([$iconType, $iconValue, $id]);
        echo json_encode(['success' => true, 'icon_type' => $iconType, 'icon_value' => $iconValue]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $linkType = $_POST['link_type'];

        // Generate slug from name or use provided slug
        $slug = slugify($_POST['slug'] ?: $_POST['name']);

        // Handle file upload if provided
        $filePath = trim($_POST['file_path'] ?? '');
        if ($linkType === 'file' && !empty($_FILES['file_upload']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            $subfolder = trim($_POST['file_subfolder'] ?? '');
            if ($subfolder) {
                $subfolder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $subfolder);
                $uploadDir .= rtrim($subfolder, '/') . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
            }
            $filename = basename($_FILES['file_upload']['name']);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $targetPath)) {
                $filePath = $subfolder ? $subfolder . '/' . $filename : $filename;
            }
        }

        // Handle password
        $password = null;
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } elseif (!empty($_POST['keep_password']) && $action === 'update') {
            // Keep existing password
            $existing = getBookmarkById($id);
            $password = $existing['password'] ?? null;
        }

        $data = [
            'category_id' => $_POST['category_id'] ?: null,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description']),
            'link_type' => $linkType,
            'target_url' => $linkType === 'file' ? '' : trim($_POST['target_url']),
            'icon_type' => $_POST['icon_type'],
            'icon_value' => trim($_POST['icon_value']),
            'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
            'is_featured' => 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'file_path' => $linkType === 'file' ? $filePath : null,
            'password' => $password,
        ];

        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO bookmarks (category_id, name, slug, description, link_type, target_url, icon_type, icon_value, is_visible, is_featured, sort_order, file_path, password)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_values($data));
                $message = 'Short link created successfully!';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE bookmarks SET category_id=?, name=?, slug=?, description=?, link_type=?, target_url=?, icon_type=?, icon_value=?, is_visible=?, is_featured=?, sort_order=?, file_path=?, password=?
                    WHERE id=?
                ");
                $stmt->execute([...array_values($data), $id]);
                $message = 'Short link updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . ($e->getCode() === '23000' ? 'Slug already exists. Please use a different slug.' : $e->getMessage());
        }
    }
}

// Get data
$categories = getCategories(); // Gets all categories (including hidden) for admin dropdown
$defaultCategoryId = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === 'shortener') {
        $defaultCategoryId = $cat['id'];
        break;
    }
}
$filter = $_GET['filter'] ?? 'all';

// Pagination settings
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 20;
$offset = ($page - 1) * $perPage;

// Only show redirect, embed, and file links
$filterSql = " WHERE t.link_type IN ('redirect', 'embed', 'file')";
if ($filter === 'visible') {
    $filterSql .= ' AND t.is_visible = 1';
} elseif ($filter === 'hidden') {
    $filterSql .= ' AND t.is_visible = 0';
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM bookmarks t" . $filterSql;
$totalItems = (int)$pdo->query($countSql)->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Get tools (all in sort mode, paginated otherwise)
$sortMode = isset($_GET['sort']);
if ($sortMode) {
    $tools = $pdo->query("SELECT t.*, c.name as category_name, c.is_visible as category_visible FROM bookmarks t LEFT JOIN categories c ON t.category_id = c.id" . $filterSql . " ORDER BY t.sort_order, t.name")->fetchAll();
} else {
    $tools = $pdo->query("SELECT t.*, c.name as category_name, c.is_visible as category_visible FROM bookmarks t LEFT JOIN categories c ON t.category_id = c.id" . $filterSql . " ORDER BY t.sort_order, t.name LIMIT $perPage OFFSET $offset")->fetchAll();
}

// Get counts for filter tabs (only redirect/embed/file links)
$counts = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(is_visible = 1) as visible,
    SUM(is_visible = 0) as hidden
    FROM bookmarks WHERE link_type IN ('redirect', 'embed', 'file')")->fetch();
$editTool = isset($_GET['edit']) ? getToolById((int)$_GET['edit']) : null;
$createMode = isset($_GET['create']);

// Prefill values from Files page "Create Short Link" button
$prefillFile = $_GET['file'] ?? '';
$prefillName = $_GET['name'] ?? '';
$prefillSlug = $_GET['slug'] ?? '';
$prefillIsFile = !empty($prefillFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Shortener | <?= htmlspecialchars($siteTitle) ?> Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1><?= $editTool ? 'Edit Short Link' : ($createMode ? 'Add New Short Link' : 'Shortener') ?></h1>
                <?php if (!$editTool && !$createMode): ?>
                    <div class="header-buttons">
                        <?php if ($sortMode): ?>
                            <a href="shortener.php" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="check" class="btn-icon-only"></i>
                                <span class="btn-text">Done Sorting</span>
                            </a>
                        <?php else: ?>
                            <a href="?sort=1" class="btn btn-icon-mobile">
                                <i data-lucide="grip-vertical"></i>
                                <span class="btn-text">Sort Order</span>
                            </a>
                            <a href="?create=1" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="plus"></i>
                                <span class="btn-text">Add Short Link</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($editTool): ?>
                <!-- Edit Form -->
                <div class="card">
                    <form method="POST" id="editForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $editTool['id'] ?>">
                        <input type="hidden" name="sort_order" value="<?= $editTool['sort_order'] ?>">

                        <div class="form-header">
                            <h2>Short Link Details</h2>
                            <div class="visibility-toggle-wrap" id="editVisibleWrap">
                                <span>Visible</span>
                                <input type="hidden" name="is_visible" id="editVisibleInput" value="<?= $editTool['is_visible'] ? '1' : '0' ?>">
                                <button type="button"
                                    class="btn-visibility <?= $editTool['is_visible'] ? 'visible' : 'hidden' ?>"
                                    id="editVisibleBtn"
                                    onclick="toggleEditVisibility()"
                                    title="<?= $editTool['is_visible'] ? 'Visible - click to hide' : 'Hidden - click to show' ?>">
                                    <i data-lucide="<?= $editTool['is_visible'] ? 'eye' : 'eye-off' ?>"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($editTool['name']) ?>" required oninput="updateEditSlug()">
                            </div>
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" onchange="handleEditCategoryChange()">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-visible="<?= $cat['is_visible'] ?>" <?= $editTool['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?><?= !$cat['is_visible'] ? ' (hidden)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" id="description" name="description" value="<?= htmlspecialchars($editTool['description']) ?>" placeholder="Short description shown on card">
                            </div>
                            <div class="form-group">
                                <label>Icon</label>
                                <?php renderAssetIconTrigger('edit_icon', $editTool['icon_type'], $editTool['icon_value']); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Link Type *</label>
                            <div class="link-type-row">
                                <label class="link-type-btn <?= $editTool['link_type'] === 'url' ? 'selected' : '' ?>" onclick="selectEditLinkType('url')">
                                    <input type="radio" name="link_type" value="url" <?= $editTool['link_type'] === 'url' ? 'checked' : '' ?>>
                                    <i data-lucide="square-arrow-out-up-right"></i><span>URL</span>
                                </label>
                                <label class="link-type-btn <?= $editTool['link_type'] === 'redirect' ? 'selected' : '' ?>" onclick="selectEditLinkType('redirect')">
                                    <input type="radio" name="link_type" value="redirect" <?= $editTool['link_type'] === 'redirect' ? 'checked' : '' ?>>
                                    <i data-lucide="corner-up-right"></i><span>Redirect</span>
                                </label>
                                <label class="link-type-btn <?= $editTool['link_type'] === 'embed' ? 'selected' : '' ?>" onclick="selectEditLinkType('embed')">
                                    <input type="radio" name="link_type" value="embed" <?= $editTool['link_type'] === 'embed' ? 'checked' : '' ?>>
                                    <i data-lucide="app-window"></i><span>Embed</span>
                                </label>
                                <label class="link-type-btn <?= $editTool['link_type'] === 'file' ? 'selected' : '' ?>" onclick="selectEditLinkType('file')">
                                    <input type="radio" name="link_type" value="file" <?= $editTool['link_type'] === 'file' ? 'checked' : '' ?>>
                                    <i data-lucide="file"></i><span>File</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group slug-field <?= $editTool['link_type'] !== 'url' ? 'show' : '' ?>" id="editSlugField">
                            <label for="slug">URL Path *</label>
                            <div class="slug-input-wrapper">
                                <span class="slug-prefix"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/</span>
                                <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($editTool['slug']) ?>" placeholder="my-tool">
                                <div class="slug-actions">
                                    <button type="button" onclick="copySlugUrl('edit')" title="Copy URL">
                                        <i data-lucide="copy"></i>
                                    </button>
                                    <button type="button" onclick="openSlugUrl('edit')" title="Open in new tab">
                                        <i data-lucide="external-link"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="editTargetUrlField" <?= $editTool['link_type'] === 'file' ? 'style="display:none;"' : '' ?>>
                            <label for="target_url" id="editTargetLabel"><?= $editTool['link_type'] === 'embed' ? 'URL to Embed' : 'Redirect To' ?> *</label>
                            <input type="text" id="target_url" name="target_url" value="<?= htmlspecialchars($editTool['target_url']) ?>" <?= $editTool['link_type'] !== 'file' ? 'required' : '' ?> placeholder="https://example.com">
                            <small class="text-muted" id="editTargetHelp" style="margin-top:0.25rem;">
                                <?= $editTool['link_type'] === 'embed' ? 'External URL to show in iframe (must allow embedding)' : 'URL to redirect visitors to (301 permanent redirect)' ?>
                            </small>
                        </div>

                        <div class="form-group" id="editFileField" <?= $editTool['link_type'] !== 'file' ? 'style="display:none;"' : '' ?>>
                            <label>Select File</label>
                            <?php renderAssetFileTrigger('edit_file_path', $editTool['file_path'] ?? ''); ?>
                        </div>

                        <div class="form-group" id="editPasswordField" <?= $editTool['link_type'] !== 'file' ? 'style="display:none;"' : '' ?>>
                            <label for="edit_password">Password Protection</label>
                            <?php if (!empty($editTool['password'])): ?>
                            <div style="margin-bottom:0.5rem;">
                                <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;cursor:pointer;">
                                    <input type="checkbox" name="keep_password" value="1" checked>
                                    <span style="font-size:0.875rem;">Keep current password</span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <input type="password" id="edit_password" name="password" placeholder="<?= !empty($editTool['password']) ? 'Enter new password to change' : 'Leave empty for public access' ?>">
                            <small class="text-muted" style="margin-top:0.25rem;">Set password to protect this file</small>
                        </div>

                        <div class="form-actions">
                            <a href="shortener.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                <script>
                    function selectEditLinkType(type) {
                        document.querySelectorAll('#editForm .link-type-btn').forEach(c => c.classList.remove('selected'));
                        document.querySelector(`#editForm input[value="${type}"]`).closest('.link-type-btn').classList.add('selected');
                        document.querySelector(`#editForm input[value="${type}"]`).checked = true;

                        const slugField = document.getElementById('editSlugField');
                        const targetLabel = document.getElementById('editTargetLabel');
                        const targetInput = document.getElementById('target_url');
                        const targetHelp = document.getElementById('editTargetHelp');
                        const targetUrlField = document.getElementById('editTargetUrlField');
                        const fileField = document.getElementById('editFileField');
                        const passwordField = document.getElementById('editPasswordField');

                        if (type === 'url') {
                            slugField.classList.remove('show');
                            targetUrlField.style.display = 'block';
                            targetInput.setAttribute('required', 'required');
                            fileField.style.display = 'none';
                            passwordField.style.display = 'none';
                            targetLabel.textContent = 'Target URL *';
                            targetInput.placeholder = 'https://example.com';
                            targetHelp.textContent = 'URL to open when clicked on homepage';
                        } else if (type === 'file') {
                            slugField.classList.add('show');
                            targetUrlField.style.display = 'none';
                            targetInput.removeAttribute('required');
                            fileField.style.display = 'block';
                            passwordField.style.display = 'block';
                        } else {
                            slugField.classList.add('show');
                            targetUrlField.style.display = 'block';
                            targetInput.setAttribute('required', 'required');
                            fileField.style.display = 'none';
                            passwordField.style.display = 'none';
                            if (type === 'embed') {
                                targetLabel.textContent = 'URL to Embed *';
                                targetInput.placeholder = 'https://example.com/page';
                                targetHelp.textContent = 'External URL to show in iframe (must allow embedding)';
                            } else {
                                targetLabel.textContent = 'Redirect To *';
                                targetInput.placeholder = 'https://example.com';
                                targetHelp.textContent = 'URL to redirect visitors to (301 permanent redirect)';
                            }
                        }
                    }

                    function updateEditSlug() {
                        const nameInput = document.getElementById('name');
                        const slugInput = document.getElementById('slug');
                        // Only auto-update if slug is empty or matches a slugified version of previous name
                        if (!slugInput.dataset.manual) {
                            slugInput.value = nameInput.value.toLowerCase()
                                .replace(/[^a-z0-9]+/g, '-')
                                .replace(/^-|-$/g, '');
                        }
                    }

                    // Mark slug as manually edited
                    document.getElementById('slug').addEventListener('input', function() {
                        this.dataset.manual = 'true';
                    });

                    // Toggle visibility button
                    function toggleEditVisibility() {
                        const btn = document.getElementById('editVisibleBtn');
                        const input = document.getElementById('editVisibleInput');
                        const wrap = document.getElementById('editVisibleWrap');

                        // Don't toggle if category is hidden
                        if (wrap.classList.contains('disabled')) return;

                        const isCurrentlyVisible = input.value === '1';
                        if (isCurrentlyVisible) {
                            input.value = '0';
                            btn.classList.remove('visible');
                            btn.classList.add('hidden');
                            btn.title = 'Hidden - click to show';
                            btn.innerHTML = '<i data-lucide="eye-off"></i>';
                        } else {
                            input.value = '1';
                            btn.classList.remove('hidden');
                            btn.classList.add('visible');
                            btn.title = 'Visible - click to hide';
                            btn.innerHTML = '<i data-lucide="eye"></i>';
                        }
                        lucide.createIcons();
                    }

                    // Handle category change for visibility
                    function handleEditCategoryChange() {
                        const select = document.getElementById('category_id');
                        const selectedOption = select.options[select.selectedIndex];
                        const isCategoryVisible = selectedOption.dataset.visible === '1';
                        const wrap = document.getElementById('editVisibleWrap');
                        const btn = document.getElementById('editVisibleBtn');
                        const input = document.getElementById('editVisibleInput');

                        if (!isCategoryVisible) {
                            wrap.classList.add('disabled');
                            btn.classList.add('disabled');
                            // Set to hidden when category is hidden
                            input.value = '0';
                            btn.classList.remove('visible');
                            btn.classList.add('hidden');
                            btn.title = 'Category is hidden';
                            btn.innerHTML = '<i data-lucide="eye-off"></i>';
                        } else {
                            wrap.classList.remove('disabled');
                            btn.classList.remove('disabled');
                        }
                        lucide.createIcons();
                    }

                    // Initialize
                    handleEditCategoryChange();
                </script>

            <?php elseif ($createMode): ?>
                <!-- Create Form (inline, not modal) -->
                <div class="card">
                    <form method="POST" id="createForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">

                        <div class="form-header">
                            <h2>Short Link Details</h2>
                            <div class="visibility-toggle-wrap" id="newVisibleWrap">
                                <span>Visible</span>
                                <input type="hidden" name="is_visible" id="newVisibleInput" value="1">
                                <button type="button"
                                    class="btn-visibility visible"
                                    id="newVisibleBtn"
                                    onclick="toggleNewVisibility()"
                                    title="Visible - click to hide">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_name">Name *</label>
                                <input type="text" id="new_name" name="name" required oninput="updateNewSlug()" value="<?= htmlspecialchars($prefillName) ?>">
                            </div>
                            <div class="form-group">
                                <label for="new_category_id">Category</label>
                                <select id="new_category_id" name="category_id" onchange="handleNewCategoryChange()">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-visible="<?= $cat['is_visible'] ?>" <?= $cat['id'] == $defaultCategoryId ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?><?= !$cat['is_visible'] ? ' (hidden)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_description">Description</label>
                                <input type="text" id="new_description" name="description" placeholder="Short description shown on card">
                            </div>
                            <div class="form-group">
                                <label>Icon</label>
                                <?php renderAssetIconTrigger('new_icon', 'library', 'lucide:scissors'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Link Type *</label>
                            <div class="link-type-row">
                                <label class="link-type-btn <?= !$prefillIsFile ? 'selected' : '' ?>" onclick="selectNewLinkType('redirect')">
                                    <input type="radio" name="link_type" value="redirect" <?= !$prefillIsFile ? 'checked' : '' ?>>
                                    <i data-lucide="corner-up-right"></i><span>Redirect</span>
                                </label>
                                <label class="link-type-btn" onclick="selectNewLinkType('embed')">
                                    <input type="radio" name="link_type" value="embed">
                                    <i data-lucide="app-window"></i><span>Embed</span>
                                </label>
                                <label class="link-type-btn <?= $prefillIsFile ? 'selected' : '' ?>" onclick="selectNewLinkType('file')">
                                    <input type="radio" name="link_type" value="file" <?= $prefillIsFile ? 'checked' : '' ?>>
                                    <i data-lucide="file"></i><span>File</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="newSlugField">
                            <label for="new_slug">URL Path *</label>
                            <div class="slug-input-wrapper">
                                <span class="slug-prefix"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/</span>
                                <input type="text" id="new_slug" name="slug" placeholder="my-tool" value="<?= htmlspecialchars($prefillSlug) ?>">
                                <div class="slug-actions">
                                    <button type="button" onclick="copySlugUrl('new')" title="Copy URL">
                                        <i data-lucide="copy"></i>
                                    </button>
                                    <button type="button" onclick="openSlugUrl('new')" title="Open in new tab">
                                        <i data-lucide="external-link"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="newTargetUrlField" <?= $prefillIsFile ? 'style="display:none;"' : '' ?>>
                            <label for="new_target_url" id="newTargetLabel">Redirect To *</label>
                            <input type="text" id="new_target_url" name="target_url" placeholder="https://example.com" <?= $prefillIsFile ? '' : 'required' ?>>
                            <small class="text-muted" id="newTargetHelp" style="margin-top:0.25rem;">URL to redirect visitors to (301 permanent redirect)</small>
                        </div>

                        <div class="form-group" id="newFileField" <?= $prefillIsFile ? '' : 'style="display:none;"' ?>>
                            <label>Select File</label>
                            <?php renderAssetFileTrigger('new_file_path', $prefillFile); ?>
                        </div>

                        <div class="form-group" id="newPasswordField" <?= $prefillIsFile ? '' : 'style="display:none;"' ?>>
                            <label for="new_password">Password Protection (optional)</label>
                            <input type="password" id="new_password" name="password" placeholder="Leave empty for public access">
                            <small class="text-muted" style="margin-top:0.25rem;">Set password to protect this file</small>
                        </div>

                        <div class="form-actions">
                            <a href="shortener.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Short Link</button>
                        </div>
                    </form>
                </div>

                <script>
                    function selectNewLinkType(type) {
                        document.querySelectorAll('#createForm .link-type-btn').forEach(c => c.classList.remove('selected'));
                        document.querySelector(`#createForm input[value="${type}"]`).closest('.link-type-btn').classList.add('selected');
                        document.querySelector(`#createForm input[value="${type}"]`).checked = true;

                        const targetLabel = document.getElementById('newTargetLabel');
                        const targetInput = document.getElementById('new_target_url');
                        const targetHelp = document.getElementById('newTargetHelp');
                        const targetUrlField = document.getElementById('newTargetUrlField');
                        const fileField = document.getElementById('newFileField');
                        const passwordField = document.getElementById('newPasswordField');

                        if (type === 'file') {
                            targetUrlField.style.display = 'none';
                            targetInput.removeAttribute('required');
                            fileField.style.display = 'block';
                            passwordField.style.display = 'block';
                        } else {
                            targetUrlField.style.display = 'block';
                            targetInput.setAttribute('required', 'required');
                            fileField.style.display = 'none';
                            passwordField.style.display = 'none';

                            if (type === 'embed') {
                                targetLabel.textContent = 'URL to Embed *';
                                targetInput.placeholder = 'https://example.com/page';
                                targetHelp.textContent = 'External URL to show in iframe (must allow embedding)';
                            } else {
                                targetLabel.textContent = 'Redirect To *';
                                targetInput.placeholder = 'https://example.com';
                                targetHelp.textContent = 'URL to redirect visitors to (301 permanent redirect)';
                            }
                        }
                    }

                    function updateNewSlug() {
                        const nameInput = document.getElementById('new_name');
                        const slugInput = document.getElementById('new_slug');
                        if (!slugInput.dataset.manual) {
                            slugInput.value = nameInput.value.toLowerCase()
                                .replace(/[^a-z0-9]+/g, '-')
                                .replace(/^-|-$/g, '');
                        }
                    }

                    // Mark slug as manually edited
                    document.getElementById('new_slug').addEventListener('input', function() {
                        this.dataset.manual = 'true';
                    });

                    // Toggle visibility button
                    function toggleNewVisibility() {
                        const btn = document.getElementById('newVisibleBtn');
                        const input = document.getElementById('newVisibleInput');
                        const wrap = document.getElementById('newVisibleWrap');

                        // Don't toggle if category is hidden
                        if (wrap.classList.contains('disabled')) return;

                        const isCurrentlyVisible = input.value === '1';
                        if (isCurrentlyVisible) {
                            input.value = '0';
                            btn.classList.remove('visible');
                            btn.classList.add('hidden');
                            btn.title = 'Hidden - click to show';
                            btn.innerHTML = '<i data-lucide="eye-off"></i>';
                        } else {
                            input.value = '1';
                            btn.classList.remove('hidden');
                            btn.classList.add('visible');
                            btn.title = 'Visible - click to hide';
                            btn.innerHTML = '<i data-lucide="eye"></i>';
                        }
                        lucide.createIcons();
                    }

                    // Handle category change for visibility
                    function handleNewCategoryChange() {
                        const select = document.getElementById('new_category_id');
                        const selectedOption = select.options[select.selectedIndex];
                        const isCategoryVisible = selectedOption.dataset.visible === '1';
                        const wrap = document.getElementById('newVisibleWrap');
                        const btn = document.getElementById('newVisibleBtn');
                        const input = document.getElementById('newVisibleInput');

                        if (!isCategoryVisible) {
                            wrap.classList.add('disabled');
                            btn.classList.add('disabled');
                            // Set to hidden when category is hidden
                            input.value = '0';
                            btn.classList.remove('visible');
                            btn.classList.add('hidden');
                            btn.title = 'Category is hidden';
                            btn.innerHTML = '<i data-lucide="eye-off"></i>';
                        } else {
                            wrap.classList.remove('disabled');
                            btn.classList.remove('disabled');
                            // Set to visible by default for visible categories
                            input.value = '1';
                            btn.classList.remove('hidden');
                            btn.classList.add('visible');
                            btn.title = 'Visible - click to hide';
                            btn.innerHTML = '<i data-lucide="eye"></i>';
                        }
                        lucide.createIcons();
                    }

                    // Initialize on page load
                    handleNewCategoryChange();

                    // If file type is prefilled, mark slug as manual so it doesn't get overwritten
                    <?php if ($prefillIsFile): ?>
                    document.getElementById('new_slug').dataset.manual = 'true';
                    <?php endif; ?>
                </script>

            <?php else: ?>
                <?php if ($sortMode): ?>
                    <div class="sort-banner">
                        <p><i data-lucide="info" style="width:16px;height:16px;vertical-align:middle;margin-right:0.5rem;"></i> Drag rows to reorder. Click "Done Sorting" when finished.</p>
                    </div>
                <?php else: ?>
                    <!-- Filter Row: Buttons + Search -->
                    <div class="filter-row">
                        <div class="filter-buttons">
                            <a href="shortener.php" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                                All <span class="count"><?= $counts['total'] ?></span>
                            </a>
                            <a href="shortener.php?filter=visible" class="filter-btn <?= $filter === 'visible' ? 'active' : '' ?>">
                                Visible <span class="count"><?= $counts['visible'] ?></span>
                            </a>
                            <a href="shortener.php?filter=hidden" class="filter-btn <?= $filter === 'hidden' ? 'active' : '' ?>">
                                Hidden <span class="count"><?= $counts['hidden'] ?></span>
                            </a>
                        </div>
                        <div class="search-box" id="searchBox">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" id="searchInput" placeholder="Search..." oninput="filterLinks(this.value)">
                            <button type="button" class="clear-search" onclick="clearSearch()">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Links List -->
                <div class="table-responsive">
                    <table class="table" id="linksTable">
                        <thead>
                            <tr>
                                <?php if ($sortMode): ?><th style="width:40px;"></th><?php endif; ?>
                                <th>Link</th>
                                <th>Type</th>
                                <th class="visibility-cell">Visible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sortableBody">
                            <?php foreach ($tools as $tool): ?>
                            <tr class="<?= $sortMode ? 'sortable-row' : '' ?>" data-id="<?= $tool['id'] ?>">
                                <?php if ($sortMode): ?>
                                <td class="drag-handle">
                                    <i data-lucide="grip-vertical"></i>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <span class="icon-preview clickable-icon" id="iconPreview_<?= $tool['id'] ?>" onclick="openQuickIconPicker(<?= $tool['id'] ?>, this)" title="Click to change icon">
                                            <?php if ($tool['icon_type'] === 'library'): ?>
                                                <i data-lucide="<?= str_replace('lucide:', '', $tool['icon_value']) ?>"></i>
                                            <?php else: ?>
                                                <img src="<?= htmlspecialchars($tool['icon_value']) ?>" alt="">
                                            <?php endif; ?>
                                        </span>
                                        <input type="hidden" id="link_icon_<?= $tool['id'] ?>_type" value="<?= htmlspecialchars($tool['icon_type']) ?>">
                                        <input type="hidden" id="link_icon_<?= $tool['id'] ?>_value" value="<?= htmlspecialchars($tool['icon_value']) ?>">
                                        <div>
                                            <strong><?= htmlspecialchars($tool['name']) ?></strong>
                                            <div class="slug-row">
                                                <a href="<?= url('/admin/qr.php') ?>?url=<?= urlencode($siteUrl . '/' . $tool['slug']) ?>" class="qr-btn" title="Generate QR Code"><i data-lucide="scan-qr-code"></i></a>
                                                <a href="<?= $siteUrl ?>/<?= htmlspecialchars($tool['slug']) ?>" target="_blank" class="slug-url"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/<?= htmlspecialchars($tool['slug']) ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $typeIcons = [
                                        'redirect' => 'corner-up-right',
                                        'embed' => 'app-window',
                                        'file' => 'file'
                                    ];
                                    $typeLabels = [
                                        'redirect' => 'Redirect',
                                        'embed' => 'Embed',
                                        'file' => 'File'
                                    ];
                                    $icon = $typeIcons[$tool['link_type']] ?? 'link';
                                    $label = $typeLabels[$tool['link_type']] ?? $tool['link_type'];
                                    $linkUrl = $siteUrl . '/' . $tool['slug'];
                                    ?>
                                    <a href="<?= htmlspecialchars($linkUrl) ?>" target="_blank" class="type-badge-link" title="Open in new tab">
                                        <i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span>
                                    </a>
                                </td>
                                <td class="visibility-cell">
                                    <?php $categoryHidden = !$tool['category_visible']; ?>
                                    <button type="button"
                                        class="btn-visibility <?= $tool['is_visible'] ? 'visible' : 'hidden' ?> <?= $categoryHidden ? 'disabled' : '' ?>"
                                        id="visBtn_<?= $tool['id'] ?>"
                                        onclick="toggleVisibility(<?= $tool['id'] ?>, this, <?= $categoryHidden ? 'true' : 'false' ?>)"
                                        title="<?= $categoryHidden ? 'Category is hidden' : ($tool['is_visible'] ? 'Visible - click to hide' : 'Hidden - click to show') ?>">
                                        <i data-lucide="<?= $tool['is_visible'] ? 'eye' : 'eye-off' ?>"></i>
                                    </button>
                                </td>
                                <td class="actions-cell">
                                    <a href="?edit=<?= $tool['id'] ?>" class="btn-icon-action" title="Edit">
                                        <i data-lucide="pencil"></i>
                                    </a>
                                    <button type="button" class="btn-icon-action btn-danger" title="Delete" onclick="deleteLink(<?= $tool['id'] ?>, '<?= htmlspecialchars($tool['name'], ENT_QUOTES) ?>')">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <?php if (!$sortMode && $totalPages > 0): ?>
                    <!-- Pagination -->
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?>
                        </div>
                        <div class="pagination-controls">
                            <?php
                            // Build base URL for pagination links
                            $baseParams = [];
                            if ($filter !== 'all') $baseParams['filter'] = $filter;
                            if ($perPage !== 20) $baseParams['per_page'] = $perPage;
                            $baseUrl = '?' . http_build_query($baseParams) . (empty($baseParams) ? '' : '&');
                            ?>
                            <?php if ($page > 1): ?>
                                <a href="<?= $baseUrl ?>page=1" class="pagination-btn" title="First">&laquo;</a>
                                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-btn" title="Previous">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            // Show page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($p = $startPage; $p <= $endPage; $p++):
                            ?>
                                <a href="<?= $baseUrl ?>page=<?= $p ?>" class="pagination-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-btn" title="Next">&rsaquo;</a>
                                <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="pagination-btn" title="Last">&raquo;</a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-per-page">
                            <select onchange="changePerPage(this.value)">
                                <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span>/page</span>
                        </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Copy slug URL to clipboard
        function copySlugUrl(formType) {
            const slugInput = formType === 'edit' ? document.getElementById('slug') : document.getElementById('new_slug');
            const slug = slugInput.value.trim();
            if (!slug) {
                alert('Please enter a URL path first');
                return;
            }
            const url = SITE_URL + '/' + slug;
            navigator.clipboard.writeText(url).then(() => {
                // Show feedback
                const btn = event.currentTarget;
                btn.classList.add('copied');
                setTimeout(() => btn.classList.remove('copied'), 1500);
            });
        }

        // Open slug URL in new tab
        function openSlugUrl(formType) {
            const slugInput = formType === 'edit' ? document.getElementById('slug') : document.getElementById('new_slug');
            const slug = slugInput.value.trim();
            if (!slug) {
                alert('Please enter a URL path first');
                return;
            }
            const url = SITE_URL + '/' + slug;
            window.open(url, '_blank');
        }

        // Search functionality
        function filterLinks(query) {
            const searchBox = document.getElementById('searchBox');
            const rows = document.querySelectorAll('#linksTable tbody tr');
            const lowerQuery = query.toLowerCase().trim();

            // Toggle has-value class for clear button
            if (lowerQuery) {
                searchBox.classList.add('has-value');
            } else {
                searchBox.classList.remove('has-value');
            }

            rows.forEach(row => {
                const name = row.querySelector('strong')?.textContent?.toLowerCase() || '';
                const url = row.querySelector('.slug-url')?.textContent?.toLowerCase() || '';

                if (!lowerQuery || name.includes(lowerQuery) || url.includes(lowerQuery)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            input.value = '';
            filterLinks('');
            input.focus();
        }

        // Toggle visibility via AJAX
        function toggleVisibility(id, btn, categoryHidden = false) {
            if (categoryHidden) return;
            fetch('shortener.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=toggle_visibility&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                // Update button appearance
                if (data.is_visible) {
                    btn.classList.remove('hidden');
                    btn.classList.add('visible');
                    btn.title = 'Visible - click to hide';
                    btn.innerHTML = '<i data-lucide="eye"></i>';
                } else {
                    btn.classList.remove('visible');
                    btn.classList.add('hidden');
                    btn.title = 'Hidden - click to show';
                    btn.innerHTML = '<i data-lucide="eye-off"></i>';
                }
                lucide.createIcons();
            });
        }

        // Delete link
        function deleteLink(id, name) {
            if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;

            fetch('shortener.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-id="${id}"]`).remove();
                }
            });
        }

        // Quick icon picker for table rows
        function openQuickIconPicker(linkId, element) {
            const typeInput = document.getElementById(`link_icon_${linkId}_type`);
            const valueInput = document.getElementById(`link_icon_${linkId}_value`);
            const current = {
                type: typeInput?.value || 'library',
                value: valueInput?.value || ''
            };

            openAssetPicker('icon', result => {
                // Save to server
                saveQuickIcon(linkId, result.type, result.value);

                // Update hidden inputs
                if (typeInput) typeInput.value = result.type;
                if (valueInput) valueInput.value = result.value;

                // Update the preview in the table
                if (result.type === 'library') {
                    const iconName = result.value.replace('lucide:', '');
                    element.innerHTML = `<i data-lucide="${iconName}"></i>`;
                } else {
                    element.innerHTML = `<img src="${result.value}" alt="">`;
                }
                lucide.createIcons();
            }, current, element);
        }

        function saveQuickIcon(linkId, iconType, iconValue) {
            fetch('shortener.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=update_icon&id=${linkId}&icon_type=${encodeURIComponent(iconType)}&icon_value=${encodeURIComponent(iconValue)}`
            });
        }

        // Pagination: change rows per page
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.delete('page'); // Reset to page 1
            window.location.href = url.toString();
        }

        <?php if ($sortMode): ?>
        // Drag and drop sorting with SortableJS (supports touch devices)
        document.addEventListener('DOMContentLoaded', function() {
            new Sortable(document.getElementById('sortableBody'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'dragging',
                onEnd: function() {
                    const rows = document.querySelectorAll('#sortableBody .sortable-row');
                    const orders = Array.from(rows).map((row, index) => ({
                        id: row.dataset.id,
                        sort_order: index
                    }));

                    fetch('shortener.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=update_order&orders=${encodeURIComponent(JSON.stringify(orders))}`
                    });
                }
            });
        });
        <?php endif; ?>
    </script>

    <?php
    // Asset picker styles and modal
    renderAssetPickerStyles();
    renderAssetPickerModal($popularIcons);
    ?>
</body>
</html>
