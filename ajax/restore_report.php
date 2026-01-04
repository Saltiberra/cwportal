<?php

/**
 * Restore Report API (Undo Delete)
 * Marks a report as active again
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;

if (!$report_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Report ID required']);
    exit;
}

try {
    // Check if report exists and is deleted
    $stmt = $pdo->prepare("SELECT id, is_deleted FROM commissioning_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        exit;
    }

    if (!$report['is_deleted']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Report is not deleted']);
        exit;
    }

    // Restore: mark as not deleted
    $stmt = $pdo->prepare("
        UPDATE commissioning_reports 
        SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL
        WHERE id = ?
    ");
    try {
        $stmt->execute([$report_id]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $fallback = $pdo->prepare("UPDATE commissioning_reports SET is_deleted = FALSE WHERE id = ?");
            $fallback->execute([$report_id]);
        } else {
            throw $e;
        }
    }

    // Also restore related punch list items
    try {
        $pdo->prepare("UPDATE report_punch_list SET is_deleted = FALSE WHERE report_id = ?")->execute([$report_id]);
    } catch (Throwable $e) {
        // ignore if table doesn't have column or error
    }

    // Log the restoration
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';

    $logStmt = $pdo->prepare("
        UPDATE deletion_log 
        SET restored_by = ?, restored_at = NOW(), restored_user_name = ?
        WHERE report_id = ? AND restored_at IS NULL
        LIMIT 1
    ");
    $logStmt->execute([$userId, $userName, $report_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Report restored from trash',
        'report_id' => $report_id
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
