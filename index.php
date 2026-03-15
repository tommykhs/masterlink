<?php
/**
 * MCAI - Public Frontend
 * Compact grid tool listing
 */

// Prevent caching to ensure fresh content
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/admin/includes/functions.php';

$pdo = getDB();

// Get site settings
$settings = [];
$rows = $pdo->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$siteTitle = $settings['site_title'] ?? 'MCAI';
$siteDescription = $settings['site_description'] ?? 'AI Links Platform';
$siteLogo = getSetting('site_logo');
$themeMode = getSetting('theme_mode', 'auto');

// Get visible categories with bookmark counts
$categories = $pdo->query("
    SELECT c.*, COUNT(t.id) as bookmark_count
    FROM categories c
    LEFT JOIN bookmarks t ON c.id = t.category_id AND t.is_visible = 1
    WHERE c.is_visible = 1
    GROUP BY c.id
    HAVING bookmark_count > 0
    ORDER BY c.sort_order, c.name
")->fetchAll();

// Get all visible bookmarks
$bookmarks = getBookmarks(true);
$bookmarksByCategory = [];
foreach ($bookmarks as $bookmark) {
    $catId = $bookmark['category_id'] ?? 0;
    $bookmarksByCategory[$catId][] = $bookmark;
}

// Check for category filter via URL param
$filterCatSlug = $_GET['cat'] ?? null;
$filterCatId = null;
if ($filterCatSlug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $filterCatSlug) {
            $filterCatId = $cat['id'];
            break;
        }
    }
}

