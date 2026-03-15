<?php
/**
 * Authentication Handler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require login - redirect if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verify password against stored hash
 */
function verifyPassword(string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
    $stmt->execute();
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['setting_value'])) {
        return true;
    }

    // Fallback: check if password matches plaintext (for initial setup)
    if ($row && $row['setting_value'] === $password) {
        // Upgrade to hashed password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_password'");
        $stmt->execute([$hash]);
        return true;
    }

    return false;
}

/**
 * Login user
 */
function login(string $password): bool {
    if (verifyPassword($password)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logout(): void {
    $_SESSION = [];
    session_destroy();
}

/**
 * Update admin password
 */
function updatePassword(string $newPassword): bool {
    $pdo = getDB();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_password'");
    return $stmt->execute([$hash]);
}
