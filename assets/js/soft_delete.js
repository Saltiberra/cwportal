/**
 * Soft Delete System - Client-side handler
 */

// Soft delete report function
function softDeleteReport(reportId) {
    if (!confirm('Move this report to trash? You will have 5 seconds to undo this action.')) {
        return;
    }

    const formData = new FormData();
    formData.append('report_id', reportId);

    fetch((window.BASE_URL || '') + 'ajax/delete_report_soft.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to undo page
                window.location.href = 'delete_with_undo.php?report_id=' + reportId;
            } else {
                alert('Error: ' + (data.error || 'Could not delete report'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
}
