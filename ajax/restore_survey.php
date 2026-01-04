<?php

/**
 * Restore Survey API (Undo Delete)
 * Marks a survey as active again
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : null;

if (!$survey_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Survey ID required']);
    exit;
}

try {
    // Check if survey exists and is deleted
    $stmt = $pdo->prepare("SELECT id, is_deleted FROM site_survey_reports WHERE id = ?");
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch();

    if (!$survey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Survey not found']);
        exit;
    }

    if (!$survey['is_deleted']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey is not deleted']);
        exit;
    }

    // Restore: mark as not deleted
    try {
        $stmt = $pdo->prepare("
            UPDATE site_survey_reports
            SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL
            WHERE id = ?
        ");
        $stmt->execute([$survey_id]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $fallback = $pdo->prepare("UPDATE site_survey_reports SET is_deleted = FALSE WHERE id = ?");
            $fallback->execute([$survey_id]);
        } else {
            throw $e;
        }
    }

    // Log the restoration in deletion_log
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';
    $logStmt = $pdo->prepare("
        UPDATE deletion_log
        SET restored_by = ?, restored_at = NOW(), restored_user_name = ?
        WHERE report_id = ? AND report_type = 'survey' AND restored_at IS NULL
        LIMIT 1
    ");
    $logStmt->execute([$userId, $userName, $survey_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Survey restored successfully',
        'survey_id' => $survey_id
    ]);
} catch (PDOException $e) {
    error_log('[restore_survey] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('[restore_survey] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
