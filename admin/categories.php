<?php
/**
 * Categories Management - Redirects to Bookmarks page
 * Categories are now managed within the Bookmarks page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect to bookmarks page - categories are now integrated there
header('Location: ' . url('/admin/bookmarks.php'));
exit;
