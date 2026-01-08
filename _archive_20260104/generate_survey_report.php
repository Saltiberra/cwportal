<?php
// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();

require_once 'config/database.php';

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM site_survey_reports WHERE id=? LIMIT 1");
$stmt->execute([$surveyId]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Survey not found.</div></div>';
    exit;
}

$itemsStmt = $pdo->prepare("SELECT item_type, item_key, label, status, note, value FROM site_survey_items WHERE report_id=? ORDER BY id ASC");
$itemsStmt->execute([$surveyId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$photosLink = '';
$checklist = [];
foreach ($items as $row) {
    if ($row['item_type'] === 'Survey - Photos Link') {
        $photosLink = $row['value'] ?? '';
    } elseif ($row['item_type'] === 'Survey - Checklist') {
        $checklist[] = $row;
    }
}

include 'includes/header.php';
?>
<div class="container my-4 main-content-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="fas fa-file-alt me-2"></i>Site Survey Report</h3>
        <a class="btn btn-outline-secondary" href="site_survey.php?survey_id=<?php echo $surveyId; ?>"><i class="fas fa-edit me-2"></i>Edit</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-bottom"><strong>General</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><strong>Project:</strong> <?php echo htmlspecialchars($survey['project_name']); ?></div>
                <div class="col-md-3"><strong>Date:</strong> <?php echo htmlspecialchars($survey['date']); ?></div>
                <div class="col-md-3"><strong>Responsible:</strong> <?php echo htmlspecialchars($survey['responsible'] ?? ''); ?></div>
                <div class="col-md-6"><strong>Location:</strong> <?php echo htmlspecialchars($survey['location'] ?? ''); ?></div>
                <div class="col-md-6"><strong>GPS:</strong> <?php echo htmlspecialchars($survey['gps'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-bottom"><strong>Photos Repository</strong></div>
        <div class="card-body">
            <?php if ($photosLink): ?>
                <a href="<?php echo htmlspecialchars($photosLink); ?>" target="_blank" rel="noopener">Open Photos Link</a>
            <?php else: ?>
                <span class="text-muted">No link provided.</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-bottom"><strong>Survey Checklist</strong></div>
        <div class="card-body">
            <?php if (!empty($checklist)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklist as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['label']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td><?php echo htmlspecialchars($row['note']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <span class="text-muted">No checklist data found.</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>