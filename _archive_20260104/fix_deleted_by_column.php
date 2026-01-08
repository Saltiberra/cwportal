<?php
require 'config/database.php';
require 'includes/auth.php';
requireLogin();

// Only admins can run this fix
if ($_SESSION['role'] !== 'admin') {
    echo '<p>Access denied. Admins only</p>';
    include 'includes/footer.php';
    exit;
}

$queries = [
    "ALTER TABLE site_survey_reports ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "ALTER TABLE site_survey_reports ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL",
    "ALTER TABLE site_survey_reports MODIFY COLUMN project_name VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL",
    "ALTER TABLE site_survey_reports ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at)",
    "ALTER TABLE commissioning_reports ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at)"
];

$results = [];
foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        $results[] = ['query' => $q, 'success' => true];
    } catch (PDOException $e) {
        $results[] = ['query' => $q, 'success' => false, 'error' => $e->getMessage()];
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Database Update: Deleted At Columns</h1>
    <div class="card mb-4">
        <div class="card-body">
            <p>This script attempts to add missing <code>deleted_at</code> and <code>deleted_by</code> columns used by soft-delete features.</p>
            <ul>
                <?php foreach ($results as $r): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($r['query']); ?></strong> —
                        <?php if ($r['success']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Error: <?php echo htmlspecialchars($r['error']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="admin_trash.php" class="btn btn-primary">Back to Trash</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';
