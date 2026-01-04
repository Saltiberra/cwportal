<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/ajax_security.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['report_id'])) {
        throw new Exception("report_id is required");
    }

    $report_id = (int)$data['report_id'];

    // Verify report exists
    $check = $pdo->prepare("SELECT id FROM commissioning_reports WHERE id = ?");
    $check->execute([$report_id]);
    if (!$check->fetch()) {
        throw new Exception("Report not found");
    }

    // Get all credentials from request
    $credentials = $data['credentials'] ?? [];

    if (!is_array($credentials)) {
        throw new Exception("credentials must be an array");
    }

    // Delete existing telemetry credentials for this report (from equipment table)
    $delete = $pdo->prepare("
        DELETE FROM report_equipment 
        WHERE report_id = ? AND equipment_type IN ('Telemetry - Credential', 'Telemetry - Meter')
    ");
    $delete->execute([$report_id]);

    // Insert each credential
    $insertStmt = $pdo->prepare("
        INSERT INTO report_equipment 
        (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics)
        VALUES (?, 'Telemetry - Credential', NULL, NULL, NULL, 1, ?)
    ");

    foreach ($credentials as $cred) {
        if (!is_array($cred)) continue;

        $invText = $cred['inverter_text'] ?? $cred['inverter_index'] ?? '';
        $username = $cred['username'] ?? '';
        $password = $cred['password'] ?? '';
        $ip = $cred['ip'] ?? '';

        $characteristics = "Inverter Ref: {$invText} | Username: {$username} | Password: {$password} | IP: {$ip}";

        $insertStmt->execute([
            $report_id,
            $characteristics
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Telemetry credentials saved successfully',
        'count' => count($credentials)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
