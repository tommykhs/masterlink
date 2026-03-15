<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $bookmarkName ?> | <?= htmlspecialchars(getSetting('site_title')) ?></title>
    <?php if ($bookmarkDescription): ?>
    <meta name="description" content="<?= $bookmarkDescription ?>">
    <?php endif; ?>
    <?php if ($bookmarkIconType === 'external' && $bookmarkIconValue): ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($bookmarkIconValue) ?>">
    <?php else: ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteLogo) ?>">
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
    <iframe src="<?= $targetUrl ?>" allow="fullscreen" sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>
</body>
</html>
