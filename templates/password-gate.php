<?php
/**
 * Password Gate Template
 * Displayed when accessing password-protected files
 */
require_once __DIR__ . '/../admin/includes/functions.php';
$siteTitle = getSetting('site_title');
$siteLogo = getSetting('site_logo');
$itemName = $bookmark['name'] ?? basename($file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Required | <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteLogo) ?>">
    <meta name="color-scheme" content="light dark">
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1a202c;
            --text-muted: #718096;
            --border: #e2e8f0;
            --primary: #667eea;
            --primary-hover: #5a67d8;
            --error: #e53e3e;
            --error-bg: #fed7d7;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #0f172a;
                --bg-card: #1e293b;
                --text-primary: #f1f5f9;
                --text-muted: #94a3b8;
                --border: #334155;
                --error-bg: #3b1c1c;
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
            padding: 1rem;
        }
        .password-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .lock-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            color: var(--primary);
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .item-name {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        .error-msg {
            background: var(--error-bg);
            color: var(--error);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-page);
            color: var(--text-primary);
            text-align: center;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        button {
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .back-link {
            display: block;
            margin-top: 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="password-box">
        <svg class="lock-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <h1>Protected Content</h1>
        <p class="item-name"><?= htmlspecialchars($itemName) ?></p>

        <?php if (!empty($error)): ?>
        <div class="error-msg">Incorrect password. Please try again.</div>
        <?php endif; ?>

        <form method="POST">
            <input type="password" name="password" placeholder="Enter password" autofocus required>
            <button type="submit">Unlock</button>
        </form>

        <a href="<?= url('/') ?>" class="back-link">Back to <?= htmlspecialchars($siteTitle) ?></a>
    </div>
</body>
</html>
