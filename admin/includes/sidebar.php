<?php
/**
 * Admin Sidebar Component
 * Includes shared header + admin navigation sidebar
 */

$siteLogo = getSetting('site_logo');
$siteTitle = getSetting('site_title');
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = true;

// Include shared header
include __DIR__ . '/shared-header.php';
?>

<!-- Sidebar (below header) -->
<aside class="admin-sidebar" id="adminSidebar">
    <nav class="sidebar-nav">
        <a href="<?= url('/admin/index.php') ?>" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('/admin/bookmarks.php') ?>" class="nav-item <?= $currentPage === 'bookmarks.php' || $currentPage === 'categories.php' ? 'active' : '' ?>">
            <i data-lucide="bookmark"></i>
            <span>Bookmarks</span>
        </a>
        <a href="<?= url('/admin/shortener.php') ?>" class="nav-item <?= $currentPage === 'shortener.php' ? 'active' : '' ?>">
            <i data-lucide="scissors"></i>
            <span>Shortener</span>
        </a>
        <a href="<?= url('/admin/contacts.php') ?>" class="nav-item <?= $currentPage === 'contacts.php' ? 'active' : '' ?>">
            <i data-lucide="contact"></i>
            <span>Contacts</span>
        </a>
        <a href="<?= url('/admin/files.php') ?>" class="nav-item <?= $currentPage === 'files.php' ? 'active' : '' ?>">
            <i data-lucide="folder"></i>
            <span>Files</span>
        </a>
        <a href="<?= url('/admin/qr.php') ?>" class="nav-item <?= $currentPage === 'qr.php' ? 'active' : '' ?>">
            <i data-lucide="scan-qr-code"></i>
            <span>QR Generator</span>
        </a>
        <a href="<?= url('/admin/settings.php') ?>" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <i data-lucide="settings"></i>
            <span>Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= url('/api/') ?>" class="nav-item" target="_blank">
            <i data-lucide="code"></i>
            <span>API</span>
        </a>
        <a href="<?= url('/admin/about.php') ?>" class="nav-item <?= $currentPage === 'about.php' ? 'active' : '' ?>">
            <i data-lucide="info"></i>
            <span>About</span>
        </a>
        <?php $lastUpdate = getLastCodeUpdate(); ?>
        <div class="sidebar-copyright">
            <div class="sidebar-version" title="Last modified: <?= $lastUpdate['file'] ?>">
                Updated: <?= $lastUpdate['datetime'] ?>
            </div>
            <?= getCopyright() ?>
        </div>
    </div>
</aside>
