<?php
/**
 * Bookmarks Management (with integrated Category Management)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/asset-picker.php';

requireLogin();

$pdo = getDB();
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // Bookmark AJAX actions
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

    // Category AJAX actions
    if ($action === 'cat_toggle_visibility') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE categories SET is_visible = NOT is_visible WHERE id = ?")->execute([$id]);
        $stmt = $pdo->prepare("SELECT is_visible FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $visible = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'is_visible' => (bool)$visible]);
        exit;
    }

    if ($action === 'cat_delete') {
        $id = (int)$_POST['id'];
        $count = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE category_id = ?");
        $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Remove all bookmarks first']);
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'cat_reorder') {
        $order = json_decode($_POST['order'], true);
        $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
        foreach ($order as $index => $id) {
            $stmt->execute([$index, $id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'cat_create') {
        $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM categories")->fetchColumn();
        $sortOrder = ($maxOrder ?? -1) + 1;
        $name = trim($_POST['name']);
        $slug = slugify($_POST['slug'] ?: $name);
        $iconType = $_POST['icon_type'] ?? 'library';
        $iconValue = trim($_POST['icon_value'] ?? 'lucide:folder');
        $isVisible = ($_POST['is_visible'] ?? '1') === '1' ? 1 : 0;

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon_type, icon_value, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $iconType, $iconValue, $sortOrder, $isVisible]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $newId, 'slug' => $slug]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getCode() === '23000' ? 'Slug already exists' : $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'cat_update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $slug = slugify($_POST['slug'] ?: $name);
        $iconType = $_POST['icon_type'] ?? 'library';
        $iconValue = trim($_POST['icon_value'] ?? 'lucide:folder');
        $isVisible = ($_POST['is_visible'] ?? '1') === '1' ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, icon_type=?, icon_value=?, is_visible=? WHERE id=?");
            $stmt->execute([$name, $slug, $iconType, $iconValue, $isVisible, $id]);
            echo json_encode(['success' => true, 'slug' => $slug]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getCode() === '23000' ? 'Slug already exists' : $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'cat_get') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        echo json_encode(['success' => true, 'category' => $cat]);
        exit;
    }

    if ($action === 'cat_update_icon') {
        $id = (int)$_POST['id'];
        $iconType = $_POST['icon_type'];
        $iconValue = trim($_POST['icon_value']);
        $pdo->prepare("UPDATE categories SET icon_type = ?, icon_value = ? WHERE id = ?")->execute([$iconType, $iconValue, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $linkType = $_POST['link_type'];

        // For URL links, generate slug from name but it won't be used
        // For other types, use provided slug or generate from name
        $slug = '';
        if ($linkType === 'url') {
            $slug = slugify($_POST['name']); // Still store a slug for DB uniqueness
        } else {
            $slug = slugify($_POST['slug'] ?: $_POST['name']);
        }

        // For file type, target_url is not required
        $targetUrl = trim($_POST['target_url'] ?? '');
        $filePath = ($linkType === 'file') ? trim($_POST['file_path'] ?? '') : null;

        $data = [
            'category_id' => $_POST['category_id'] ?: null,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description']),
            'link_type' => $linkType,
            'target_url' => $targetUrl,
            'file_path' => $filePath,
            'icon_type' => $_POST['icon_type'],
            'icon_value' => trim($_POST['icon_value']),
            'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
            'is_featured' => 0,
            'is_pwa' => ($linkType === 'embed' && isset($_POST['is_pwa'])) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];

        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO bookmarks (category_id, name, slug, description, link_type, target_url, file_path, icon_type, icon_value, is_visible, is_featured, is_pwa, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_values($data));
                $message = 'Link created successfully!';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE bookmarks SET category_id=?, name=?, slug=?, description=?, link_type=?, target_url=?, file_path=?, icon_type=?, icon_value=?, is_visible=?, is_featured=?, is_pwa=?, sort_order=?
                    WHERE id=?
                ");
                $stmt->execute([...array_values($data), $id]);
                $message = 'Link updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . ($e->getCode() === '23000' ? 'Slug already exists. Please use a different slug.' : $e->getMessage());
        }
    }
}

// Get data
$categories = getCategories(); // Gets all categories (including hidden) for admin dropdown

// Get category counts for sidebar
$categoriesWithCounts = $pdo->query("
    SELECT c.*,
           COUNT(t.id) as bookmark_count,
           SUM(CASE WHEN t.is_visible = 1 THEN 1 ELSE 0 END) as visible_count
    FROM categories c
    LEFT JOIN bookmarks t ON c.id = t.category_id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();
$uncategorizedCount = $pdo->query("SELECT COUNT(*) FROM bookmarks WHERE category_id IS NULL")->fetchColumn();
$totalBookmarkCount = $pdo->query("SELECT COUNT(*) FROM bookmarks")->fetchColumn();

// Get default category from the latest visible link
$latestVisibleLink = $pdo->query("SELECT category_id FROM bookmarks WHERE is_visible = 1 ORDER BY created_at DESC LIMIT 1")->fetch();
$defaultCategoryId = $latestVisibleLink ? $latestVisibleLink['category_id'] : null;

// Filter by visibility
$filter = $_GET['filter'] ?? 'all';
// Filter by category
$catFilter = $_GET['cat'] ?? 'all';

$filterSql = '';
$filterParams = [];
$conditions = [];

if ($filter === 'visible') {
    $conditions[] = 't.is_visible = 1';
} elseif ($filter === 'hidden') {
    $conditions[] = 't.is_visible = 0';
}

if ($catFilter !== 'all') {
    if ($catFilter === 'uncategorized') {
        $conditions[] = 't.category_id IS NULL';
    } else {
        $conditions[] = 't.category_id = ' . (int)$catFilter;
    }
}

if (!empty($conditions)) {
    $filterSql = ' WHERE ' . implode(' AND ', $conditions);
}

// Pagination settings
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 20;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM bookmarks t" . $filterSql;
$totalItems = (int)$pdo->query($countSql)->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));
$page = min($page, $totalPages); // Ensure page doesn't exceed total

// Get paginated tools
$sortMode = isset($_GET['sort']);
if ($sortMode) {
    // In sort mode, load all for drag-drop reordering
    $tools = $pdo->query("SELECT t.*, c.name as category_name, c.is_visible as category_visible FROM bookmarks t LEFT JOIN categories c ON t.category_id = c.id" . $filterSql . " ORDER BY t.sort_order, t.name")->fetchAll();
} else {
    // Normal mode with pagination
    $tools = $pdo->query("SELECT t.*, c.name as category_name, c.is_visible as category_visible FROM bookmarks t LEFT JOIN categories c ON t.category_id = c.id" . $filterSql . " ORDER BY t.sort_order, t.name LIMIT $perPage OFFSET $offset")->fetchAll();
}

// Get counts for filter tabs (filtered by category if applicable)
$countCatFilter = '';
if ($catFilter !== 'all') {
    if ($catFilter === 'uncategorized') {
        $countCatFilter = ' WHERE category_id IS NULL';
    } else {
        $countCatFilter = ' WHERE category_id = ' . (int)$catFilter;
    }
}
$counts = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(is_visible = 1) as visible,
    SUM(is_visible = 0) as hidden
    FROM bookmarks" . $countCatFilter)->fetch();
$editTool = isset($_GET['edit']) ? getToolById((int)$_GET['edit']) : null;
$createMode = isset($_GET['create']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Bookmarks | <?= htmlspecialchars($siteTitle) ?> Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
    /* Category Sidebar Styles */
    .bookmarks-layout { display: flex; gap: 1rem; }
    .category-sidebar {
        width: 200px; flex-shrink: 0;
        background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;
        padding: 0.75rem 0; height: fit-content; position: sticky; top: calc(var(--header-height) + 1.5rem);
    }
    .category-sidebar-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 0.75rem 0.5rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem;
    }
    .category-sidebar-header h3 { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
    .cat-add-btn {
        display: flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border: none; background: transparent;
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .cat-add-btn:hover { background: var(--bg-card-hover); color: var(--primary); }
    .cat-add-btn i { width: 16px; height: 16px; }

    .cat-list { list-style: none; margin: 0; padding: 0; }
    .cat-item {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.5rem 0.75rem; cursor: pointer; transition: background 0.15s;
        border-left: 3px solid transparent; position: relative;
    }
    .cat-item:hover { background: var(--bg-card-hover); }
    .cat-item.active { background: var(--bg-card-hover); border-left-color: var(--primary); }
    .cat-item-icon {
        width: 24px; height: 24px; border-radius: 4px;
        display: flex; align-items: center; justify-content: center;
        background: var(--gray-100); color: var(--text-muted); flex-shrink: 0;
    }
    .cat-item-icon i { width: 14px; height: 14px; }
    .cat-item-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
    .cat-item-name { flex: 1; font-size: 0.8125rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cat-item-count { font-size: 0.6875rem; color: var(--text-muted); background: var(--gray-100); padding: 0.125rem 0.375rem; border-radius: 10px; }
    .cat-item-actions {
        display: none; position: absolute; right: 0.5rem; gap: 0.125rem;
    }
    .cat-item:hover .cat-item-actions { display: flex; }
    .cat-item:hover .cat-item-count { display: none; }
    .cat-action-btn {
        display: flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border: none; background: var(--bg-card);
        color: var(--text-muted); cursor: pointer; border-radius: 4px; transition: all 0.15s;
    }
    .cat-action-btn:hover { background: var(--gray-200); color: var(--text-primary); }
    .cat-action-btn.cat-vis-btn.hidden { color: var(--danger); }
    .cat-action-btn i { width: 12px; height: 12px; }

    .bookmarks-content { flex: 1; min-width: 0; }

    /* Mobile Category Dropdown */
    .category-dropdown-mobile { display: none; margin-bottom: 0.75rem; }
    .cat-dropdown-row { display: flex; gap: 0.5rem; align-items: center; }
    .cat-dropdown-row select {
        flex: 1; height: 32px; padding: 0 0.75rem; border: 1px solid var(--border);
        border-radius: 6px; font-size: 0.8125rem; background: var(--bg-card); color: var(--text-primary);
    }
    .cat-dropdown-row .cat-mobile-actions { display: flex; gap: 0.25rem; }
    .cat-mobile-btn {
        display: flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border: 1px solid var(--border); background: var(--bg-card);
        color: var(--text-secondary); cursor: pointer; border-radius: 6px; transition: all 0.15s;
    }
    .cat-mobile-btn:hover { border-color: var(--primary); color: var(--primary); }
    .cat-mobile-btn i { width: 16px; height: 16px; }


    @media (max-width: 992px) {
        .category-sidebar { display: none; }
        .category-dropdown-mobile { display: block; }
        .bookmarks-layout { display: block; }
    }
    @media (max-width: 768px) {
        .cat-dropdown-row select { height: 28px; font-size: 0.75rem; }
        .cat-mobile-btn { width: 28px; height: 28px; }
        .cat-mobile-btn i { width: 14px; height: 14px; }
    }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1><?= $editTool ? 'Edit Bookmark' : ($createMode ? 'Add New Bookmark' : 'Bookmarks') ?></h1>
                <?php if (!$editTool && !$createMode): ?>
                    <div class="header-buttons">
                        <?php if ($sortMode): ?>
                            <a href="bookmarks.php<?= $catFilter !== 'all' ? '?cat=' . $catFilter : '' ?>" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="check" class="btn-icon-only"></i>
                                <span class="btn-text">Done Sorting</span>
                            </a>
                        <?php else: ?>
                            <a href="?sort=1<?= $catFilter !== 'all' ? '&cat=' . $catFilter : '' ?>" class="btn btn-icon-mobile">
                                <i data-lucide="grip-vertical"></i>
                                <span class="btn-text">Sort Order</span>
                            </a>
                            <a href="?create=1" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="plus"></i>
                                <span class="btn-text">Add Bookmark</span>
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
                    <form method="POST" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $editTool['id'] ?>">
                        <input type="hidden" name="sort_order" value="<?= $editTool['sort_order'] ?>">

                        <div class="form-header">
                            <h2>Link Details</h2>
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
                            <div class="visibility-toggle-wrap" id="editPwaWrap" style="<?= $editTool['link_type'] === 'embed' ? '' : 'display:none' ?>">
                                <span>PWA</span>
                                <input type="hidden" name="is_pwa" id="editPwaInput" value="<?= ($editTool['is_pwa'] ?? 0) ? '1' : '0' ?>">
                                <button type="button"
                                    class="btn-visibility <?= ($editTool['is_pwa'] ?? 0) ? 'visible' : 'hidden' ?>"
                                    id="editPwaBtn"
                                    onclick="toggleEditPwa()"
                                    title="<?= ($editTool['is_pwa'] ?? 0) ? 'PWA enabled - click to disable' : 'PWA disabled - click to enable' ?>">
                                    <i data-lucide="<?= ($editTool['is_pwa'] ?? 0) ? 'smartphone' : 'smartphone' ?>"></i>
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
                                    <option value="" data-visible="1" <?= empty($editTool['category_id']) ? 'selected' : '' ?>>— No category —</option>
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
                                    <i data-lucide="file-text"></i><span>File</span>
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

                        <div class="form-group slug-field <?= $editTool['link_type'] === 'file' ? 'show' : '' ?>" id="editFileField">
                            <label>Select File *</label>
                            <?php renderAssetFileTrigger('edit_file', $editTool['file_path'] ?? ''); ?>
                        </div>

                        <div class="form-group <?= $editTool['link_type'] === 'file' ? 'hidden-field' : '' ?>" id="editTargetGroup">
                            <label for="target_url" id="editTargetLabel">Target URL *</label>
                            <input type="text" id="target_url" name="target_url" value="<?= htmlspecialchars($editTool['target_url']) ?>" placeholder="https://example.com">
                            <small class="text-muted" id="editTargetHelp" style="display:none;margin-top:0.25rem;"></small>
                        </div>

                        <div class="form-actions">
                            <a href="bookmarks.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                <script>
                    function selectEditLinkType(type) {
                        document.querySelectorAll('#editForm .link-type-btn').forEach(c => c.classList.remove('selected'));
                        document.querySelector(`#editForm input[value="${type}"]`).closest('.link-type-btn').classList.add('selected');
                        document.querySelector(`#editForm input[value="${type}"]`).checked = true;

                        document.getElementById('editPwaWrap').style.display = type === 'embed' ? '' : 'none';

                        const slugField = document.getElementById('editSlugField');
                        const fileField = document.getElementById('editFileField');
                        const targetGroup = document.getElementById('editTargetGroup');
                        const targetLabel = document.getElementById('editTargetLabel');
                        const targetInput = document.getElementById('target_url');
                        const targetHelp = document.getElementById('editTargetHelp');

                        if (type === 'url') {
                            slugField.classList.remove('show');
                            fileField.classList.remove('show');
                            targetGroup.classList.remove('hidden-field');
                            targetLabel.textContent = 'Target URL *';
                            targetInput.placeholder = 'https://example.com';
                            targetHelp.style.display = 'none';
                        } else if (type === 'file') {
                            slugField.classList.add('show');
                            fileField.classList.add('show');
                            targetGroup.classList.add('hidden-field');
                            targetHelp.style.display = 'none';
                        } else {
                            slugField.classList.add('show');
                            fileField.classList.remove('show');
                            targetGroup.classList.remove('hidden-field');
                            if (type === 'embed') {
                                targetLabel.textContent = 'URL to Embed *';
                                targetInput.placeholder = 'https://example.com/page';
                                targetHelp.textContent = 'External URL to show in iframe (must allow embedding)';
                                targetHelp.style.display = 'block';
                            } else {
                                targetLabel.textContent = 'Redirect To *';
                                targetInput.placeholder = 'https://example.com';
                                targetHelp.textContent = 'URL to redirect visitors to (301 permanent redirect)';
                                targetHelp.style.display = 'block';
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

                    function toggleEditPwa() {
                        const btn = document.getElementById('editPwaBtn');
                        const input = document.getElementById('editPwaInput');
                        const isOn = input.value === '1';
                        input.value = isOn ? '0' : '1';
                        btn.classList.toggle('visible', !isOn);
                        btn.classList.toggle('hidden', isOn);
                        btn.title = isOn ? 'PWA disabled - click to enable' : 'PWA enabled - click to disable';
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
                    selectEditLinkType('<?= $editTool['link_type'] ?>');
                    handleEditCategoryChange();
                </script>

            <?php elseif ($createMode): ?>
                <!-- Create Form (inline, not modal) -->
                <div class="card">
                    <form method="POST" id="createForm">
                        <input type="hidden" name="action" value="create">

                        <div class="form-header">
                            <h2>Link Details</h2>
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
                            <div class="visibility-toggle-wrap" id="newPwaWrap" style="display:none">
                                <span>PWA</span>
                                <input type="hidden" name="is_pwa" id="newPwaInput" value="0">
                                <button type="button"
                                    class="btn-visibility hidden"
                                    id="newPwaBtn"
                                    onclick="toggleNewPwa()"
                                    title="PWA disabled - click to enable">
                                    <i data-lucide="smartphone"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_name">Name *</label>
                                <input type="text" id="new_name" name="name" required oninput="updateNewSlug()">
                            </div>
                            <div class="form-group">
                                <label for="new_category_id">Category</label>
                                <select id="new_category_id" name="category_id" onchange="handleNewCategoryChange()">
                                    <option value="" data-visible="1">— No category —</option>
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
                                <?php renderAssetIconTrigger('new_icon', 'library', 'lucide:link'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Link Type *</label>
                            <div class="link-type-row">
                                <label class="link-type-btn selected" onclick="selectNewLinkType('url')">
                                    <input type="radio" name="link_type" value="url" checked>
                                    <i data-lucide="square-arrow-out-up-right"></i><span>URL</span>
                                </label>
                                <label class="link-type-btn" onclick="selectNewLinkType('redirect')">
                                    <input type="radio" name="link_type" value="redirect">
                                    <i data-lucide="corner-up-right"></i><span>Redirect</span>
                                </label>
                                <label class="link-type-btn" onclick="selectNewLinkType('embed')">
                                    <input type="radio" name="link_type" value="embed">
                                    <i data-lucide="app-window"></i><span>Embed</span>
                                </label>
                                <label class="link-type-btn" onclick="selectNewLinkType('file')">
                                    <input type="radio" name="link_type" value="file">
                                    <i data-lucide="file-text"></i><span>File</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group slug-field" id="newSlugField">
                            <label for="new_slug">URL Path *</label>
                            <div class="slug-input-wrapper">
                                <span class="slug-prefix"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/</span>
                                <input type="text" id="new_slug" name="slug" placeholder="my-tool">
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

                        <div class="form-group slug-field" id="newFileField">
                            <label>Select File *</label>
                            <?php renderAssetFileTrigger('new_file', ''); ?>
                        </div>

                        <div class="form-group" id="newTargetGroup">
                            <label for="new_target_url" id="newTargetLabel">Target URL *</label>
                            <input type="text" id="new_target_url" name="target_url" placeholder="https://example.com">
                            <small class="text-muted" id="newTargetHelp" style="display:none;margin-top:0.25rem;"></small>
                        </div>

                        <div class="form-actions">
                            <a href="bookmarks.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Link</button>
                        </div>
                    </form>
                </div>

                <script>
                    function selectNewLinkType(type) {
                        document.querySelectorAll('#createForm .link-type-btn').forEach(c => c.classList.remove('selected'));
                        document.querySelector(`#createForm input[value="${type}"]`).closest('.link-type-btn').classList.add('selected');
                        document.querySelector(`#createForm input[value="${type}"]`).checked = true;

                        document.getElementById('newPwaWrap').style.display = type === 'embed' ? '' : 'none';

                        const slugField = document.getElementById('newSlugField');
                        const fileField = document.getElementById('newFileField');
                        const targetGroup = document.getElementById('newTargetGroup');
                        const targetLabel = document.getElementById('newTargetLabel');
                        const targetInput = document.getElementById('new_target_url');
                        const targetHelp = document.getElementById('newTargetHelp');

                        if (type === 'url') {
                            slugField.classList.remove('show');
                            fileField.classList.remove('show');
                            targetGroup.classList.remove('hidden-field');
                            targetLabel.textContent = 'Target URL *';
                            targetInput.placeholder = 'https://example.com';
                            targetHelp.style.display = 'none';
                        } else if (type === 'file') {
                            slugField.classList.add('show');
                            fileField.classList.add('show');
                            targetGroup.classList.add('hidden-field');
                            targetHelp.style.display = 'none';
                        } else {
                            slugField.classList.add('show');
                            fileField.classList.remove('show');
                            targetGroup.classList.remove('hidden-field');
                            if (type === 'embed') {
                                targetLabel.textContent = 'URL to Embed *';
                                targetInput.placeholder = 'https://example.com/page';
                                targetHelp.textContent = 'External URL to show in iframe (must allow embedding)';
                                targetHelp.style.display = 'block';
                            } else {
                                targetLabel.textContent = 'Redirect To *';
                                targetInput.placeholder = 'https://example.com';
                                targetHelp.textContent = 'URL to redirect visitors to (301 permanent redirect)';
                                targetHelp.style.display = 'block';
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

                    function toggleNewPwa() {
                        const btn = document.getElementById('newPwaBtn');
                        const input = document.getElementById('newPwaInput');
                        const isOn = input.value === '1';
                        input.value = isOn ? '0' : '1';
                        btn.classList.toggle('visible', !isOn);
                        btn.classList.toggle('hidden', isOn);
                        btn.title = isOn ? 'PWA disabled - click to enable' : 'PWA enabled - click to disable';
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
                </script>

            <?php else: ?>
                <!-- Mobile Category Dropdown -->
                <div class="category-dropdown-mobile">
                    <div class="cat-dropdown-row">
                        <select id="catDropdownMobile" onchange="filterByCategory(this.value)">
                            <option value="all" <?= $catFilter === 'all' ? 'selected' : '' ?>>All Categories (<?= $totalBookmarkCount ?>)</option>
                            <?php foreach ($categoriesWithCounts as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?> (<?= $cat['bookmark_count'] ?>)<?= !$cat['is_visible'] ? ' 👁' : '' ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="uncategorized" <?= $catFilter === 'uncategorized' ? 'selected' : '' ?>>Uncategorized (<?= $uncategorizedCount ?>)</option>
                        </select>
                        <div class="cat-mobile-actions">
                            <button type="button" class="cat-mobile-btn" onclick="openCatModal()" title="Add Category">
                                <i data-lucide="folder-plus"></i>
                            </button>
                            <button type="button" class="cat-mobile-btn" onclick="openCatModal(document.getElementById('catDropdownMobile').value)" title="Edit Category" id="editCatMobileBtn" <?= $catFilter === 'all' || $catFilter === 'uncategorized' ? 'disabled style="opacity:0.5"' : '' ?>>
                                <i data-lucide="pencil"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bookmarks-layout">
                    <!-- Category Sidebar (Desktop) -->
                    <aside class="category-sidebar">
                        <div class="category-sidebar-header">
                            <h3>Categories</h3>
                            <button type="button" class="cat-add-btn" onclick="openCatModal()" title="Add Category">
                                <i data-lucide="plus"></i>
                            </button>
                        </div>
                        <ul class="cat-list" id="catList">
                            <li class="cat-item <?= $catFilter === 'all' ? 'active' : '' ?>" onclick="filterByCategory('all')">
                                <span class="cat-item-icon"><i data-lucide="layers"></i></span>
                                <span class="cat-item-name">All</span>
                                <span class="cat-item-count"><?= $totalBookmarkCount ?></span>
                            </li>
                            <?php foreach ($categoriesWithCounts as $cat): ?>
                            <li class="cat-item <?= $catFilter == $cat['id'] ? 'active' : '' ?>" data-id="<?= $cat['id'] ?>" onclick="filterByCategory(<?= $cat['id'] ?>)">
                                <span class="cat-item-icon">
                                    <?php if ($cat['icon_type'] === 'library'): ?>
                                        <i data-lucide="<?= str_replace('lucide:', '', $cat['icon_value']) ?>"></i>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($cat['icon_value']) ?>" alt="">
                                    <?php endif; ?>
                                </span>
                                <span class="cat-item-name"><?= htmlspecialchars($cat['name']) ?></span>
                                <span class="cat-item-count"><?= $cat['bookmark_count'] ?></span>
                                <div class="cat-item-actions" onclick="event.stopPropagation()">
                                    <button type="button" class="cat-action-btn cat-vis-btn <?= !$cat['is_visible'] ? 'hidden' : '' ?>" onclick="toggleCatVisibility(<?= $cat['id'] ?>, this, <?= $cat['visible_count'] ?>)" title="<?= $cat['is_visible'] ? 'Visible' : 'Hidden' ?>">
                                        <i data-lucide="<?= $cat['is_visible'] ? 'eye' : 'eye-off' ?>"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn" onclick="openCatModal(<?= $cat['id'] ?>)" title="Edit">
                                        <i data-lucide="pencil"></i>
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li class="cat-item <?= $catFilter === 'uncategorized' ? 'active' : '' ?>" onclick="filterByCategory('uncategorized')">
                                <span class="cat-item-icon"><i data-lucide="inbox"></i></span>
                                <span class="cat-item-name">Uncategorized</span>
                                <span class="cat-item-count"><?= $uncategorizedCount ?></span>
                            </li>
                        </ul>
                    </aside>

                    <!-- Bookmarks Content -->
                    <div class="bookmarks-content">
                <?php if ($sortMode): ?>
                    <div class="sort-banner">
                        <p><i data-lucide="info" style="width:16px;height:16px;vertical-align:middle;margin-right:0.5rem;"></i> Drag rows to reorder. Click "Done Sorting" when finished.</p>
                    </div>
                <?php else: ?>
                    <!-- Filter Row: Buttons + Search -->
                    <?php
                    $catParam = $catFilter !== 'all' ? '&cat=' . $catFilter : '';
                    $catParamFirst = $catFilter !== 'all' ? '?cat=' . $catFilter : '';
                    ?>
                    <div class="filter-row">
                        <div class="filter-buttons">
                            <a href="bookmarks.php<?= $catParamFirst ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                                All <span class="count"><?= $counts['total'] ?></span>
                            </a>
                            <a href="bookmarks.php?filter=visible<?= $catParam ?>" class="filter-btn <?= $filter === 'visible' ? 'active' : '' ?>">
                                Visible <span class="count"><?= $counts['visible'] ?></span>
                            </a>
                            <a href="bookmarks.php?filter=hidden<?= $catParam ?>" class="filter-btn <?= $filter === 'hidden' ? 'active' : '' ?>">
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
                                            <?php if ($tool['link_type'] === 'redirect' || $tool['link_type'] === 'embed' || $tool['link_type'] === 'file'): ?>
                                            <div class="slug-row">
                                                <a href="<?= url('/admin/qr.php') ?>?url=<?= urlencode($siteUrl . '/' . $tool['slug']) ?>" class="qr-btn" title="Generate QR Code"><i data-lucide="scan-qr-code"></i></a>
                                                <a href="<?= $siteUrl ?>/<?= htmlspecialchars($tool['slug']) ?>" target="_blank" class="slug-url"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/<?= htmlspecialchars($tool['slug']) ?></a>
                                            </div>
                                            <?php else: ?>
                                            <?php $displayUrl = preg_replace('#^https?://#', '', $tool['target_url']); ?>
                                            <div class="slug-row">
                                                <a href="<?= url('/admin/qr.php') ?>?url=<?= urlencode($tool['target_url']) ?>" class="qr-btn" title="Generate QR Code"><i data-lucide="scan-qr-code"></i></a>
                                                <a href="<?= htmlspecialchars($tool['target_url']) ?>" target="_blank" class="link-url" title="<?= htmlspecialchars($tool['target_url']) ?>"><?= htmlspecialchars($displayUrl) ?></a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $typeIcons = [
                                        'url' => 'square-arrow-out-up-right',
                                        'redirect' => 'corner-up-right',
                                        'embed' => 'app-window',
                                        'file' => 'file-text'
                                    ];
                                    $typeLabels = [
                                        'url' => 'URL',
                                        'redirect' => 'Redirect',
                                        'embed' => 'Embed',
                                        'file' => 'File'
                                    ];
                                    $icon = $typeIcons[$tool['link_type']] ?? 'link';
                                    $label = $typeLabels[$tool['link_type']] ?? $tool['link_type'];
                                    // For redirect/embed/file, link to site URL; for url type, link to target
                                    $linkUrl = ($tool['link_type'] === 'redirect' || $tool['link_type'] === 'embed' || $tool['link_type'] === 'file')
                                        ? $siteUrl . '/' . $tool['slug']
                                        : $tool['target_url'];
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
                            if ($catFilter !== 'all') $baseParams['cat'] = $catFilter;
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
                    </div><!-- /.pagination-wrapper -->
                <?php endif; ?>
                    </div><!-- /.bookmarks-content -->
                </div><!-- /.bookmarks-layout -->

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
                const url = row.querySelector('.link-url, .slug-url')?.textContent?.toLowerCase() || '';

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
            fetch('bookmarks.php', {
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

            fetch('bookmarks.php', {
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
            fetch('bookmarks.php', {
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

                    fetch('bookmarks.php', {
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

    <!-- Category Modal -->
    <div class="modal-overlay" id="catModalOverlay" onclick="if(event.target === this) closeCatModal()">
        <div class="modal-box" id="catModal">
            <div class="modal-box-header">
                <h3 id="catModalTitle">Add Category</h3>
                <button type="button" class="modal-box-close" onclick="closeCatModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-box-body">
                <input type="hidden" id="catModalId" value="">
                <div class="form-group">
                    <label for="catModalName">Name *</label>
                    <input type="text" id="catModalName" placeholder="Category name">
                </div>
                <div class="form-group">
                    <label for="catModalSlug">Slug</label>
                    <input type="text" id="catModalSlug" placeholder="auto-generated">
                </div>
                <div class="form-group">
                    <label>Icon</label>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="icon-preview clickable-icon" id="catModalIconPreview" onclick="openCatIconPicker()">
                            <i data-lucide="folder"></i>
                        </span>
                        <input type="hidden" id="catModalIconType" value="library">
                        <input type="hidden" id="catModalIconValue" value="lucide:folder">
                        <span style="font-size: 0.8125rem; color: var(--text-muted);">Click to change</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Visibility</label>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <input type="hidden" id="catModalVisible" value="1">
                        <button type="button" class="btn-visibility visible" id="catModalVisBtn" onclick="toggleCatModalVisibility()" title="Visible - click to hide">
                            <i data-lucide="eye"></i>
                        </button>
                        <span id="catModalVisLabel" style="font-size: 0.8125rem; color: var(--text-muted);">Visible on frontend</span>
                    </div>
                </div>
            </div>
            <div class="modal-box-footer">
                <button type="button" class="btn btn-danger" id="catModalDeleteBtn" onclick="deleteCatFromModal()" style="display: none;">Delete</button>
                <div style="display: flex; gap: 0.5rem; margin-left: auto;">
                    <button type="button" class="btn" onclick="closeCatModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCatModal()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Category filter function
    function filterByCategory(catId) {
        const url = new URL(window.location.href);
        if (catId === 'all') {
            url.searchParams.delete('cat');
        } else {
            url.searchParams.set('cat', catId);
        }
        url.searchParams.delete('page'); // Reset pagination
        window.location.href = url.toString();
    }

    // Category visibility toggle
    function toggleCatVisibility(id, btn, visibleCount) {
        const isCurrentlyVisible = !btn.classList.contains('hidden');
        if (isCurrentlyVisible && visibleCount > 0) {
            const msg = `Hiding this category will hide ${visibleCount} bookmark${visibleCount > 1 ? 's' : ''} from the public page.\n\nContinue?`;
            if (!confirm(msg)) return;
        }

        fetch('bookmarks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=cat_toggle_visibility&id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.is_visible) {
                btn.classList.remove('hidden');
                btn.title = 'Visible';
                btn.innerHTML = '<i data-lucide="eye"></i>';
            } else {
                btn.classList.add('hidden');
                btn.title = 'Hidden';
                btn.innerHTML = '<i data-lucide="eye-off"></i>';
            }
            lucide.createIcons();
        });
    }

    // Category Modal functions
    let catModalMode = 'create';
    let catModalBookmarkCount = 0;

    function openCatModal(catId = null) {
        const modal = document.getElementById('catModal');
        const titleEl = document.getElementById('catModalTitle');
        const deleteBtn = document.getElementById('catModalDeleteBtn');

        // Reset form
        document.getElementById('catModalId').value = '';
        document.getElementById('catModalName').value = '';
        document.getElementById('catModalSlug').value = '';
        document.getElementById('catModalIconType').value = 'library';
        document.getElementById('catModalIconValue').value = 'lucide:folder';
        document.getElementById('catModalIconPreview').innerHTML = '<i data-lucide="folder"></i>';
        document.getElementById('catModalVisible').value = '1';
        document.getElementById('catModalVisBtn').classList.remove('hidden');
        document.getElementById('catModalVisBtn').classList.add('visible');
        document.getElementById('catModalVisBtn').innerHTML = '<i data-lucide="eye"></i>';
        document.getElementById('catModalVisBtn').title = 'Visible - click to hide';
        document.getElementById('catModalVisLabel').textContent = 'Visible on frontend';

        if (catId && catId !== 'all' && catId !== 'uncategorized') {
            catModalMode = 'edit';
            titleEl.textContent = 'Edit Category';
            deleteBtn.style.display = 'block';

            // Fetch category data
            fetch('bookmarks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=cat_get&id=${catId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.category) {
                    const cat = data.category;
                    document.getElementById('catModalId').value = cat.id;
                    document.getElementById('catModalName').value = cat.name;
                    document.getElementById('catModalSlug').value = cat.slug;
                    document.getElementById('catModalIconType').value = cat.icon_type;
                    document.getElementById('catModalIconValue').value = cat.icon_value;
                    const isVis = cat.is_visible == 1;
                    document.getElementById('catModalVisible').value = isVis ? '1' : '0';
                    const visBtn = document.getElementById('catModalVisBtn');
                    const visLabel = document.getElementById('catModalVisLabel');
                    if (isVis) {
                        visBtn.classList.remove('hidden');
                        visBtn.classList.add('visible');
                        visBtn.innerHTML = '<i data-lucide="eye"></i>';
                        visBtn.title = 'Visible - click to hide';
                        visLabel.textContent = 'Visible on frontend';
                    } else {
                        visBtn.classList.remove('visible');
                        visBtn.classList.add('hidden');
                        visBtn.innerHTML = '<i data-lucide="eye-off"></i>';
                        visBtn.title = 'Hidden - click to show';
                        visLabel.textContent = 'Hidden from frontend';
                    }

                    // Update icon preview
                    const iconPreview = document.getElementById('catModalIconPreview');
                    if (cat.icon_type === 'library') {
                        iconPreview.innerHTML = `<i data-lucide="${cat.icon_value.replace('lucide:', '')}"></i>`;
                    } else {
                        iconPreview.innerHTML = `<img src="${cat.icon_value}" alt="">`;
                    }
                    lucide.createIcons();
                }
            });
        } else {
            catModalMode = 'create';
            titleEl.textContent = 'Add Category';
            deleteBtn.style.display = 'none';
        }

        lucide.createIcons();
        document.getElementById('catModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeCatModal() {
        document.getElementById('catModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    function saveCatModal() {
        const id = document.getElementById('catModalId').value;
        const name = document.getElementById('catModalName').value.trim();
        const slug = document.getElementById('catModalSlug').value.trim();
        const iconType = document.getElementById('catModalIconType').value;
        const iconValue = document.getElementById('catModalIconValue').value;
        const isVisible = document.getElementById('catModalVisible').value === '1' ? 1 : 0;

        if (!name) {
            alert('Please enter a category name');
            return;
        }

        const action = catModalMode === 'edit' ? 'cat_update' : 'cat_create';
        let body = `action=${action}&name=${encodeURIComponent(name)}&slug=${encodeURIComponent(slug)}&icon_type=${encodeURIComponent(iconType)}&icon_value=${encodeURIComponent(iconValue)}&is_visible=${isVisible}`;
        if (id) body += `&id=${id}`;

        fetch('bookmarks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeCatModal();
                window.location.reload();
            } else {
                alert(data.error || 'Failed to save category');
            }
        });
    }

    function deleteCatFromModal() {
        const id = document.getElementById('catModalId').value;
        const name = document.getElementById('catModalName').value;

        if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;

        fetch('bookmarks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=cat_delete&id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeCatModal();
                window.location.href = 'bookmarks.php';
            } else {
                alert(data.error || 'Cannot delete category. Remove all bookmarks first.');
            }
        });
    }

    // Category modal visibility toggle
    function toggleCatModalVisibility() {
        const input = document.getElementById('catModalVisible');
        const btn = document.getElementById('catModalVisBtn');
        const label = document.getElementById('catModalVisLabel');
        const isCurrentlyVisible = input.value === '1';

        if (isCurrentlyVisible) {
            input.value = '0';
            btn.classList.remove('visible');
            btn.classList.add('hidden');
            btn.innerHTML = '<i data-lucide="eye-off"></i>';
            btn.title = 'Hidden - click to show';
            label.textContent = 'Hidden from frontend';
        } else {
            input.value = '1';
            btn.classList.remove('hidden');
            btn.classList.add('visible');
            btn.innerHTML = '<i data-lucide="eye"></i>';
            btn.title = 'Visible - click to hide';
            label.textContent = 'Visible on frontend';
        }
        lucide.createIcons();
    }

    // Category icon picker
    function openCatIconPicker() {
        const typeInput = document.getElementById('catModalIconType');
        const valueInput = document.getElementById('catModalIconValue');
        const preview = document.getElementById('catModalIconPreview');
        const current = {
            type: typeInput?.value || 'library',
            value: valueInput?.value || ''
        };

        openAssetPicker('icon', result => {
            typeInput.value = result.type;
            valueInput.value = result.value;

            if (result.type === 'library') {
                const iconName = result.value.replace('lucide:', '');
                preview.innerHTML = `<i data-lucide="${iconName}"></i>`;
            } else {
                preview.innerHTML = `<img src="${result.value}" alt="">`;
            }
            lucide.createIcons();
        }, current, preview);
    }

    // Update mobile edit button state
    document.getElementById('catDropdownMobile')?.addEventListener('change', function() {
        const btn = document.getElementById('editCatMobileBtn');
        if (this.value === 'all' || this.value === 'uncategorized') {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    });

    </script>

    <?php
    // Asset picker styles and modal
    renderAssetPickerStyles();
    renderAssetPickerModal($popularIcons);
    ?>
</body>
</html>
