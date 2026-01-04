<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $report_id = $_POST['report_id'] ?? null;
    $form_id = $_POST['form_id'] ?? null;
    $form_data = $_POST['form_data'] ?? null;

    if (!$report_id || !$form_id || !$form_data) {
        throw new Exception('Missing required parameters');
    }

    // Validate report exists and belongs to current user
    $stmt = $pdo->prepare("
        SELECT id FROM reports
        WHERE id = ? AND (user_id = ? OR ? = 'admin')
    ");
    $stmt->execute([$report_id, $_SESSION['user_id'] ?? null, $_SESSION['role'] ?? null]);
    if (!$stmt->fetch()) {
        throw new Exception('Report not found or access denied');
    }

    // Save or update form draft
    $stmt = $pdo->prepare("
        INSERT INTO form_drafts (report_id, form_id, form_data, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        form_data = VALUES(form_data),
        updated_at = NOW()
    ");
    $stmt->execute([$report_id, $form_id, $form_data]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
