<?php
// 🔒 Require login to access dashboard
require_once 'includes/auth.php';
requireLogin();

require_once "config/database.php";
require_once "includes/audit.php";

// Add custom CSS for Recent Activity scrollbar
echo '<style>
    .activity-timeline {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 5px;
    }

    /* Custom scrollbar styling */
    .activity-timeline::-webkit-scrollbar {
        width: 8px;
    }

    .activity-timeline::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .activity-timeline::-webkit-scrollbar-thumb {
        background: #2CCCD3;
        border-radius: 10px;
    }

    .activity-timeline::-webkit-scrollbar-thumb:hover {
        background: #1fb7b8;
    }

    /* Firefox scrollbar */
    .activity-timeline {
        scrollbar-color: #2CCCD3 #f1f1f1;
        scrollbar-width: thin;
    }

    /* Remove last border-bottom */
    .activity-item:last-child {
        border-bottom: none !important;
    }
</style>';

include "includes/header.php";

// Include soft delete modals
include "includes/soft_delete_modals.php";

// Get current user
$user = getCurrentUser();

// Handle success/error messages
$successMessage = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

try {
    // 📊 Show statistics for ALL *recent* reports in system (exclude Trash)
    $userId = $user['id'];
    // Count only non-deleted (recent) reports
    $totalReportsStmt = $pdo->prepare("SELECT COUNT(*) as count FROM commissioning_reports WHERE is_deleted = FALSE OR is_deleted IS NULL");
    $totalReportsStmt->execute();
    $totalReports = $totalReportsStmt->fetch(PDO::FETCH_ASSOC)["count"];

    // This month's reports (exclude trashed)
    $monthReportsStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM commissioning_reports 
            WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
              AND (is_deleted = FALSE OR is_deleted IS NULL)
        ");
    $monthReportsStmt->execute();
    $monthReports = $monthReportsStmt->fetch(PDO::FETCH_ASSOC)["count"];

    // Total installed power (sum) only from non-deleted reports
    $powerStmt = $pdo->prepare("
            SELECT SUM(CAST(installed_power AS DECIMAL(10, 2))) as total FROM commissioning_reports 
            WHERE installed_power IS NOT NULL AND installed_power > 0
              AND (is_deleted = FALSE OR is_deleted IS NULL)
        ");
    $powerStmt->execute();
    $totalPower = $powerStmt->fetch(PDO::FETCH_ASSOC)["total"] ?? 0;

    // 📋 Show all recent reports from ALL users (excluding deleted)
    $recentStmt = $pdo->prepare("
        SELECT 
            cr.id, 
            cr.project_name, 
            cr.date, 
            cr.installed_power, 
            cr.user_id,
            u.username
        FROM commissioning_reports cr
        LEFT JOIN users u ON cr.user_id = u.id
        WHERE cr.is_deleted = FALSE OR cr.is_deleted IS NULL
        ORDER BY cr.created_at DESC LIMIT 5
    ");
    $recentStmt->execute();
    $recentReports = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get count of deleted reports (for admin)
    $deletedCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM commissioning_reports WHERE is_deleted = TRUE");
    $deletedCountStmt->execute();
    $deletedCount = $deletedCountStmt->fetch(PDO::FETCH_ASSOC)["count"] ?? 0;

    // 📋 Get recent activity log - EVERYONE sees ALL activities
    // (not filtered by user - all users see all actions from all users)
    $activityLogs = getAuditLog(['days' => 7], 20);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $totalReports = 0;
    $monthReports = 0;
    $totalPower = 0;
    $recentReports = [];
    $activityLogs = [];
    $deletedCount = 0;
}
?>
<div class="container-fluid py-5">
    <!-- Display success message -->
    <?php if ($successMessage): ?>
        <div class="container mb-3 main-content-container">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Display error message -->
    <?php if ($errorMessage): ?>
        <div class="container mb-3 main-content-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center gsap-fade-up">
            <div class="mb-4">
                <img src="assets/img/logo-main.png" alt="Cleanwatts Logo" width="300" style="height: auto;">
            </div>
            <h2 class="fw-bold mb-3 gsap-text-reveal" style="font-size: 1.75rem;">PV System Commissioning</h2>
            <p class="lead text-muted mb-4">Professional Solar Photovoltaic Commissioning and Inspection Reports</p>
            <a href="comissionamento.php?new=1" class="btn btn-primary btn-lg me-3 btn-magnetic">
                <i class="fas fa-plus me-2"></i>Create New Report
            </a>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100 gsap-bounce-in card-hover-lift">
                <div class="card-body text-center">
                    <i class="fas fa-file-alt" style="font-size: 2.5rem; color: #2CCCD3;"></i>
                    <h5 class="card-title text-muted">Total Reports</h5>
                    <p class="display-5 fw-bold" data-gsap-counter="<?php echo $totalReports; ?>"><?php echo $totalReports; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100 gsap-bounce-in card-hover-lift">
                <div class="card-body text-center">
                    <i class="fas fa-calendar" style="font-size: 2.5rem; color: #ff6b6b;"></i>
                    <h5 class="card-title text-muted">This Month</h5>
                    <p class="display-5 fw-bold" data-gsap-counter="<?php echo $monthReports; ?>"><?php echo $monthReports; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100 gsap-bounce-in card-hover-lift">
                <div class="card-body text-center">
                    <i class="fas fa-bolt" style="font-size: 2.5rem; color: #ffc107;"></i>
                    <h5 class="card-title text-muted">Total Capacity</h5>
                    <p class="display-5 fw-bold" data-gsap-counter="<?php echo $totalPower; ?>" data-suffix=" kWp"><?php echo number_format($totalPower, 1); ?> kWp</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100 gsap-bounce-in card-hover-lift">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line" style="font-size: 2.5rem; color: #17a2b8;"></i>
                    <h5 class="card-title text-muted">Avg. Power</h5>
                    <p class="display-5 fw-bold" data-gsap-counter="<?php echo $totalReports > 0 ? $totalPower / $totalReports : 0; ?>" data-suffix=" kWp"><?php echo $totalReports > 0 ? number_format($totalPower / $totalReports, 1) : "0"; ?> kWp</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 🎯 PUNCH LIST WIDGET -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="punch-list-widget card">
                <div class="card-body p-4">
                    <div id="punch-list-container">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="text-muted mt-2">Carregando punch lists...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentReports)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Project Name</th>
                                        <th>Created By</th>
                                        <th>Date</th>
                                        <th>Capacity (kWp)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td><span class="badge bg-primary">COM-<?php echo str_pad($report["id"], 5, "0", STR_PAD_LEFT); ?></span></td>
                                            <td><?php echo htmlspecialchars($report["project_name"] ?? "Untitled"); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($report["username"] ?? "Unknown"); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($report["date"] ?? "N/A"); ?></td>
                                            <td><?php echo $report["installed_power"] ? number_format($report["installed_power"], 2) : ""; ?> kWp</td>
                                            <td>
                                                <a href="comissionamento.php?report_id=<?php echo $report["id"]; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit me-1"></i>Edit</a>
                                                <a href="generate_report.php?id=<?php echo $report["id"]; ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf me-1"></i>View</a>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteConfirmation(<?php echo $report['id']; ?>, '<?php echo addslashes($report['project_name']); ?>')"><i class="fas fa-trash me-1"></i>Delete</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top text-center">
                        <a href="index.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-home me-1"></i>Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Activity Widget -->
    <?php if (!empty($activityLogs)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0"><i class="fas fa-scroll me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="activity-item d-flex mb-3 pb-3 border-bottom">
                                    <div class="activity-icon me-3" style="min-width: 40px; text-align: center;">
                                        <?php echo getActionIcon($log['action']); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                            <span class="text-muted"><?php
                                                                        $actionText = '';
                                                                        switch ($log['action']) {
                                                                            case 'user_created':
                                                                                $actionText = 'created account';
                                                                                break;
                                                                            case 'user_deleted':
                                                                                $actionText = 'deleted account';
                                                                                break;
                                                                            case 'user_deactivated':
                                                                                $actionText = 'deactivated account';
                                                                                break;
                                                                            case 'privilege_changed':
                                                                                $actionText = 'changed privilege';
                                                                                break;
                                                                            case 'password_reset':
                                                                                $actionText = 'reset password';
                                                                                break;
                                                                            case 'report_created':
                                                                                $actionText = 'created report';
                                                                                break;
                                                                            case 'report_edited':
                                                                                $actionText = 'edited report';
                                                                                break;
                                                                            case 'report_deleted':
                                                                                $actionText = 'deleted report';
                                                                                break;
                                                                            case 'login':
                                                                                $actionText = 'logged in';
                                                                                break;
                                                                            case 'logout':
                                                                                $actionText = 'logged out';
                                                                                break;
                                                                            default:
                                                                                $actionText = $log['action'];
                                                                        }
                                                                        echo $actionText;
                                                                        ?></span>
                                        </p>
                                        <small class="text-muted">
                                            <?php if (!empty($log['entity_name'])): ?>
                                                <strong><?php echo htmlspecialchars($log['entity_name']); ?></strong>
                                            <?php endif; ?>
                                            <?php echo $log['description'] ? ' · ' . htmlspecialchars($log['description']) : ''; ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo formatTimeAgo($log['timestamp']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>