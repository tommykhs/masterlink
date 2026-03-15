<?php
/**
 * Page Template
 * Renders Markdown content as styled HTML page
 * Variables: $html (rendered HTML), $pageTitle (page title)
 */
require_once __DIR__ . '/../admin/includes/functions.php';
$siteTitle = getSetting('site_title');
$siteLogo = getSetting('site_logo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteLogo) ?>">
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1a202c;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --primary: #667eea;
            --code-bg: #f1f5f9;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #0f172a;
                --bg-card: #1e293b;
                --text-primary: #f1f5f9;
                --text-muted: #94a3b8;
                --border: #334155;
                --code-bg: #0f172a;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            line-height: 1.7;
        }
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .page-header .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .page-header .back-link:hover {
            color: var(--primary);
        }
        .page-content {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        /* Typography */
        .page-content h1 { font-size: 1.875rem; margin: 1.5rem 0 1rem; font-weight: 700; }
        .page-content h2 { font-size: 1.5rem; margin: 1.5rem 0 0.75rem; font-weight: 600; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        .page-content h3 { font-size: 1.25rem; margin: 1.25rem 0 0.5rem; font-weight: 600; }
        .page-content h4 { font-size: 1.125rem; margin: 1rem 0 0.5rem; font-weight: 600; }
        .page-content h5, .page-content h6 { font-size: 1rem; margin: 1rem 0 0.5rem; font-weight: 600; }
        .page-content p { margin: 0.75rem 0; }
        .page-content a { color: var(--primary); text-decoration: none; }
        .page-content a:hover { text-decoration: underline; }
        .page-content ul, .page-content ol { margin: 0.75rem 0; padding-left: 1.5rem; }
        .page-content li { margin: 0.25rem 0; }
        .page-content blockquote {
            margin: 1rem 0;
            padding: 0.75rem 1rem;
            border-left: 4px solid var(--primary);
            background: var(--code-bg);
            color: var(--text-muted);
        }
        .page-content code {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.875em;
            background: var(--code-bg);
            padding: 0.2em 0.4em;
            border-radius: 4px;
        }
        .page-content pre {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--code-bg);
            border-radius: 8px;
            overflow-x: auto;
        }
        .page-content pre code {
            background: none;
            padding: 0;
        }
        .page-content table {
            width: 100%;
            margin: 1rem 0;
            border-collapse: collapse;
        }
        .page-content th, .page-content td {
            padding: 0.75rem;
            border: 1px solid var(--border);
            text-align: left;
        }
        .page-content th {
            background: var(--code-bg);
            font-weight: 600;
        }
        .page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .page-content hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 2rem 0;
        }
        /* Task lists */
        .page-content input[type="checkbox"] {
            margin-right: 0.5rem;
        }
        /* First element no top margin */
        .page-content > *:first-child { margin-top: 0; }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="page-header">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <a href="<?= url('/') ?>" class="back-link">&larr; Back to <?= htmlspecialchars($siteTitle) ?></a>
        </header>
        <article class="page-content">
            <?= $html ?>
        </article>
    </div>
</body>
</html>
