<?php

/**
 * Delete Schedule - AJAX Endpoint
 * 
 * Removes a schedule from the calendar
 * 
 * @return JSON Operation result
 */

require_once '../includes/auth.php';
requireLogin();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Accept DELETE or POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Also accept GET for convenience
if (empty($input['id']) && isset($_GET['id'])) {
    $input['id'] = $_GET['id'];
}

try {
    // Validation
    if (empty($input['id'])) {
        throw new Exception('Schedule ID is required');
    }

    $scheduleId = (int) $input['id'];

    // Check if schedule exists
    $checkStmt = $pdo->prepare("SELECT id, title FROM schedules WHERE id = :id");
    $checkStmt->execute([':id' => $scheduleId]);
    $schedule = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    // Delete the schedule
    $deleteStmt = $pdo->prepare("DELETE FROM schedules WHERE id = :id");
    $deleteStmt->execute([':id' => $scheduleId]);

    // Audit log (optional)
    if (isset($_SESSION['user_id'])) {
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (:user_id, 'delete', 'schedule', :entity_id, :details, NOW())
            ");
            $logStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':entity_id' => $scheduleId,
                ':details' => json_encode(['title' => $schedule['title']])
            ]);
        } catch (Exception $logError) {
            // Ignore log error - not critical
            error_log('[delete_schedule.php] Log error: ' . $logError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully',
        'id' => $scheduleId,
        'title' => $schedule['title']
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[delete_schedule.php] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error while deleting schedule',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[delete_schedule.php] Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
