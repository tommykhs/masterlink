<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo = getDB();

// Limits for dashboard display
$bookmarkLimit = 12; // 2 rows of 6
$contactLimit = 6;   // 2 rows of 3
$shortenerLimit = 4; // 4 rows

// Get visible bookmarks (limited)
$visibleLinks = $pdo->query("
    SELECT id, name, slug, icon_type, icon_value, is_visible
    FROM bookmarks
    WHERE is_visible = 1
    ORDER BY sort_order, name
    LIMIT $bookmarkLimit
")->fetchAll();

// Get contacts (limited)
$contacts = $pdo->query("
    SELECT id, name, url, icon_type, icon_value
    FROM contacts
    ORDER BY sort_order, id
    LIMIT $contactLimit
")->fetchAll();

// Get shortener links (limited)
$shortenerLinks = $pdo->query("
    SELECT id, name, slug, icon_type, icon_value, target_url
    FROM bookmarks
    WHERE link_type IN ('redirect', 'embed')
    ORDER BY created_at DESC
    LIMIT $shortenerLimit
")->fetchAll();

// Get total counts
$visibleLinkCount = $pdo->query("SELECT COUNT(*) FROM bookmarks WHERE is_visible = 1")->fetchColumn();
$contactCount = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
$shortenerCount = $pdo->query("SELECT COUNT(*) FROM bookmarks WHERE link_type IN ('redirect', 'embed')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Dashboard | <?= htmlspecialchars($siteTitle) ?> Admin</title>
    <style>
        /* 2x2 Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .dashboard-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            font-size: 0.9375rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .card-header h2 i {
            width: 18px;
            height: 18px;
            color: var(--text-muted);
        }

        .card-header .count {
            background: var(--primary);
            color: white;
            font-size: 0.6875rem;
            padding: 0.125rem 0.4rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .card-header .more-link {
            font-size: 0.8125rem;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.125rem;
        }

        .card-header .more-link:hover {
            text-decoration: underline;
        }

        .card-header .more-link i {
            width: 14px;
            height: 14px;
        }

        .card-content {
            padding: 1rem;
        }

        /* Item Grid (Categories, Contacts) */
        .item-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .grid-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--bg-hover);
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
            transition: all 0.15s;
            overflow: hidden;
        }

        .grid-item:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.08);
        }

        .grid-item.hidden-item {
            opacity: 0.5;
        }

        .grid-item .item-icon {
            width: 28px;
            height: 28px;
            min-width: 28px;
            background: var(--border);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .grid-item .item-icon i {
            width: 16px;
            height: 16px;
            color: var(--text-secondary);
        }

        .grid-item .item-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .grid-item .item-name {
            font-size: 0.8125rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Icon Grid (Bookmarks) */
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
        }

        .icon-item {
            aspect-ratio: 1;
            background: var(--bg-hover);
            border: 1px solid var(--border);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.15s;
            overflow: hidden;
        }

        .icon-item:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.08);
            transform: scale(1.05);
        }

        .icon-item i {
            width: 22px;
            height: 22px;
            color: var(--text-secondary);
        }

        .icon-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-muted);
        }

        .empty-state i {
            width: 32px;
            height: 32px;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.8125rem;
            margin: 0;
        }

        /* Shortener table in dashboard */
        .shortener-content {
            padding: 0;
        }

        .shortener-table {
            margin: 0;
        }

        .shortener-table td {
            padding: 0.625rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .shortener-table tr:last-child td {
            border-bottom: none;
        }

        .shortener-name {
            font-size: 0.8125rem;
            font-weight: 500;
            color: inherit;
            text-decoration: none;
            display: block;
        }

        .shortener-name:hover {
            color: var(--primary);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .item-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .icon-grid {
                grid-template-columns: repeat(6, 1fr);
                gap: 0.375rem;
                /* Limit to 2 rows */
                max-height: 90px;
                overflow: hidden;
            }

            .icon-item {
                border-radius: 4px;
            }

            .icon-item i {
                width: 16px;
                height: 16px;
            }

            .grid-item .item-name {
                font-size: 0.75rem;
            }

            .shortener-name {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>Dashboard</h1>
            </header>

            <div class="dashboard-grid">
                <!-- Bookmarks -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>
                            <i data-lucide="bookmark"></i>
                            Bookmarks
                            <span class="count"><?= $visibleLinkCount ?></span>
                        </h2>
                        <a href="bookmarks.php" class="more-link">
                            All <i data-lucide="chevron-right"></i>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($visibleLinks)): ?>
                            <div class="empty-state">
                                <i data-lucide="bookmark"></i>
                                <p>No bookmarks yet</p>
                            </div>
                        <?php else: ?>
                            <div class="icon-grid">
                                <?php foreach ($visibleLinks as $link):
                                    $iconName = str_replace('lucide:', '', $link['icon_value'] ?? 'link');
                                    if ($iconName === 'tool') $iconName = 'wrench';
                                    if (empty($iconName)) $iconName = 'link';
                                ?>
                                <a href="bookmarks.php?edit=<?= $link['id'] ?>" class="icon-item" title="<?= htmlspecialchars($link['name']) ?>">
                                    <?php if ($link['icon_type'] === 'library'): ?>
                                        <i data-lucide="<?= htmlspecialchars($iconName) ?>"></i>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($link['icon_value']) ?>" alt="">
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contacts -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>
                            <i data-lucide="contact"></i>
                            Contacts
                            <span class="count"><?= $contactCount ?></span>
                        </h2>
                        <a href="contacts.php" class="more-link">
                            All <i data-lucide="chevron-right"></i>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($contacts)): ?>
                            <div class="empty-state">
                                <i data-lucide="contact"></i>
                                <p>No contacts yet</p>
                            </div>
                        <?php else: ?>
                            <div class="item-grid">
                                <?php foreach ($contacts as $contact):
                                    $iconName = str_replace('lucide:', '', $contact['icon_value'] ?? 'globe');
                                    if (empty($iconName)) $iconName = 'globe';
                                ?>
                                <a href="contacts.php?edit=<?= $contact['id'] ?>" class="grid-item" title="<?= htmlspecialchars($contact['name']) ?>">
                                    <div class="item-icon">
                                        <?php if ($contact['icon_type'] === 'library'): ?>
                                            <i data-lucide="<?= htmlspecialchars($iconName) ?>"></i>
                                        <?php else: ?>
                                            <img src="<?= htmlspecialchars($contact['icon_value']) ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <span class="item-name"><?= htmlspecialchars($contact['name']) ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shortener -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>
                            <i data-lucide="scissors"></i>
                            Shortener
                            <span class="count"><?= $shortenerCount ?></span>
                        </h2>
                        <a href="shortener.php" class="more-link">
                            All <i data-lucide="chevron-right"></i>
                        </a>
                    </div>
                    <div class="card-content shortener-content">
                        <?php if (empty($shortenerLinks)): ?>
                            <div class="empty-state">
                                <i data-lucide="scissors"></i>
                                <p>No short links yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table shortener-table">
                                    <tbody>
                                        <?php foreach ($shortenerLinks as $link):
                                            $iconName = str_replace('lucide:', '', $link['icon_value'] ?? 'link');
                                            if ($iconName === 'tool') $iconName = 'wrench';
                                            if (empty($iconName)) $iconName = 'link';
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <span class="icon-preview">
                                                        <?php if ($link['icon_type'] === 'library'): ?>
                                                            <i data-lucide="<?= htmlspecialchars($iconName) ?>"></i>
                                                        <?php else: ?>
                                                            <img src="<?= htmlspecialchars($link['icon_value']) ?>" alt="">
                                                        <?php endif; ?>
                                                    </span>
                                                    <div>
                                                        <a href="shortener.php?edit=<?= $link['id'] ?>" class="shortener-name"><?= htmlspecialchars($link['name']) ?></a>
                                                        <div class="slug-row">
                                                            <a href="<?= url('/admin/qr.php') ?>?url=<?= urlencode($siteUrl . '/' . $link['slug']) ?>" class="qr-btn" title="Generate QR Code"><i data-lucide="scan-qr-code"></i></a>
                                                            <a href="<?= $siteUrl ?>/<?= htmlspecialchars($link['slug']) ?>" target="_blank" class="slug-url"><?= parse_url($siteUrl, PHP_URL_HOST) ?>/<?= htmlspecialchars($link['slug']) ?></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
