<?php
/**
 * Database Manager - browse tables, rows, structure, and run SQL
 * Protected by admin login
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$message = '';
$error = '';

// Get list of valid tables
$validTables = [];
$tablesStmt = $pdo->query('SHOW TABLES');
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $validTables[] = $row[0];
}

// Determine current view
$table = $_GET['table'] ?? null;
$viewSql = isset($_GET['sql']);
$viewStructure = isset($_GET['structure']);
$viewEdit = isset($_GET['edit']);
$viewInsert = isset($_GET['insert']);
$deleteId = $_GET['delete'] ?? null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Validate table name
if ($table !== null && !in_array($table, $validTables, true)) {
    $error = "Table not found: " . htmlspecialchars($table);
    $table = null;
}

// ── DELETE action ──
if ($table && $deleteId !== null) {
    try {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
        $pk = $cols[0]['Field'];
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$deleteId]);
        $message = "Deleted row ($pk = " . htmlspecialchars($deleteId) . ")";
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
    // Redirect to remove delete param
    if (!$error) {
        header("Location: " . url("/admin/database.php?table=" . urlencode($table)));
        exit;
    }
}

// ── INSERT action (POST) ──
if ($table && $viewInsert && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($cols as $col) {
            $name = $col['Field'];
            // Skip auto-increment columns
            if (stripos($col['Extra'], 'auto_increment') !== false) continue;
            if (isset($_POST[$name])) {
                $fields[] = "`$name`";
                $placeholders[] = '?';
                $values[] = $_POST[$name] === '' ? null : $_POST[$name];
            }
        }
        if ($fields) {
            $sql = "INSERT INTO `$table` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "Row inserted (ID: " . $pdo->lastInsertId() . ")";
            $viewInsert = false; // Show browse view after insert
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// ── EDIT/UPDATE action (POST) ──
if ($table && $viewEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
        $pk = $cols[0]['Field'];
        $editId = $_GET['edit'];
        $sets = [];
        $values = [];
        foreach ($cols as $col) {
            $name = $col['Field'];
            if ($name === $pk) continue;
            if (isset($_POST[$name])) {
                $sets[] = "`$name` = ?";
                $values[] = $_POST[$name] === '' ? null : $_POST[$name];
            }
        }
        $values[] = $editId;
        if ($sets) {
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = "Row updated successfully";
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// ── SQL execution ──
$sqlQuery = '';
$sqlResult = null;
if ($viewSql && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) {
    $sqlQuery = trim($_POST['query']);
    $queryType = strtoupper(strtok($sqlQuery, " \t\n\r"));
    try {
        if (in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'])) {
            $stmt = $pdo->query($sqlQuery);
            $sqlResult = ['type' => 'select', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } elseif ($queryType === 'INSERT') {
            $pdo->exec($sqlQuery);
            $sqlResult = ['type' => 'insert', 'lastId' => $pdo->lastInsertId()];
        } elseif (in_array($queryType, ['UPDATE', 'DELETE'])) {
            $count = $pdo->exec($sqlQuery);
            $sqlResult = ['type' => strtolower($queryType), 'affected' => $count];
        } else {
            $pdo->exec($sqlQuery);
            $sqlResult = ['type' => 'ddl', 'message' => 'Executed OK'];
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Database | <?= htmlspecialchars(getSetting('site_title')) ?> Admin</title>
    <style>
        .db-tabs { display: flex; gap: 0.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .db-tab {
            padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none;
            font-size: 0.875rem; font-weight: 500; color: var(--text-secondary);
            background: transparent; transition: all 0.15s;
        }
        .db-tab:hover { color: var(--text-primary); background: var(--bg-card-hover); }
        .db-tab.active { background: var(--bg-card-hover); color: var(--primary); }
        .db-actions { display: flex; gap: 0.375rem; }
        .db-actions .btn { padding: 0.25rem 0.625rem; font-size: 0.75rem; }
        .btn-sm { padding: 0.25rem 0.625rem; font-size: 0.75rem; }
        .btn-danger { background: #ef4444; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: var(--bg-card-hover); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; text-decoration: none; }
        .btn-secondary:hover { background: var(--border-color); }
        .truncated { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
        .pagination { display: flex; gap: 0.5rem; margin-top: 1rem; align-items: center; }
        .pagination a, .pagination span {
            padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.875rem;
            text-decoration: none; color: var(--text-secondary);
        }
        .pagination a { background: var(--bg-card-hover); }
        .pagination a:hover { color: var(--text-primary); }
        .pagination .current { background: var(--primary); color: #fff; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-success { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
        .alert-error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .sql-textarea {
            font-family: monospace; width: 100%; padding: 0.75rem;
            background: var(--bg-main); color: var(--text-primary);
            border: 1px solid var(--border-color); border-radius: 6px;
            resize: vertical; min-height: 120px;
        }
        .form-input {
            width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-main);
            color: var(--text-primary); border: 1px solid var(--border-color);
            border-radius: 6px; font-size: 0.875rem;
        }
        .form-input:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-textarea {
            width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-main);
            color: var(--text-primary); border: 1px solid var(--border-color);
            border-radius: 6px; font-size: 0.875rem; font-family: monospace;
            resize: vertical; min-height: 80px;
        }
        .table td { font-family: monospace; font-size: 0.8125rem; }
        .table th { white-space: nowrap; }
        .row-count { color: var(--text-muted); font-size: 0.8125rem; }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1>Database</h1>
            </header>

            <div class="admin-content" style="padding: 0 1.5rem 1.5rem;">

                <!-- Tab Navigation -->
                <div class="db-tabs">
                    <a href="<?= url('/admin/database.php') ?>" class="db-tab <?= !$table && !$viewSql ? 'active' : '' ?>">Tables</a>
                    <?php if ($table): ?>
                        <a href="<?= url('/admin/database.php?table=' . urlencode($table)) ?>" class="db-tab <?= $table && !$viewStructure && !$viewEdit && !$viewInsert ? 'active' : '' ?>">Browse</a>
                        <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&structure=1') ?>" class="db-tab <?= $viewStructure ? 'active' : '' ?>">Structure</a>
                        <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&insert=1') ?>" class="db-tab <?= $viewInsert ? 'active' : '' ?>">Insert</a>
                    <?php endif; ?>
                    <a href="<?= url('/admin/database.php?sql=1') ?>" class="db-tab <?= $viewSql ? 'active' : '' ?>">SQL</a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

<?php if ($viewSql): ?>
<!-- ══════════════════════════ SQL VIEW ══════════════════════════ -->
<div class="card">
    <form method="POST">
        <div class="form-group">
            <label for="query" style="font-weight:600;margin-bottom:0.5rem;display:block;">SQL Query</label>
            <textarea id="query" name="query" class="sql-textarea" placeholder="SELECT * FROM bookmarks"><?= htmlspecialchars($sqlQuery) ?></textarea>
        </div>
        <div style="margin-top:0.75rem;">
            <button type="submit" class="btn btn-primary">Run Query</button>
            <span style="margin-left:1rem;color:var(--text-muted);font-size:0.85rem;">Ctrl+Enter to submit</span>
        </div>
    </form>
</div>

<?php if ($sqlResult): ?>
<div class="card" style="margin-top:1rem;">
    <?php if ($sqlResult['type'] === 'select' && !empty($sqlResult['rows'])): ?>
        <p style="margin-bottom:0.75rem;color:var(--text-muted);"><?= count($sqlResult['rows']) ?> row(s)</p>
        <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <?php foreach (array_keys($sqlResult['rows'][0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
                <?php foreach ($sqlResult['rows'] as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars($val ?? 'NULL') ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php elseif ($sqlResult['type'] === 'select'): ?>
        <p style="color:var(--text-muted);">No rows returned</p>
    <?php elseif ($sqlResult['type'] === 'insert'): ?>
        <p>Inserted OK &mdash; Last ID: <strong><?= $sqlResult['lastId'] ?></strong></p>
    <?php elseif (in_array($sqlResult['type'], ['update', 'delete'])): ?>
        <p><?= ucfirst($sqlResult['type']) ?> OK &mdash; <strong><?= $sqlResult['affected'] ?></strong> row(s) affected</p>
    <?php else: ?>
        <p style="color:#22c55e;"><?= htmlspecialchars($sqlResult['message']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('query').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') this.form.submit();
});
</script>

<?php elseif ($table && $viewStructure): ?>
<!-- ══════════════════════════ STRUCTURE VIEW ══════════════════════════ -->
<?php
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
?>
<div class="card">
    <h2 style="margin-bottom:1rem;">Structure: <?= htmlspecialchars($table) ?></h2>
    <div class="table-responsive">
    <table class="table">
        <thead><tr>
            <th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>
        </tr></thead>
        <tbody>
            <?php foreach ($cols as $col): ?>
            <tr>
                <td><?= htmlspecialchars($col['Field']) ?></td>
                <td><?= htmlspecialchars($col['Type']) ?></td>
                <td><?= htmlspecialchars($col['Null']) ?></td>
                <td><?= htmlspecialchars($col['Key']) ?></td>
                <td><?= htmlspecialchars($col['Default'] ?? 'NULL') ?></td>
                <td><?= htmlspecialchars($col['Extra']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php elseif ($table && $viewEdit): ?>
<!-- ══════════════════════════ EDIT VIEW ══════════════════════════ -->
<?php
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
    $pk = $cols[0]['Field'];
    $editId = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch();
    if (!$row) {
        echo '<div class="alert alert-error">Row not found</div>';
    } else {
?>
<div class="card">
    <h2 style="margin-bottom:1rem;">Edit row in <?= htmlspecialchars($table) ?></h2>
    <form method="POST">
        <?php foreach ($cols as $col):
            $name = $col['Field'];
            $val = $row[$name] ?? '';
            $isText = (stripos($col['Type'], 'text') !== false || stripos($col['Type'], 'blob') !== false);
        ?>
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="font-weight:600;font-size:0.875rem;display:block;margin-bottom:0.25rem;">
                <?= htmlspecialchars($name) ?>
                <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;margin-left:0.5rem;"><?= htmlspecialchars($col['Type']) ?></span>
            </label>
            <?php if ($isText): ?>
                <textarea name="<?= htmlspecialchars($name) ?>" class="form-textarea"><?= htmlspecialchars($val) ?></textarea>
            <?php else: ?>
                <input type="text" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($val) ?>" class="form-input"
                    <?= $name === $pk ? 'disabled' : '' ?>>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('/admin/database.php?table=' . urlencode($table)) ?>" class="btn btn-secondary" style="display:inline-flex;align-items:center;">Cancel</a>
        </div>
    </form>
</div>
<?php } ?>

<?php elseif ($table && $viewInsert): ?>
<!-- ══════════════════════════ INSERT VIEW ══════════════════════════ -->
<?php
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
?>
<div class="card">
    <h2 style="margin-bottom:1rem;">Insert into <?= htmlspecialchars($table) ?></h2>
    <form method="POST">
        <?php foreach ($cols as $col):
            $name = $col['Field'];
            $isAuto = stripos($col['Extra'], 'auto_increment') !== false;
            $isText = (stripos($col['Type'], 'text') !== false || stripos($col['Type'], 'blob') !== false);
        ?>
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="font-weight:600;font-size:0.875rem;display:block;margin-bottom:0.25rem;">
                <?= htmlspecialchars($name) ?>
                <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;margin-left:0.5rem;"><?= htmlspecialchars($col['Type']) ?><?= $isAuto ? ' (auto)' : '' ?></span>
            </label>
            <?php if ($isText): ?>
                <textarea name="<?= htmlspecialchars($name) ?>" class="form-textarea" <?= $isAuto ? 'disabled' : '' ?>></textarea>
            <?php else: ?>
                <input type="text" name="<?= htmlspecialchars($name) ?>" class="form-input"
                    <?= $isAuto ? 'disabled' : '' ?>
                    placeholder="<?= htmlspecialchars($col['Default'] ?? '') ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Insert Row</button>
            <a href="<?= url('/admin/database.php?table=' . urlencode($table)) ?>" class="btn btn-secondary" style="display:inline-flex;align-items:center;margin-left:0.5rem;">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($table): ?>
<!-- ══════════════════════════ BROWSE VIEW ══════════════════════════ -->
<?php
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
    $pk = $cols[0]['Field'];
    $colNames = array_column($cols, 'Field');

    // Count total rows
    $total = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT * FROM `$table` LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
        <h2><?= htmlspecialchars($table) ?> <span class="row-count">(<?= $total ?> rows)</span></h2>
        <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&insert=1') ?>" class="btn btn-primary btn-sm">+ Insert Row</a>
    </div>

    <?php if (empty($rows)): ?>
        <p style="color:var(--text-muted);">No rows in this table.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table">
        <thead><tr>
            <?php foreach ($colNames as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($colNames as $col):
                    $val = $row[$col] ?? 'NULL';
                    $display = mb_strlen((string)$val) > 50 ? mb_substr((string)$val, 0, 50) . '...' : $val;
                ?>
                    <td><span class="truncated" title="<?= htmlspecialchars((string)$val) ?>"><?= htmlspecialchars((string)$display) ?></span></td>
                <?php endforeach; ?>
                <td>
                    <div class="db-actions">
                        <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&edit=' . urlencode($row[$pk])) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&delete=' . urlencode($row[$pk])) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this row (<?= htmlspecialchars($pk) ?> = <?= htmlspecialchars($row[$pk]) ?>)?')">Delete</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&page=' . ($page - 1)) ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php
            $start = max(1, $page - 3);
            $end = min($totalPages, $page + 3);
            for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&page=' . $i) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= url('/admin/database.php?table=' . urlencode($table) . '&page=' . ($page + 1)) ?>">Next &raquo;</a>
        <?php endif; ?>
        <span class="row-count" style="margin-left:0.5rem;">Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ══════════════════════════ TABLES LIST VIEW ══════════════════════════ -->
<div class="card">
    <h2 style="margin-bottom:1rem;">Tables</h2>
    <div class="table-responsive">
    <table class="table">
        <thead><tr>
            <th>Table Name</th>
            <th>Rows</th>
            <th>Actions</th>
        </tr></thead>
        <tbody>
            <?php foreach ($validTables as $t):
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            ?>
            <tr>
                <td><a href="<?= url('/admin/database.php?table=' . urlencode($t)) ?>" style="color:var(--primary);text-decoration:none;font-weight:500;"><?= htmlspecialchars($t) ?></a></td>
                <td class="row-count"><?= $count ?></td>
                <td>
                    <div class="db-actions">
                        <a href="<?= url('/admin/database.php?table=' . urlencode($t)) ?>" class="btn btn-secondary btn-sm">Browse</a>
                        <a href="<?= url('/admin/database.php?table=' . urlencode($t) . '&structure=1') ?>" class="btn btn-secondary btn-sm">Structure</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

            </div>
        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
