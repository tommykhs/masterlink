<?php
/**
 * Shared Header Component
 * Used by both frontend (index.php) and admin pages
 *
 * Variables expected:
 * - $siteLogo (string) - Logo URL
 * - $siteTitle (string) - Site title
 * - $isAdmin (bool) - Whether in admin area (shows logout instead of login)
 */

$isAdmin = $isAdmin ?? false;
?>
<header class="site-header">
    <div class="header-inner">
        <?php if ($isAdmin): ?>
        <button class="header-btn hamburger-btn" id="menuToggle" aria-label="Toggle menu">
            <i data-lucide="menu"></i>
        </button>
        <?php endif; ?>

        <a href="<?= url('/') ?>" class="logo-link">
            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteTitle) ?>" class="logo-img">
            <span class="logo-text"><?= htmlspecialchars($siteTitle) ?></span>
        </a>

        <div class="header-actions">
            <?php if ($isAdmin): ?>
                <a href="<?= url('/admin/logout.php') ?>" class="header-btn" title="Logout">
                    <i data-lucide="log-out"></i>
                </a>
            <?php else: ?>
                <a href="<?= url('/admin/') ?>" class="header-btn" title="Login">
                    <i data-lucide="log-in"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php if ($isAdmin): ?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php endif; ?>
