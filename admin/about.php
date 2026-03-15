<?php
/**
 * About Page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$lastUpdate = getLastCodeUpdate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>About | <?= htmlspecialchars(getSetting('site_title')) ?> Admin</title>
    <style>
        .github-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.75rem; background: #24292e; color: #fff;
            border-radius: 6px; text-decoration: none; font-weight: 500;
            transition: background 0.15s; font-size: 0.875rem;
        }
        .github-btn:hover { background: #1b1f23; color: #fff; }
        .github-btn i { width: 16px; height: 16px; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>About</h1>
            </header>

            <div class="content-narrow">
                <div class="card">
                    <div class="card-header">
                        <h2><?= BRAND_NAME ?></h2>
                    </div>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                        A lightweight bookmark and link management system with admin panel, API support, and customizable themes.
                    </p>
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">Version</span>
                            <span class="info-value">1.2.1</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value" title="<?= $lastUpdate['file'] ?>"><?= $lastUpdate['datetime'] ?> <?= $lastUpdate['timezone'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Author</span>
                            <span class="info-value"><a href="mailto:Tommy.shum@hkmci.com">Tommy Shum</a></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Source</span>
                            <span class="info-value">
                                <a href="https://github.com/mcailab/masterlink" class="github-btn" target="_blank" rel="noopener">
                                    <i data-lucide="github"></i>
                                    GitHub
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
