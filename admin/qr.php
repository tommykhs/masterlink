<?php
/**
 * QR Code Generator
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>QR Generator | <?= htmlspecialchars($siteTitle) ?> Admin</title>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>QR Generator</h1>
            </header>

            <div class="qr-container">
                <div class="qr-card">
                    <div class="qr-input-row">
                        <input type="url" id="qrUrl" placeholder="<?= $siteUrl ?>/your-link" autofocus>
                        <button type="button" class="btn btn-primary" onclick="generateQR()">
                            <i data-lucide="scan-qr-code"></i>
                        </button>
                    </div>

                    <div class="qr-preview empty" id="qrPreview">
                        <i data-lucide="scan-qr-code"></i>
                        <p>Enter URL and generate</p>
                    </div>

                    <div class="qr-actions" id="qrActions" style="display: none;">
                        <div class="qr-buttons">
                            <a id="downloadBtn" class="btn btn-primary" download="qrcode.png" title="Download PNG">
                                <i data-lucide="download"></i>
                            </a>
                            <button type="button" class="btn" onclick="openInNewTab()" title="Open in new tab">
                                <i data-lucide="external-link"></i>
                            </button>
                            <button type="button" class="btn" onclick="copyUrl()" title="Copy image URL">
                                <i data-lucide="copy"></i>
                            </button>
                        </div>
                        <div class="qr-options">
                            <select id="qrSize" onchange="updateQR()" title="Size">
                                <option value="200">200</option>
                                <option value="300" selected>300</option>
                                <option value="400">400</option>
                            </select>
                            <select id="qrMargin" onchange="updateQR()" title="Margin">
                                <option value="0">0</option>
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        let currentQrUrl = '';

        function getQrApiUrl(url) {
            const size = document.getElementById('qrSize').value;
            const margin = document.getElementById('qrMargin').value;
            return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&margin=${margin}&data=${encodeURIComponent(url)}`;
        }

        function generateQR() {
            const url = document.getElementById('qrUrl').value.trim();
            if (!url) {
                alert('Please enter a URL');
                return;
            }

            currentQrUrl = url;
            const preview = document.getElementById('qrPreview');
            const actions = document.getElementById('qrActions');

            // Show loading
            preview.className = 'qr-preview loading';
            preview.innerHTML = '<div class="spinner"></div>';

            const qrApiUrl = getQrApiUrl(url);

            // Create image
            const img = new Image();
            img.onload = function() {
                preview.className = 'qr-preview';
                preview.innerHTML = '';
                preview.appendChild(img);

                // Add URL display
                const urlDisplay = document.createElement('div');
                urlDisplay.className = 'qr-url-display';
                urlDisplay.textContent = url;
                preview.appendChild(urlDisplay);

                // Update download link
                document.getElementById('downloadBtn').href = qrApiUrl;
                document.getElementById('downloadBtn').download = 'qrcode-' + url.replace(/[^a-z0-9]/gi, '-').substring(0, 30) + '.png';

                // Show actions
                actions.style.display = 'flex';
                lucide.createIcons();
            };
            img.onerror = function() {
                preview.className = 'qr-preview empty';
                preview.innerHTML = '<i data-lucide="alert-circle"></i><p>Failed to generate QR code</p>';
                lucide.createIcons();
            };
            img.src = qrApiUrl;
        }

        function updateQR() {
            if (currentQrUrl) {
                generateQR();
            }
        }

        function openInNewTab() {
            if (currentQrUrl) {
                window.open(getQrApiUrl(currentQrUrl), '_blank');
            }
        }

        function copyUrl() {
            if (currentQrUrl) {
                const qrApiUrl = getQrApiUrl(currentQrUrl);
                navigator.clipboard.writeText(qrApiUrl).then(() => {
                    alert('QR code URL copied to clipboard');
                });
            }
        }

        // Generate on Enter key
        document.getElementById('qrUrl').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                generateQR();
            }
        });

        // Check for URL parameter and auto-generate
        const urlParams = new URLSearchParams(window.location.search);
        const prefilledUrl = urlParams.get('url');
        if (prefilledUrl) {
            document.getElementById('qrUrl').value = prefilledUrl;
            generateQR();
        }
    </script>
</body>
</html>
