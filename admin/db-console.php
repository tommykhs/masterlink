<?php
/**
 * Database Console - runs SQL from browser form
 * Protected by admin login
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$result = null;
$error = null;
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) {
    $query = trim($_POST['query']);
    $queryType = strtoupper(strtok($query, " \t\n\r"));

    try {
        $pdo = getDB();

        if (in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'])) {
            $stmt = $pdo->query($query);
            $result = ['type' => 'select', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } elseif ($queryType === 'INSERT') {
            $pdo->exec($query);
            $result = ['type' => 'insert', 'lastId' => $pdo->lastInsertId()];
        } elseif (in_array($queryType, ['UPDATE', 'DELETE'])) {
            $count = $pdo->exec($query);
            $result = ['type' => strtolower($queryType), 'affected' => $count];
        } else {
            $pdo->exec($query);
            $result = ['type' => 'ddl', 'message' => 'Executed OK'];
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="content-header">
    <h1>Database Console</h1>
</div>

<div class="card">
    <form method="POST">
        <div class="form-group">
            <label for="query">SQL Query</label>
            <textarea id="query" name="query" rows="5" style="font-family:monospace;width:100%;padding:0.75rem;background:var(--bg-main);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;resize:vertical;" placeholder="SELECT * FROM bookmarks"><?= htmlspecialchars($query) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Run Query</button>
        <span style="margin-left:1rem;color:var(--text-muted);font-size:0.85rem;">Ctrl+Enter to submit</span>
    </form>
</div>

<?php if ($error): ?>
<div class="card" style="border-color:#ef4444;margin-top:1rem;">
    <p style="color:#ef4444;font-family:monospace;"><?= htmlspecialchars($error) ?></p>
</div>
<?php endif; ?>

<?php if ($result): ?>
<div class="card" style="margin-top:1rem;">
    <?php if ($result['type'] === 'select' && !empty($result['rows'])): ?>
        <p style="margin-bottom:0.75rem;color:var(--text-muted);"><?= count($result['rows']) ?> row(s)</p>
        <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <?php foreach (array_keys($result['rows'][0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($val ?? 'NULL') ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php elseif ($result['type'] === 'select'): ?>
        <p style="color:var(--text-muted);">No rows returned</p>
    <?php elseif ($result['type'] === 'insert'): ?>
        <p>Inserted OK — Last ID: <strong><?= $result['lastId'] ?></strong></p>
    <?php elseif (in_array($result['type'], ['update', 'delete'])): ?>
        <p><?= ucfirst($result['type']) ?> OK — <strong><?= $result['affected'] ?></strong> row(s) affected</p>
    <?php else: ?>
        <p style="color:#22c55e;"><?= htmlspecialchars($result['message']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('query').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') this.form.submit();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
