<?php
/**
 * Database Configuration
 * Copy this file to config.php and update with your credentials
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Site URL - Change this to your domain (no trailing slash)
// For root install: https://example.com
// For subfolder: https://example.com/link
define('SITE_URL', 'https://example.com');

// Base path - Auto-extracted from SITE_URL
// Root install: '' (empty), Subfolder: '/link'
define('BASE_PATH', parse_url(SITE_URL, PHP_URL_PATH) ?: '');

// Upload paths
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session settings
define('SESSION_NAME', 'masterlink_session');
define('SESSION_LIFETIME', 86400); // 24 hours
