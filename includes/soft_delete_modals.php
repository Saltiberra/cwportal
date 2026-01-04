<?php

/**
 * Soft Delete Modals
 * Custom Bootstrap modals for delete confirmations
 */
?>

<!-- Modal: Confirm Soft Delete -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteLabel">
                    <i class="fas fa-trash-alt me-2"></i>Move to Trash?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Report will be moved to trash</strong>
                </div>
                <p class="mb-0">
                    <strong>Report:</strong> <span id="deleteReportName" class="text-danger">-</span>
                </p>
                <p class="mb-3">
                    <small class="text-muted">You will have <strong>5 seconds</strong> to undo this action.</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="proceedSoftDelete()">
                    <i class="fas fa-trash me-1"></i>Move to Trash
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirm Permanent Delete -->
<div class="modal fade" id="confirmPermanentDeleteModal" tabindex="-1" aria-labelledby="confirmPermanentDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmPermanentDeleteLabel">
                    <i class="fas fa-times-circle me-2"></i>Permanently Delete?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>⚠️ WARNING: This action CANNOT be undone!</strong>
                </div>
                <p class="mb-0">
                    <strong>Report:</strong> <span id="deletePermanentReportName" class="text-danger">-</span>
                </p>
                <p class="mb-3">
                    <small class="text-muted">All data will be permanently removed from the system.</small>
                </p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmPermanentCheckbox">
                    <label class="form-check-label" for="confirmPermanentCheckbox">
                        I understand this cannot be undone
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmPermanentDeleteBtn" disabled onclick="proceedPermanentDelete()">
                    <i class="fas fa-times-circle me-1"></i>Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirm Restore -->
<div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-labelledby="confirmRestoreLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-success">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="confirmRestoreLabel">
                    <i class="fas fa-undo me-2"></i>Restore Report?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Report will be restored to active status</strong>
                </div>
                <p class="mb-0">
                    <strong>Report:</strong> <span id="restoreReportName" class="text-success">-</span>
                </p>
                <p class="mb-3">
                    <small class="text-muted">It will appear in the normal reports list.</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmRestoreBtn" onclick="proceedRestore()">
                    <i class="fas fa-undo me-1"></i>Restore
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let pendingDeleteReportId = null;
    let pendingDeleteReportName = null;
    let pendingDeleteReportType = null;
    let pendingPermanentDeleteReportId = null;
    let pendingPermanentDeleteReportName = null;
    let pendingPermanentDeleteReportType = null;
    let pendingRestoreReportId = null;
    let pendingRestoreReportName = null;
    let pendingRestoreReportType = null;

    // Show soft delete confirmation modal
    function showDeleteConfirmation(reportId, reportName) {
        pendingDeleteReportId = reportId;
        pendingDeleteReportName = reportName;
        pendingDeleteReportType = 'commissioning'; // default for backward compatibility
        document.getElementById('deleteReportName').textContent = reportName;

        const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        modal.show();
    }

    // Show permanent delete confirmation modal
    function showPermanentDeleteConfirmation(reportId, reportName, type = 'commissioning') {
        pendingPermanentDeleteReportId = reportId;
        pendingPermanentDeleteReportName = reportName;
        pendingPermanentDeleteReportType = type;
        document.getElementById('deletePermanentReportName').textContent = reportName;
        document.getElementById('confirmPermanentCheckbox').checked = false;
        document.getElementById('confirmPermanentDeleteBtn').disabled = true;

        // Re-attach checkbox listener
        const checkbox = document.getElementById('confirmPermanentCheckbox');
        checkbox.removeEventListener('change', updateDeleteButton);
        checkbox.addEventListener('change', updateDeleteButton);

        const modal = new bootstrap.Modal(document.getElementById('confirmPermanentDeleteModal'));
        modal.show();
    }

    // Helper function for checkbox listener
    function updateDeleteButton() {
        document.getElementById('confirmPermanentDeleteBtn').disabled = !this.checked;
    }

    // Show restore confirmation modal
    function showRestoreConfirmation(reportId, reportName, type = 'commissioning') {
        pendingRestoreReportId = reportId;
        pendingRestoreReportName = reportName;
        pendingRestoreReportType = type;
        document.getElementById('restoreReportName').textContent = reportName;

        const modal = new bootstrap.Modal(document.getElementById('confirmRestoreModal'));
        modal.show();
    }

    // Enable/disable permanent delete button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('confirmPermanentCheckbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                document.getElementById('confirmPermanentDeleteBtn').disabled = !this.checked;
            });
        }
    });

    // Proceed with soft delete
    function proceedSoftDelete() {
        if (!pendingDeleteReportId) return;

        const formData = new FormData();
        formData.append('report_id', pendingDeleteReportId);

        fetch('ajax/delete_report_soft.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and redirect to undo page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    if (modal) modal.hide();

                    window.location.href = 'delete_with_undo.php?report_id=' + pendingDeleteReportId;
                } else {
                    alert('Error: ' + (data.error || 'Could not delete report'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
    }

    // Proceed with permanent delete
    function proceedPermanentDelete() {
        if (!pendingPermanentDeleteReportId) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
        <input type="hidden" name="report_id" value="${pendingPermanentDeleteReportId}">
        <input type="hidden" name="report_type" value="${pendingPermanentDeleteReportType}">
        <input type="hidden" name="action" value="delete_permanent">
    `;
        document.body.appendChild(form);
        form.submit();
    }

    // Proceed with restore
    function proceedRestore() {
        if (!pendingRestoreReportId) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
        <input type="hidden" name="report_id" value="${pendingRestoreReportId}">
        <input type="hidden" name="report_type" value="${pendingRestoreReportType}">
        <input type="hidden" name="action" value="restore">
    `;
        document.body.appendChild(form);
        form.submit();
    }
</script>