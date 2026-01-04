<?php

/**
 * Trash/Recovery Admin Page
 * View deleted reports and restore or permanently delete them
 */

require_once 'includes/auth.php';
requireLogin();

// Only admins can access trash
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger"><h4>Access Denied</h4>Only administrators can access the trash.</div>';
    include 'includes/footer.php';
    exit;
}

require_once 'config/database.php';
include 'includes/header.php';

// Include soft delete modals
include 'includes/soft_delete_modals.php';

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'commissioning';

    $table = $report_type === 'survey' ? 'site_survey_reports' : 'commissioning_reports';

    if ($action === 'restore' && $report_id) {
        try {
            $stmt = $pdo->prepare("UPDATE {$table} SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->execute([$report_id]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $fallback = $pdo->prepare("UPDATE {$table} SET is_deleted = FALSE WHERE id = ?");
                $fallback->execute([$report_id]);
            } else {
                throw $e;
            }
        }

        // Log the restoration
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';
        $logStmt = $pdo->prepare("
            UPDATE deletion_log 
            SET restored_by = ?, restored_at = NOW(), restored_user_name = ?
            WHERE report_id = ? AND report_type = ? AND restored_at IS NULL
            LIMIT 1
        ");
        $logStmt->execute([$userId, $userName, $report_id, $report_type]);

        echo '<div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>✓ ' . ucfirst($report_type) . ' restored successfully</div>';
    } elseif ($action === 'delete_permanent' && $report_id) {
        // Log the permanent deletion
        $userId = $_SESSION['user_id'] ?? null;

        $logStmt = $pdo->prepare("
            UPDATE deletion_log 
            SET permanently_deleted = TRUE, deleted_forever_by = ?, deleted_forever_at = NOW()
            WHERE report_id = ? AND report_type = ? AND deleted_forever_at IS NULL
            LIMIT 1
        ");
        $logStmt->execute([$userId, $report_id, $report_type]);

        // Delete related records first (only for commissioning reports)
        if ($report_type === 'commissioning') {
            $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ?")->execute([$report_id]);
            $pdo->prepare("DELETE FROM report_drafts WHERE report_id = ?")->execute([$report_id]);
        }

        // Then delete the report
        $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$report_id]);
        echo '<div class="alert alert-warning alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>✓ ' . ucfirst($report_type) . ' permanently deleted</div>';
    }
}

// Fetch deleted reports
try {
    $stmt = $pdo->query("
        SELECT 
            cr.id, 
            cr.project_name, 
            cr.date, 
            cr.created_at, 
            cr.deleted_at, 
            cr.deleted_by,
            cr.user_id,
            u_creator.full_name as creator_name,
            u_deleter.full_name as deleter_name,
            'commissioning' as type
        FROM commissioning_reports cr
        LEFT JOIN users u_creator ON cr.user_id = u_creator.id
        LEFT JOIN users u_deleter ON cr.deleted_by = u_deleter.id
        WHERE cr.is_deleted = TRUE
        UNION ALL
        SELECT 
            ssr.id, 
            ssr.project_name, 
            ssr.date, 
            ssr.created_at, 
            ssr.deleted_at, 
            ssr.deleted_by,
            ssr.user_id,
            u_creator_ssr.full_name as creator_name,
            u_deleter_ssr.full_name as deleter_name,
            'survey' as type
        FROM site_survey_reports ssr
        LEFT JOIN users u_creator_ssr ON ssr.user_id = u_creator_ssr.id
        LEFT JOIN users u_deleter_ssr ON ssr.deleted_by = u_deleter_ssr.id
        WHERE ssr.is_deleted = TRUE
        ORDER BY deleted_at DESC
    ");
    $deletedReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If deleted_by column doesn't exist, try simpler query
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo '<div class="alert alert-warning alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <strong>⚠️ Database Setup Required</strong><br>
            The system needs to add missing columns. <a href="fix_deleted_by_column.php" class="alert-link">Click here to fix automatically</a>
        </div>';
        $deletedReports = [];
    } else {
        echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $deletedReports = [];
    }
}

?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">
                <i class="fas fa-trash me-2"></i>Trash
            </h1>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-warning me-2"></i>Deleted Reports & Surveys (<?php echo count($deletedReports); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($deletedReports)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-trash-alt fa-3x mb-3" style="opacity:0.3;"></i>
                        <p>No deleted reports in trash</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>ID</th>
                                    <th>Project Name</th>
                                    <th>Date</th>
                                    <th>Creator</th>
                                    <th>Deleted By</th>
                                    <th>Deleted At</th>
                                    <th>Time Ago</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletedReports as $report): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $report['type'] === 'survey' ? 'info' : 'primary'; ?>">
                                                <?php echo ucfirst($report['type']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo strtoupper(substr($report['type'], 0, 3)); ?>-<?php echo str_pad($report['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($report['project_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($report['date'])); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($report['creator_name'] ?? 'Unknown'); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-danger font-weight-bold">
                                                <i class="fas fa-user-slash me-1"></i><?php echo htmlspecialchars($report['deleter_name'] ?? 'Unknown'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($report['deleted_at']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('Y-m-d H:i', strtotime($report['deleted_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="badge bg-secondary">
                                                <?php
                                                $deletedTime = strtotime($report['deleted_at']);
                                                $now = time();
                                                $diff = $now - $deletedTime;

                                                if ($diff < 60) {
                                                    echo $diff . ' seconds ago';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . ' minutes ago';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } else {
                                                    echo floor($diff / 86400) . ' days ago';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success me-2" onclick="showRestoreConfirmation(<?php echo $report['id']; ?>, '<?php echo addslashes(htmlspecialchars($report['project_name'])); ?>', '<?php echo $report['type']; ?>')">
                                                <i class="fas fa-undo me-1"></i>Restore
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="showPermanentDeleteConfirmation(<?php echo $report['id']; ?>, '<?php echo addslashes(htmlspecialchars($report['project_name'])); ?>', '<?php echo $report['type']; ?>')">
                                                <i class="fas fa-times me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Info:</strong> Reports in trash are kept for 90 days before automatic purge.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>