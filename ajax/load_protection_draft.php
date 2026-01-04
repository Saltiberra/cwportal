<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $report_id = $_GET['report_id'] ?? null;

    if (!$report_id) {
        throw new Exception('Missing report_id parameter');
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

    // Load protection draft
    $stmt = $pdo->prepare("
        SELECT draft_data FROM drafts
        WHERE report_id = ? AND draft_type = 'protection'
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$report_id]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'success' => true,
            'protection_data' => $result['draft_data']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No protection data found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
