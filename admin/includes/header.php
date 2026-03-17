<?php
/**
 * Admin Header Include
 * Common meta tags, favicon, and CSS for admin pages
 */

$siteLogo = getSetting('site_logo');
$siteTitle = getSetting('site_title');
$siteUrl = rtrim(SITE_URL, '/');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="<?= BRAND_AUTHOR ?>">
<link rel="icon" type="image/png" href="<?= $siteLogo ?>">
<link rel="apple-touch-icon" href="<?= $siteLogo ?>">
<link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>?v=<?= filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
<script src="https://unpkg.com/lucide@latest"></script>
<script>
// Site configuration for JavaScript
const SITE_URL = '<?= $siteUrl ?>';
const SITE_HOST = '<?= parse_url($siteUrl, PHP_URL_HOST) . (parse_url($siteUrl, PHP_URL_PATH) ?: '') ?>';

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Reinitialize lucide icons after DOM loaded
    setTimeout(function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }, 100);
});
</script>
