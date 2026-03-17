<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $bookmarkName ?> | <?= htmlspecialchars(getSetting('site_title')) ?></title>
    <?php if ($bookmarkDescription): ?>
    <meta name="description" content="<?= $bookmarkDescription ?>">
    <?php endif; ?>
    <?php
    $iconHref = ($bookmarkIconType === 'external' && $bookmarkIconValue)
        ? htmlspecialchars($bookmarkIconValue)
        : htmlspecialchars($siteLogo);
    ?>
    <link rel="icon" type="image/png" href="<?= $iconHref ?>">
    <?php if (!empty($isPwa)): ?>
    <link rel="manifest" href="manifest.json">
    <meta id="theme-color">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= $bookmarkName ?>">
    <link rel="apple-touch-icon" href="<?= $iconHref ?>">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
    </style>
</head>
<body>
    <iframe src="<?= $targetUrl ?>" allow="fullscreen popups-to-escape-sandbox" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"></iframe>
    <?php if (!empty($isPwa)): ?>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js', { scope: './' });
    }
    window.addEventListener('message', function(e) {
        if (e.data && e.data.themeColor && /^#[0-9A-Fa-f]{3,6}$/.test(e.data.themeColor)) {
            var meta = document.getElementById('theme-color');
            meta.setAttribute('content', e.data.themeColor);
            document.body.style.backgroundColor = e.data.themeColor;
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
