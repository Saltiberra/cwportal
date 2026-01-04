<?php
// AJAX endpoint to save Additional Notes for a report
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid method');
    $reportId = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($reportId <= 0) throw new Exception('Missing report_id');

    // Delete existing Additional Notes for this report
    $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Additional Notes'");
    $stmt->execute([$reportId]);

    if ($notes !== '') {
        $characteristics = 'Notes: ' . $notes;
        $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Additional Notes', NULL, NULL, NULL, 1, ?)");
        $stmt->execute([$reportId, $characteristics]);
    }

    echo json_encode(['status' => 'ok']);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
