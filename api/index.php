<?php
/**
 * MCAI API Documentation - Swagger UI
 */
require_once __DIR__ . '/../admin/includes/functions.php';
$siteLogo = getSetting('site_logo');
$siteTitle = getSetting('site_title');
$isAdmin = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?> API</title>
    <meta name="description" content="<?= htmlspecialchars($siteTitle) ?> REST API documentation">
    <meta name="author" content="<?= BRAND_AUTHOR ?>">
    <link rel="icon" type="image/png" href="<?= $siteLogo ?>">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; }
        .topbar { display: none !important; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .info .title { font-size: 1.5rem; }
        .swagger-ui .scheme-container { background: var(--bg-card); box-shadow: none; }
        @media (prefers-color-scheme: dark) {
            .swagger-ui, .swagger-ui .info .title, .swagger-ui .opblock-tag,
            .swagger-ui .opblock .opblock-summary-description,
            .swagger-ui .opblock-description-wrapper p,
            .swagger-ui .parameter__name, .swagger-ui .parameter__type,
            .swagger-ui table thead tr th, .swagger-ui table tbody tr td,
            .swagger-ui .model-title, .swagger-ui .model { color: #e2e8f0 !important; }
            .swagger-ui .opblock .opblock-section-header { background: #334155 !important; }
            .swagger-ui section.models { border-color: #334155 !important; }
            .swagger-ui section.models .model-container { background: #1e293b !important; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../admin/includes/shared-header.php'; ?>

    <main class="main-content">
        <div id="swagger-ui"></div>
    </main>

    <footer class="site-footer">
        <p class="footer-copyright"><?= getCopyright() ?></p>
    </footer>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        lucide.createIcons();
        window.onload = function() {
            SwaggerUIBundle({
                url: "<?= url('/api/openapi.json') ?>",
                dom_id: '#swagger-ui',
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                layout: "BaseLayout",
                deepLinking: true,
                docExpansion: "list",
                filter: true,
                validatorUrl: null
            });
        };
    </script>
</body>
</html>
