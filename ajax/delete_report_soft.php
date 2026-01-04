<?php

/**
 * Delete Report API (Soft Delete)
 * Marks a report as deleted instead of permanently removing it
 */

// Suppress warnings and notices to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
// Capture any unexpected output (warnings/notices) so we can return it as part of the JSON response
ob_start();
require_once '../config/database.php';
require_once '../includes/audit.php';

// Always return JSON and ensure uncaught exceptions are logged and returned as JSON
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function ($e) {
    $out = ob_get_clean();
    error_log('[delete_report_soft] Uncaught exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage(), 'html' => $out]);
    exit;
});

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
    // Check if report exists and user can delete it
    $stmt = $pdo->prepare("SELECT id, user_id, project_name FROM commissioning_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        exit;
    }

    // Check permissions (creator or admin)
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';
    $canDelete = ($report['user_id'] == $userId) || ($userRole === 'admin');

    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    // Soft delete: mark as deleted instead of removing
    // Wrap in transaction and insert to deletion_log while avoiding reliance on AUTO_INCREMENT
    $pdo->beginTransaction();

    try {
        $updateStmt = $pdo->prepare("UPDATE commissioning_reports SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $updateStmt->execute([$userId, $report_id]);
    } catch (PDOException $e) {
        // If the deleted_at or deleted_by column is missing, fallback to marking is_deleted only
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $fallbackStmt = $pdo->prepare("UPDATE commissioning_reports SET is_deleted = TRUE WHERE id = ?");
            $fallbackStmt->execute([$report_id]);
        } else {
            throw $e;
        }
    }

    // Log the deletion in deletion_log table. Some hosts disallow ALTER, so compute next id if needed.
    $nextId = null;
    try {
        $nextIdRow = $pdo->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM deletion_log");
        if ($nextIdRow !== false) {
            $nextId = (int) $nextIdRow->fetchColumn();
        }
    } catch (Throwable $e) {
        // ignore - fallback to insert without id
        $nextId = null;
    }

    if ($nextId) {
        $logStmt = $pdo->prepare("INSERT INTO deletion_log (id, report_id, report_project_name, report_type, deleted_by, deleted_user_name, deleted_at) VALUES (?, ?, ?, 'commissioning', ?, ?, NOW())");
        $logStmt->execute([$nextId, $report_id, $report['project_name'], $userId, $userName]);
    } else {
        $logStmt = $pdo->prepare("INSERT INTO deletion_log (report_id, report_project_name, report_type, deleted_by, deleted_user_name, deleted_at) VALUES (?, ?, 'commissioning', ?, ?, NOW())");
        $logStmt->execute([$report_id, $report['project_name'], $userId, $userName]);
    }

    $pdo->commit();

    // Also soft delete related punch list items
    try {
        $pdo->prepare("UPDATE report_punch_list SET is_deleted = TRUE WHERE report_id = ?")->execute([$report_id]);
    } catch (Throwable $e) {
        // ignore if table doesn't have column or error
    }

    // Audit log
    try {
        logAction('report_deleted', 'commissioning_reports', $report_id, 'Report soft-deleted: ' . $report['project_name'], $report['project_name']);
    } catch (Exception $e) {
        // ignore audit failures
    }

    // Clean any buffered output before sending JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Report moved to trash',
        'report_id' => $report_id,
        'deleted_by' => $userName,
        'deleted_at' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    // If we're in a transaction, rollback
    try {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    } catch (Throwable $rb) {
    }
    error_log('[delete_report_soft] Error: ' . $e->getMessage());
    $out = ob_get_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'html' => $out]);
}