// Build category slug map for JavaScript
$categorySlugs = [];
foreach ($categories as $cat) {
    $categorySlugs[$cat['id']] = $cat['slug'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($themeMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?> - <?= htmlspecialchars($siteDescription) ?></title>
    <meta name="description" content="<?= htmlspecialchars($siteDescription) ?> - AI-powered tools by <?= BRAND_NAME ?>.">
    <meta name="keywords" content="AI tools, artificial intelligence, <?= BRAND_NAME ?>, MCAI, automation">
    <meta name="author" content="<?= BRAND_AUTHOR ?>">
    <link rel="icon" type="image/png" href="<?= $siteLogo ?>">
    <link rel="apple-touch-icon" href="<?= $siteLogo ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteTitle) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($siteTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($siteDescription) ?>">
    <meta property="og:image" content="<?= $siteLogo ?>">
    <meta property="og:url" content="<?= htmlspecialchars(SITE_URL) ?>/">
    <meta name="color-scheme" content="light dark">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ==========================================================================
           CSS Variables - Theme System Ready
           Organized for easy color customization
           ========================================================================== */
        :root {
            /* Brand Colors */
            --primary: #667eea;
            --primary-hover: #5a67d8;
            --primary-light: rgba(102, 126, 234, 0.15);

            /* Background Colors */
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;

            /* Text Colors */
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --text-muted: #718096;

            /* Border & Dividers */
            --border: #e2e8f0;

            /* Header Specific */
            --header-bg: #ffffff;
            --header-text: #1a202c;
            --header-btn-bg: rgba(0,0,0,0.05);
            --header-btn-border: #e2e8f0;
            --header-btn-color: #4a5568;

            /* Component Colors */
            --icon-color: #667eea;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 24px rgba(102, 126, 234, 0.15);

            /* Spacing (for future use) */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;

            /* Border Radius */
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-full: 9999px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                /* Background Colors - Dark */
                --bg-page: #0f172a;
                --bg-card: #1e293b;
                --bg-card-hover: #334155;

                /* Text Colors - Dark */
                --text-primary: #f1f5f9;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;

                /* Border - Dark */
                --border: #334155;

                /* Header - Dark */
                --header-bg: #1e293b;
                --header-text: #f1f5f9;
                --header-btn-bg: rgba(255,255,255,0.1);
                --header-btn-border: #334155;
                --header-btn-color: #cbd5e1;

                /* Component Colors - Dark */
                --icon-color: #a5b4fc;
                --shadow-sm: 0 1px 3px rgba(0,0,0,0.2);
                --shadow-hover: 0 8px 24px rgba(102, 126, 234, 0.25);
            }
        }

        /* ==========================================================================
           Theme Overrides - Force Light
           ========================================================================== */
        [data-theme="light"] {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border: #e2e8f0;
            --header-bg: #ffffff;
            --header-text: #1a202c;
            --header-btn-bg: rgba(0,0,0,0.05);
            --header-btn-border: #e2e8f0;
            --header-btn-color: #4a5568;
            --icon-color: #667eea;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 24px rgba(102, 126, 234, 0.15);
        }

        /* ==========================================================================
           Theme Overrides - Force Dark
           ========================================================================== */
        [data-theme="dark"] {
            --bg-page: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border: #334155;
            --header-bg: #1e293b;
            --header-text: #f1f5f9;
            --header-btn-bg: rgba(255,255,255,0.1);
            --header-btn-border: #334155;
            --header-btn-color: #cbd5e1;
            --icon-color: #a5b4fc;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.2);
            --shadow-hover: 0 8px 24px rgba(102, 126, 234, 0.25);
        }

        /* ==========================================================================
           Theme Overrides - MC Brand (Light variant by default)
           ========================================================================== */
        [data-theme="mc"] {
            --primary: #FF9E1B;
            --primary-hover: #E88D17;
            --primary-light: rgba(255, 158, 27, 0.15);
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #FFF8ED;
            --text-primary: #1B365D;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border: #e2e8f0;
            --header-bg: #1B365D;
            --header-text: #ffffff;
            --header-btn-bg: rgba(255,255,255,0.15);
            --header-btn-border: rgba(255,255,255,0.2);
            --header-btn-color: #ffffff;
            --icon-color: #FF9E1B;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 24px rgba(255, 158, 27, 0.25);
        }

        /* MC Brand - Dark variant when system prefers dark */
        @media (prefers-color-scheme: dark) {
            [data-theme="mc"] {
                --bg-page: #0f1f38;
                --bg-card: #1B365D;
                --bg-card-hover: #243B5E;
                --text-primary: #f1f5f9;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;
                --border: #2D4A73;
                --header-bg: #1B365D;
                --header-text: #ffffff;
                --header-btn-bg: rgba(255,255,255,0.1);
                --header-btn-border: rgba(255,255,255,0.2);
                --header-btn-color: #ffffff;
                --icon-color: #FFB84D;
                --shadow-sm: 0 1px 3px rgba(0,0,0,0.2);
                --shadow-hover: 0 8px 24px rgba(255, 158, 27, 0.3);
            }
        }

        /* ==========================================================================
           Base Styles
           ========================================================================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ==========================================================================
           Header
           ========================================================================== */
        .site-header {
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            padding: 0.625rem var(--spacing-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .logo-link {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-decoration: none;
            color: var(--header-text);
        }

        .logo-img {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            object-fit: contain;
        }

        .logo-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .header-actions {
            position: absolute;
            right: 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .header-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--header-btn-bg);
            border: 1px solid var(--header-btn-border);
            border-radius: var(--radius-sm);
            color: var(--header-btn-color);
            text-decoration: none;
            transition: all 0.2s;
        }

        .header-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .header-btn i { width: 18px; height: 18px; }

        /* ==========================================================================
           Main Content
           ========================================================================== */
        .main-content { flex: 1; }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: var(--spacing-lg) var(--spacing-md);
        }

        /* ==========================================================================
           Category Filter
           ========================================================================== */
        .category-filter {
            display: flex;
            flex-wrap: nowrap;
            gap: var(--spacing-sm);
            justify-content: center;
            margin-bottom: var(--spacing-lg);
            overflow-x: auto;
            padding-bottom: var(--spacing-xs);
        }

        .filter-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border);
            border-radius: var(--radius-full);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            white-space: nowrap;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .filter-btn i { width: 16px; height: 16px; }

        /* ==========================================================================
           Tools Grid - Flexbox for horizontal centering
           ========================================================================== */
        .tools-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            max-width: 1020px;
            margin: 0 auto;
        }

        .tool-card {
            width: calc((100% - 3.75rem) / 6); /* 6 columns with gaps */
            min-width: 140px;
            max-width: 170px;
        }

        @media (max-width: 1100px) {
            .tool-card {
                width: calc((100% - 3rem) / 5); /* 5 columns */
            }
        }

        @media (max-width: 900px) {
            .tool-card {
                width: calc((100% - 2.25rem) / 4); /* 4 columns */
            }
        }

        /* ==========================================================================
           Tool Card
           ========================================================================== */
        .tool-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem var(--spacing-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .tool-card:hover {
            background: var(--bg-card-hover);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .tool-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 0.75rem;
            flex-shrink: 0;
        }

        .tool-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .tool-icon i {
            width: 32px;
            height: 32px;
            color: var(--icon-color);
        }

        .tool-name {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
            line-height: 1.2;
        }

        .tool-tagline {
            font-size: 0.7rem;
            color: var(--text-muted);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ==========================================================================
           Footer
           ========================================================================== */
        .site-footer {
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: var(--spacing-lg) var(--spacing-md);
            text-align: center;
        }

        .footer-copyright {
            color: var(--text-muted);
            font-size: 0.8125rem;
        }

        .footer-copyright a {
            color: inherit;
            text-decoration: none;
        }

        .footer-copyright a:hover { text-decoration: underline; }

        /* Footer Contact Buttons */
        .footer-contacts {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--header-btn-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .contact-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .contact-btn i {
            width: 20px;
            height: 20px;
        }

        .contact-btn img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        /* ==========================================================================
           Responsive - Tablet
           ========================================================================== */
        @media (max-width: 768px) {
            .container { padding: var(--spacing-md) 0.75rem; }

            .category-filter {
                justify-content: flex-start;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
                padding: 0 var(--spacing-sm) var(--spacing-sm);
            }

            .category-filter::-webkit-scrollbar { display: none; }

            .filter-btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.8rem;
                flex-shrink: 0;
            }

            .filter-btn i { width: 14px; height: 14px; }

            .tools-grid {
                gap: var(--spacing-sm);
                max-width: none;
            }

            .tool-card {
                width: calc((100% - 1rem) / 3); /* 3 columns */
                min-width: 100px;
                max-width: none;
                padding: var(--spacing-sm);
            }

            .tool-icon {
                width: 36px;
                height: 36px;
                margin-bottom: var(--spacing-sm);
            }

            .tool-icon i { width: 18px; height: 18px; }
            .tool-name { font-size: 0.75rem; margin-bottom: 0; }

            /* Hide description on mobile */
            .tool-tagline { display: none; }
        }

        /* ==========================================================================
           Responsive - Small Mobile
           ========================================================================== */
        @media (max-width: 380px) {
            .tool-card {
                width: calc((100% - 0.5rem) / 2); /* 2 columns */
                padding: 0.625rem;
            }

            .tool-icon { width: 40px; height: 40px; }
            .tool-name { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php
    $isAdmin = false;
    include __DIR__ . '/admin/includes/shared-header.php';
    ?>

    <main class="main-content">
        <div class="container">
            <div class="category-filter">
                <button class="filter-btn <?= !$filterCatId ? 'active' : '' ?>" data-category="all" data-slug="">
                    <i data-lucide="grid-3x3"></i>All
                </button>
                <?php foreach ($categories as $cat): ?>
                <button class="filter-btn <?= $filterCatId == $cat['id'] ? 'active' : '' ?>" data-category="<?= $cat['id'] ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                    <?php if ($cat['icon_type'] === 'library'): ?>
                        <i data-lucide="<?= str_replace('lucide:', '', $cat['icon_value']) ?>"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($cat['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="tools-grid">
                <?php foreach ($bookmarks as $bookmark): ?>
                <a href="<?= $bookmark['link_type'] === 'url' ? htmlspecialchars($bookmark['target_url']) : url('/' . htmlspecialchars($bookmark['slug']) . '/') ?>"
                   class="tool-card"
                   data-category="<?= $bookmark['category_id'] ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                    <div class="tool-icon">
                        <?php if ($bookmark['icon_type'] === 'library'): ?>
                            <i data-lucide="<?= str_replace('lucide:', '', $bookmark['icon_value']) ?>"></i>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($bookmark['icon_value']) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="tool-name"><?= htmlspecialchars($bookmark['name']) ?></div>
                    <?php if ($bookmark['description']): ?>
                        <div class="tool-tagline"><?= htmlspecialchars($bookmark['description']) ?></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <?php $contacts = getContacts(); ?>
        <?php if (!empty($contacts)): ?>
        <div class="footer-contacts">
            <?php foreach ($contacts as $contact): ?>
            <a href="<?= htmlspecialchars($contact['url']) ?>"
               class="contact-btn"
               target="_blank"
               rel="noopener noreferrer"
               title="<?= htmlspecialchars($contact['name']) ?>">
                <?php if ($contact['icon_type'] === 'library'): ?>
                    <i data-lucide="<?= htmlspecialchars($contact['icon_value']) ?>"></i>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($contact['icon_value']) ?>" alt="">
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="footer-copyright">
            <?= getCopyright() ?>
        </p>
    </footer>

    <script>
        lucide.createIcons();

        // Category filtering
        const filterBtns = document.querySelectorAll('.filter-btn');
        const toolCards = document.querySelectorAll('.tool-card');

        function filterByCategory(category, slug, updateUrl = true) {
            filterBtns.forEach(b => b.classList.remove('active'));
            const activeBtn = document.querySelector(`.filter-btn[data-category="${category}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            toolCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update URL without page reload
            if (updateUrl) {
                const url = new URL(window.location);
                if (slug) {
                    url.searchParams.set('cat', slug);
                } else {
                    url.searchParams.delete('cat');
                }
                history.pushState({category, slug}, '', url);
            }
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterByCategory(btn.dataset.category, btn.dataset.slug);
            });
        });

        // Handle browser back/forward
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.category) {
                filterByCategory(e.state.category, e.state.slug, false);
            } else {
                // Check URL param
                const urlParams = new URLSearchParams(window.location.search);
                const catSlug = urlParams.get('cat');
                if (catSlug) {
                    const btn = document.querySelector(`.filter-btn[data-slug="${catSlug}"]`);
                    if (btn) filterByCategory(btn.dataset.category, catSlug, false);
                } else {
                    filterByCategory('all', '', false);
                }
            }
        });

        // Apply filter on page load if URL has ?cat param
        <?php if ($filterCatId): ?>
        filterByCategory('<?= $filterCatId ?>', '<?= htmlspecialchars($filterCatSlug) ?>', false);
        <?php endif; ?>
    </script>
</body>
</html>
