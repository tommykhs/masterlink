<?php
/**
 * Contacts Management
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/asset-picker.php';

requireLogin();

$pdo = getDB();
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reorder') {
        $orders = json_decode($_POST['orders'], true);
        $stmt = $pdo->prepare("UPDATE contacts SET sort_order = ? WHERE id = ?");
        foreach ($orders as $order) {
            $stmt->execute([$order['sort_order'], $order['id']]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_contact') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        echo json_encode(['success' => true, 'contact' => $contact]);
        exit;
    }

    if ($action === 'update_icon') {
        $id = (int)$_POST['id'];
        $iconType = $_POST['icon_type'];
        $iconValue = trim($_POST['icon_value']);
        $pdo->prepare("UPDATE contacts SET icon_type = ?, icon_value = ? WHERE id = ?")->execute([$iconType, $iconValue, $id]);
        echo json_encode(['success' => true, 'icon_type' => $iconType, 'icon_value' => $iconValue]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $maxOrder = $pdo->query("SELECT MAX(sort_order) FROM contacts")->fetchColumn();
        $sortOrder = ($maxOrder ?? -1) + 1;

        $data = [
            'name' => trim($_POST['name']),
            'url' => trim($_POST['url']),
            'icon_type' => $_POST['icon_type'],
            'icon_value' => trim($_POST['icon_value']),
            'sort_order' => $sortOrder,
        ];

        try {
            $stmt = $pdo->prepare("INSERT INTO contacts (name, url, icon_type, icon_value, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
            $message = 'Contact created!';
            header('Location: contacts.php?msg=created');
            exit;
        } catch (PDOException $e) {
            $error = 'Error creating contact: ' . $e->getMessage();
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $data = [
            'name' => trim($_POST['name']),
            'url' => trim($_POST['url']),
            'icon_type' => $_POST['icon_type'],
            'icon_value' => trim($_POST['icon_value']),
        ];

        try {
            $stmt = $pdo->prepare("UPDATE contacts SET name=?, url=?, icon_type=?, icon_value=? WHERE id=?");
            $stmt->execute([...array_values($data), $id]);
            $message = 'Contact updated!';
            header('Location: contacts.php?msg=updated');
            exit;
        } catch (PDOException $e) {
            $error = 'Error updating contact: ' . $e->getMessage();
        }
    }
}

// Handle message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'created' ? 'Contact created!' : 'Contact updated!';
}

// Get all contacts
$contacts = getContacts();

// Check modes
$editContact = isset($_GET['edit']) ? $pdo->query("SELECT * FROM contacts WHERE id = " . (int)$_GET['edit'])->fetch() : null;
$createMode = isset($_GET['create']);
$sortMode = isset($_GET['sort']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Contacts | <?= htmlspecialchars($siteTitle) ?> Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        /* Contact icon clickable */
        .contact-icon { width: 32px; height: 32px; min-width: 32px; display: flex; align-items: center; justify-content: center; background: var(--bg-card-hover); border-radius: 6px; cursor: pointer; transition: all 0.15s; }
        .contact-icon:hover { background: var(--primary-light); }
        .contact-icon i { width: 18px; height: 18px; color: var(--icon-color); }
        .contact-icon img { width: 24px; height: 24px; object-fit: contain; border-radius: 4px; }

        /* Contact URL truncation */
        .contact-url { font-size: 0.8125rem; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .contact-url a { color: inherit; text-decoration: none; }
        .contact-url a:hover { text-decoration: underline; }

        /* Contact row info - name and URL stacked */
        .contact-info { display: flex; flex-direction: column; gap: 0.125rem; }
        .contact-name { font-weight: 500; }

        /* Show mobile URL only on small screens */
        .show-mobile-only { display: none; }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .contact-icon { width: 28px; height: 28px; min-width: 28px; }
            .contact-icon i { width: 16px; height: 16px; }
            .contact-icon img { width: 20px; height: 20px; }
            .contact-url { max-width: 140px; font-size: 0.6875rem; }
            .contact-name { font-size: 0.75rem; }
            .show-mobile-only { display: block; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1><?= $editContact ? 'Edit Contact' : ($createMode ? 'Add New Contact' : 'Contacts') ?></h1>
                <?php if (!$editContact && !$createMode): ?>
                    <div class="header-buttons">
                        <?php if ($sortMode): ?>
                            <a href="contacts.php" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="check" class="btn-icon-only"></i>
                                <span class="btn-text">Done Sorting</span>
                            </a>
                        <?php else: ?>
                            <a href="?sort=1" class="btn btn-icon-mobile">
                                <i data-lucide="grip-vertical"></i>
                                <span class="btn-text">Sort Order</span>
                            </a>
                            <a href="?create=1" class="btn btn-primary btn-icon-mobile">
                                <i data-lucide="plus"></i>
                                <span class="btn-text">Add Contact</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($editContact): ?>
                <!-- Edit Form -->
                <div class="card">
                    <form method="POST" id="editForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $editContact['id'] ?>">

                        <div class="form-header">
                            <h2>Contact Details</h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($editContact['name']) ?>" required placeholder="e.g. LinkedIn">
                            </div>
                            <div class="form-group">
                                <label>Icon</label>
                                <?php renderAssetIconTrigger('edit_icon', $editContact['icon_type'], $editContact['icon_value']); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="url">URL *</label>
                            <input type="text" id="url" name="url" value="<?= htmlspecialchars($editContact['url']) ?>" required placeholder="https://...">
                        </div>

                        <div class="form-actions">
                            <a href="<?= url('/admin/contacts.php') ?>" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($createMode): ?>
                <!-- Create Form -->
                <div class="card">
                    <form method="POST" id="createForm">
                        <input type="hidden" name="action" value="create">

                        <div class="form-header">
                            <h2>Contact Details</h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required placeholder="e.g. LinkedIn">
                            </div>
                            <div class="form-group">
                                <label>Icon</label>
                                <?php renderAssetIconTrigger('icon', 'library', 'lucide:globe'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="url">URL *</label>
                            <input type="text" id="url" name="url" required placeholder="https://...">
                        </div>

                        <div class="form-actions">
                            <a href="<?= url('/admin/contacts.php') ?>" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Contact</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <?php if ($sortMode): ?>
                    <div class="sort-banner">
                        <p><i data-lucide="info" style="width:16px;height:16px;vertical-align:middle;margin-right:0.5rem;"></i> Drag rows to reorder. Click "Done Sorting" when finished.</p>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <?php if (empty($contacts)): ?>
                        <div class="empty-state">
                            <i data-lucide="contact"></i>
                            <p>No contacts yet</p>
                            <a href="?create=1" class="btn btn-primary">Add Contact</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="table" id="contactsTable">
                            <thead>
                                <tr>
                                    <?php if ($sortMode): ?><th style="width:40px"></th><?php endif; ?>
                                    <th>Contact</th>
                                    <th class="hide-mobile">URL</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sortableBody">
                                <?php foreach ($contacts as $contact): ?>
                                <tr class="<?= $sortMode ? 'sortable-row' : '' ?>" data-id="<?= $contact['id'] ?>">
                                    <?php if ($sortMode): ?>
                                    <td class="drag-handle">
                                        <i data-lucide="grip-vertical"></i>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div class="contact-icon" id="iconPreview_<?= $contact['id'] ?>" onclick="openQuickIconPicker(<?= $contact['id'] ?>, this)" title="Click to change icon">
                                                <?php if ($contact['icon_type'] === 'library'): ?>
                                                    <i data-lucide="<?= str_replace('lucide:', '', htmlspecialchars($contact['icon_value'])) ?>"></i>
                                                <?php else: ?>
                                                    <img src="<?= htmlspecialchars($contact['icon_value']) ?>" alt="">
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" id="contact_icon_<?= $contact['id'] ?>_type" value="<?= htmlspecialchars($contact['icon_type']) ?>">
                                            <input type="hidden" id="contact_icon_<?= $contact['id'] ?>_value" value="<?= htmlspecialchars($contact['icon_value']) ?>">
                                            <div class="contact-info">
                                                <span class="contact-name"><?= htmlspecialchars($contact['name']) ?></span>
                                                <div class="contact-url show-mobile-only">
                                                    <a href="<?= htmlspecialchars($contact['url']) ?>" target="_blank"><?= preg_replace('#^https?://#', '', $contact['url']) ?></a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hide-mobile">
                                        <div class="contact-url">
                                            <a href="<?= htmlspecialchars($contact['url']) ?>" target="_blank"><?= htmlspecialchars($contact['url']) ?></a>
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="?edit=<?= $contact['id'] ?>" class="btn-icon-action" title="Edit">
                                            <i data-lucide="pencil"></i>
                                        </a>
                                        <button class="btn-icon-action btn-danger" onclick="deleteContact(<?= $contact['id'] ?>, '<?= htmlspecialchars($contact['name'], ENT_QUOTES) ?>')" title="Delete">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php
    // Asset picker styles and modal
    renderAssetPickerStyles();
    renderAssetPickerModal($popularIcons);
    ?>

    <script>
        lucide.createIcons();

        // Delete contact
        function deleteContact(id, name) {
            if (!confirm(`Delete "${name}"?`)) return;
            fetch('contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=delete&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`tr[data-id="${id}"]`).remove();
                }
            });
        }

        // Quick icon picker for table rows
        function openQuickIconPicker(contactId, element) {
            const typeInput = document.getElementById(`contact_icon_${contactId}_type`);
            const valueInput = document.getElementById(`contact_icon_${contactId}_value`);
            const current = {
                type: typeInput?.value || 'library',
                value: valueInput?.value || ''
            };

            openAssetPicker('icon', result => {
                // Save to server
                const iconValue = result.type === 'library' ? result.value.replace('lucide:', '') : result.value;
                saveQuickIcon(contactId, result.type, iconValue);

                // Update hidden inputs
                if (typeInput) typeInput.value = result.type;
                if (valueInput) valueInput.value = iconValue;

                // Update the preview in the table
                if (result.type === 'library') {
                    const iconName = result.value.replace('lucide:', '');
                    element.innerHTML = `<i data-lucide="${iconName}"></i>`;
                } else {
                    element.innerHTML = `<img src="${result.value}" alt="">`;
                }
                lucide.createIcons();
            }, current, element);
        }

        function saveQuickIcon(contactId, iconType, iconValue) {
            fetch('contacts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=update_icon&id=${contactId}&icon_type=${encodeURIComponent(iconType)}&icon_value=${encodeURIComponent(iconValue)}`
            });
        }

        <?php if ($sortMode): ?>
        // Drag and drop sorting with SortableJS
        document.addEventListener('DOMContentLoaded', function() {
            new Sortable(document.getElementById('sortableBody'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'dragging',
                onEnd: function() {
                    const rows = document.querySelectorAll('#sortableBody .sortable-row');
                    const orders = Array.from(rows).map((row, index) => ({
                        id: row.dataset.id,
                        sort_order: index
                    }));

                    fetch('contacts.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=reorder&orders=${encodeURIComponent(JSON.stringify(orders))}`
                    });
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
