<?php
/**
 * Settings Management
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/asset-picker.php';

requireLogin();

$pdo = getDB();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_password') {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            updatePassword($newPassword);
            $message = 'Password updated successfully!';
        }
    }

    if ($action === 'update_settings') {
        $settingsToSave = [
            'site_title' => trim($_POST['site_title']),
            'site_description' => trim($_POST['site_description']),
            'site_logo' => trim($_POST['site_logo']),
            'theme_mode' => $_POST['theme_mode'] ?? 'auto',
        ];
        if (!in_array($settingsToSave['theme_mode'], ['auto', 'light', 'dark', 'mc'])) {
            $settingsToSave['theme_mode'] = 'auto';
        }
        foreach ($settingsToSave as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $message = 'Settings saved!';
    }

    if ($action === 'generate_api_key') {
        $name = trim($_POST['key_name']);
        $key = bin2hex(random_bytes(32));
        $hash = hash('sha256', $key);

        $stmt = $pdo->prepare("INSERT INTO api_keys (key_hash, name, permissions) VALUES (?, ?, ?)");
        $stmt->execute([$hash, $name, json_encode(['read', 'write'])]);

        $message = "API Key generated: <code>{$key}</code><br><small>Copy this key now - it won't be shown again!</small>";
    }

    if ($action === 'delete_api_key') {
        $id = (int) $_POST['key_id'];
        $pdo->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
        $message = 'API key deleted!';
    }
}

// Get settings
$settings = [];
$rows = $pdo->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get API keys
$apiKeys = $pdo->query("SELECT id, name, created_at FROM api_keys ORDER BY created_at DESC")->fetchAll();
$currentTheme = $settings['theme_mode'] ?? 'auto';
$defaults = getDefaultSettings();
$currentLogo = $settings['site_logo'] ?? $defaults['site_logo'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Settings | <?= htmlspecialchars(getSetting('site_title')) ?> Admin</title>
    <style>
        /* Theme selector */
        .theme-selector { display: flex; gap: 0.5rem; }
        .theme-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 0.375rem; padding: 0.5rem; border: 2px solid var(--border);
            border-radius: 6px; background: var(--bg-card); cursor: pointer;
            transition: all 0.15s; flex: 1; min-width: 0;
        }
        .theme-btn:hover { border-color: var(--primary); }
        .theme-btn.selected { border-color: var(--primary); background: rgba(102, 126, 234, 0.08); }
        .theme-btn input { display: none; }
        .theme-swatch {
            display: flex; width: 32px; height: 18px; border-radius: 4px;
            overflow: hidden; border: 1px solid var(--border);
        }
        .theme-swatch span { flex: 1; }
        .theme-auto .theme-swatch span:first-child { background: #f8fafc; }
        .theme-auto .theme-swatch span:last-child { background: #0f172a; }
        .theme-light .theme-swatch span:first-child { background: #f8fafc; }
        .theme-light .theme-swatch span:last-child { background: #667eea; }
        .theme-dark .theme-swatch span:first-child { background: #0f172a; }
        .theme-dark .theme-swatch span:last-child { background: #667eea; }
        .theme-mc .theme-swatch span:first-child { background: #1B365D; }
        .theme-mc .theme-swatch span:last-child { background: #FF9E1B; }
        .theme-name { font-size: 0.75rem; font-weight: 500; text-align: center; }

        /* Password row */
        .password-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .password-row .form-group { margin-bottom: 0; }

        /* API keys */
        .api-keys-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .api-key-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.5rem 0.75rem; background: var(--bg-card-hover);
            border-radius: 6px; font-size: 0.875rem;
        }
        .api-key-name { font-weight: 500; }
        .api-key-date { color: var(--text-muted); font-size: 0.75rem; margin-left: 0.5rem; }

        @media (max-width: 480px) {
            .theme-selector { flex-wrap: wrap; }
            .theme-btn { flex: 1 1 calc(50% - 0.25rem); }
            .password-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>Settings</h1>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="content-narrow">
                <!-- Site Settings (includes Theme) -->
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="card form-compact">
                        <div class="card-header">
                            <h2>Site Settings</h2>
                        </div>
                        <div class="form-group">
                            <label for="site_title">Title</label>
                            <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? 'MCAI') ?>">
                        </div>
                        <div class="form-group">
                            <label for="site_description">Description</label>
                            <input type="text" id="site_description" name="site_description" value="<?= htmlspecialchars($settings['site_description'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Logo</label>
                            <?php renderAssetImageTrigger('site_logo', $currentLogo, 'Change'); ?>
                        </div>
                        <div class="form-group">
                            <label>Theme</label>
                            <div class="theme-selector">
                                <label class="theme-btn theme-auto <?= $currentTheme === 'auto' ? 'selected' : '' ?>">
                                    <input type="radio" name="theme_mode" value="auto" <?= $currentTheme === 'auto' ? 'checked' : '' ?>>
                                    <span class="theme-swatch"><span></span><span></span></span>
                                    <span class="theme-name">Auto</span>
                                </label>
                                <label class="theme-btn theme-light <?= $currentTheme === 'light' ? 'selected' : '' ?>">
                                    <input type="radio" name="theme_mode" value="light" <?= $currentTheme === 'light' ? 'checked' : '' ?>>
                                    <span class="theme-swatch"><span></span><span></span></span>
                                    <span class="theme-name">Light</span>
                                </label>
                                <label class="theme-btn theme-dark <?= $currentTheme === 'dark' ? 'selected' : '' ?>">
                                    <input type="radio" name="theme_mode" value="dark" <?= $currentTheme === 'dark' ? 'checked' : '' ?>>
                                    <span class="theme-swatch"><span></span><span></span></span>
                                    <span class="theme-name">Dark</span>
                                </label>
                                <label class="theme-btn theme-mc <?= $currentTheme === 'mc' ? 'selected' : '' ?>">
                                    <input type="radio" name="theme_mode" value="mc" <?= $currentTheme === 'mc' ? 'checked' : '' ?>>
                                    <span class="theme-swatch"><span></span><span></span></span>
                                    <span class="theme-name">MC</span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" style="margin-top: 0.75rem;">Save</button>
                    </div>
                </form>

                <!-- Change Password -->
                <div class="card form-compact">
                    <div class="card-header">
                        <h2>Password</h2>
                    </div>
                    <div class="password-row">
                        <div class="form-group">
                            <label for="new_password">New</label>
                            <input type="password" id="new_password" name="new_password" minlength="6" placeholder="Min 6 chars">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat">
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" style="margin-top: 0.75rem;" onclick="updatePassword()">Update</button>
                </div>

                <!-- API Keys -->
                <div class="card form-compact">
                    <div class="card-header">
                        <h2>API Keys</h2>
                        <button type="button" onclick="document.getElementById('apiKeyModal').showModal()" class="btn btn-sm btn-primary">Add</button>
                    </div>
                    <?php if (empty($apiKeys)): ?>
                        <p class="text-muted" style="font-size: 0.8125rem; margin: 0;">No API keys yet.</p>
                    <?php else: ?>
                        <div class="api-keys-list">
                            <?php foreach ($apiKeys as $key): ?>
                            <div class="api-key-item">
                                <div>
                                    <span class="api-key-name"><?= htmlspecialchars($key['name']) ?></span>
                                    <span class="api-key-date"><?= date('M j', strtotime($key['created_at'])) ?></span>
                                </div>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this API key?')">
                                    <input type="hidden" name="action" value="delete_api_key">
                                    <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                    <button type="submit" class="btn-icon-action btn-danger" title="Delete">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Generate API Key Modal -->
            <dialog id="apiKeyModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Generate API Key</h2>
                        <button onclick="document.getElementById('apiKeyModal').close()" class="btn-close">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_api_key">

                        <div class="form-group">
                            <label for="key_name">Key Name</label>
                            <input type="text" id="key_name" name="key_name" required placeholder="e.g., MCP Server">
                        </div>

                        <div class="form-actions">
                            <button type="button" onclick="document.getElementById('apiKeyModal').close()" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Generate</button>
                        </div>
                    </form>
                </div>
            </dialog>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Theme selector visual feedback
        document.querySelectorAll('.theme-btn input').forEach(input => {
            input.addEventListener('change', function() {
                document.querySelectorAll('.theme-btn').forEach(btn => btn.classList.remove('selected'));
                this.closest('.theme-btn').classList.add('selected');
            });
        });

        // Password update via separate form submission
        function updatePassword() {
            const newPwd = document.getElementById('new_password').value;
            const confirmPwd = document.getElementById('confirm_password').value;

            if (!newPwd || newPwd.length < 6) {
                alert('Password must be at least 6 characters');
                return;
            }
            if (newPwd !== confirmPwd) {
                alert('Passwords do not match');
                return;
            }

            // Create a separate form for password update
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="new_password" value="${newPwd}">
                <input type="hidden" name="confirm_password" value="${confirmPwd}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <?php
    // Asset picker styles and modal
    renderAssetPickerStyles();
    renderAssetPickerModal([]);
    ?>
</body>
</html>
