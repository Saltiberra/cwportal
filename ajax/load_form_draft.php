<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $report_id = $_GET['report_id'] ?? null;
    $form_id = $_GET['form_id'] ?? null;

    if (!$report_id || !$form_id) {
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

    // Load form draft
    $stmt = $pdo->prepare("
        SELECT form_data FROM form_drafts
        WHERE report_id = ? AND form_id = ?
    ");
    $stmt->execute([$report_id, $form_id]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'success' => true,
            'form_data' => $result['form_data']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No saved data found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
