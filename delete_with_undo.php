<?php

/**
 * Temporary Undo page after soft-deleting a report
 */
require_once 'includes/auth.php';
requireLogin();
require_once 'config/database.php';

$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : null;
$survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : null;

$report = null;
$survey = null;
$type = null;

if ($report_id) {
    $type = 'report';
    try {
        $stmt = $pdo->prepare("SELECT id, project_name FROM commissioning_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // ignore
    }
} elseif ($survey_id) {
    $type = 'survey';
    try {
        $stmt = $pdo->prepare("SELECT id, project_name FROM site_survey_reports WHERE id = ?");
        $stmt->execute([$survey_id]);
        $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // ignore
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-3"><?php echo $type === 'survey' ? 'Survey' : 'Report'; ?> moved to Trash</h3>
                    <?php if ($report): ?>
                        <p class="lead">The report <strong><?php echo htmlspecialchars($report['project_name']); ?></strong> (ID <?php echo $report['id']; ?>) was moved to the trash.</p>
                    <?php elseif ($survey): ?>
                        <p class="lead">The survey <strong><?php echo htmlspecialchars($survey['project_name']); ?></strong> (ID <?php echo $survey['id']; ?>) was moved to the trash.</p>
                    <?php else: ?>
                        <p class="lead">The <?php echo $type ?: 'item'; ?> was moved to the trash.</p>
                    <?php endif; ?>

                    <p>You have <strong id="countdown">5</strong> seconds to undo.</p>

                    <div class="d-flex justify-content-center gap-2">
                        <button id="undoBtn" class="btn btn-outline-success">Undo</button>
                        <a href="index.php" class="btn btn-secondary">Go to Dashboard</a>
                        <a href="admin_trash.php" class="btn btn-danger">Open Trash</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const reportId = <?php echo $report_id ? (int)$report_id : 'null'; ?>;
        const surveyId = <?php echo $survey_id ? (int)$survey_id : 'null'; ?>;
        const type = '<?php echo $type; ?>';
        let seconds = 5;
        const countdownEl = document.getElementById('countdown');
        const undoBtn = document.getElementById('undoBtn');

        const timer = setInterval(() => {
            seconds -= 1;
            if (countdownEl) countdownEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                // Redirect back to where the user came from (previous page) after timeout
                const returnUrl = (document.referrer && document.referrer !== '') ? document.referrer : 'index.php';
                window.location.href = returnUrl;
            }
        }, 1000);

        if (undoBtn) {
            undoBtn.addEventListener('click', function() {
                const id = reportId || surveyId;
                if (!id) return;
                const fd = new FormData();
                if (reportId) {
                    fd.append('report_id', reportId);
                } else if (surveyId) {
                    fd.append('survey_id', surveyId);
                }
                fetch('ajax/restore_' + type + '.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect back to edit page or dashboard
                            if (type === 'survey') {
                                window.location.href = 'site_survey.php?survey_id=' + surveyId;
                            } else {
                                window.location.href = 'comissionamento.php?report_id=' + reportId;
                            }
                        } else {
                            alert('Could not restore: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('An error occurred while restoring the ' + type);
                    });
            });
        }
    })();
</script>

<?php include 'includes/footer.php';
