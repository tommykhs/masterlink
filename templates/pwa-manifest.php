<?php
header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=3600');

$name = $pwaBookmark['name'];
$description = $pwaBookmark['description'] ?: $name;
$slug = $pwaBookmark['slug'];
$basePath = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';

// Determine icon URL
if ($pwaBookmark['icon_type'] === 'external' && $pwaBookmark['icon_value']) {
    $iconUrl = $pwaBookmark['icon_value'];
} elseif ($pwaBookmark['icon_type'] === 'upload' && $pwaBookmark['icon_value']) {
    $iconUrl = SITE_URL . $pwaBookmark['icon_value'];
} else {
    $iconUrl = getSetting('site_logo');
}

echo json_encode([
    'name' => $name,
    'short_name' => mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name,
    'description' => $description,
    'start_url' => $basePath . '/' . $slug . '/',
    'scope' => $basePath . '/' . $slug . '/',
    'display' => 'standalone',
    'background_color' => '#000000',
    'icons' => [
        ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
