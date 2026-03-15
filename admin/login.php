<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$siteLogo = getSetting('site_logo');
$siteTitle = getSetting('site_title');

// Already logged in?
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= $siteLogo ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
    <style>
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .login-logo img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #fff;
        }
        .login-logo span {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <img src="<?= $siteLogo ?>" alt="<?= htmlspecialchars($siteTitle) ?>">
                <span><?= htmlspecialchars($siteTitle) ?></span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <a href="<?= url('/') ?>" class="back-link">&larr; Back to <?= htmlspecialchars($siteTitle) ?></a>
        </div>
        <div class="login-footer">
            <?= getCopyright() ?>
        </div>
    </div>
</body>
</html>
