<?php
// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();

require_once 'config/database.php';
require_once 'includes/audit.php';

include 'includes/header.php';

$user = getCurrentUser();
$successMessage = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

try {
    // Stats for Site Surveys (exclude deleted)
    $totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM site_survey_reports WHERE is_deleted = FALSE OR is_deleted IS NULL");
    $totalStmt->execute();
    $totalSurveys = (int)($totalStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $monthStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM site_survey_reports WHERE (is_deleted = FALSE OR is_deleted IS NULL) AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    $monthStmt->execute();
    $monthSurveys = (int)($monthStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $recentStmt = $pdo->prepare("SELECT ssr.id, ssr.project_name, ssr.date, ssr.location, ssr.user_id, u.username
                                 FROM site_survey_reports ssr
                                 LEFT JOIN users u ON ssr.user_id = u.id
                                 WHERE ssr.is_deleted = FALSE OR ssr.is_deleted IS NULL
                                 ORDER BY ssr.created_at DESC LIMIT 5");
    $recentStmt->execute();
    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    $activityLogs = getAuditLog(['days' => 7], 20);
} catch (Exception $e) {
    error_log('[SiteSurvey] Dashboard error: ' . $e->getMessage());
    $totalSurveys = 0;
    $monthSurveys = 0;
    $recent = [];
    $activityLogs = [];
}
?>
<div class="container-fluid py-5">
    <?php if ($successMessage): ?>
        <div class="container mb-3 main-content-container">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="container mb-3 main-content-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center">
            <div class="mb-4">
                <img src="assets/img/logo-main.png" alt="Cleanwatts Logo" width="300" style="height: auto;">
            </div>
            <h2 class="fw-bold mb-3" style="font-size: 1.75rem;">PV Site Survey</h2>
            <p class="lead text-muted mb-4">Pre-installation site assessment and records</p>
            <a href="site_survey.php" class="btn btn-primary btn-lg me-3">
                <i class="fas fa-plus me-2"></i>Create New Survey
            </a>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-clipboard-list" style="font-size: 2.5rem; color: #2CCCD3;"></i>
                    <h5 class="card-title text-muted">Total Surveys</h5>
                    <p class="display-5 fw-bold"><?php echo $totalSurveys; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calendar" style="font-size: 2.5rem; color: #ff6b6b;"></i>
                    <h5 class="card-title text-muted">This Month</h5>
                    <p class="display-5 fw-bold"><?php echo $monthSurveys; ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recent)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Surveys</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Project</th>
                                        <th>Created By</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $row): ?>
                                        <tr>
                                            <td><span class="badge bg-primary">SUR-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['project_name'] ?? 'Untitled'); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['date'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['location'] ?? ''); ?></td>
                                            <td>
                                                <a href="site_survey.php?survey_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="generate_survey_report_new.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Report" aria-label="Report"><i class="fas fa-file-pdf"></i></a>
                                                <?php if ($user['role'] === 'admin' || $row['user_id'] == $user['id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteSurveyConfirmation(<?php echo $row['id']; ?>, '<?php echo addslashes($row['project_name']); ?>')" title="Delete" aria-label="Delete"><i class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top text-center">
                        <a href="survey_index.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-home me-1"></i>Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    try {
        $activityLogs = getAuditLog(['days' => 7], 20);
    } catch (Exception $e) {
        $activityLogs = [];
    }
    include 'includes/activity_timeline.php';
    ?>
</div>

<script>
    function showDeleteSurveyConfirmation(surveyId, projectName) {
        if (!confirm('Are you sure you want to move the survey "' + projectName + '" to the trash? You will have 5 seconds to undo this action.')) {
            return;
        }

        const formData = new FormData();
        formData.append('survey_id', surveyId);

        fetch('ajax/delete_survey_soft.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to undo page
                    window.location.href = 'delete_with_undo.php?survey_id=' + surveyId;
                } else {
                    alert('Error: ' + (data.error || 'Unable to delete the survey'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
    }
</script>

<?php include 'includes/footer.php'; ?>