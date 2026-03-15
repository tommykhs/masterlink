<?php
require_once __DIR__ . '/../admin/includes/functions.php';
$siteTitle = getSetting('site_title');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | <?= htmlspecialchars($siteTitle) ?></title>
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --bg-page: #f8fafc;
            --text-primary: #1a202c;
            --text-muted: #718096;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #0f172a;
                --text-primary: #f1f5f9;
                --text-muted: #94a3b8;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-page);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        p {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin: 1rem 0 2rem;
        }
        a {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        a:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>The page you're looking for doesn't exist.</p>
        <a href="<?= url('/') ?>">Back to <?= htmlspecialchars($siteTitle) ?></a>
    </div>
</body>
</html>
