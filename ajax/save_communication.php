<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/ajax_security.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // 🔒 SECURITY: Validate report ownership before proceeding
    validateAjaxRequest($pdo, $data);

    $report_id = (int)$data['report_id'];

    // Report ownership already validated above
    if (!$check->fetch()) {
        throw new Exception("Report not found");
    }

    // If communication_id provided, it's an UPDATE
    if (isset($data['communication_id'])) {
        $communication_id = (int)$data['communication_id'];

        $update = $pdo->prepare("
            UPDATE report_communications 
            SET device_type = ?, 
                device_model = ?, 
                firmware_version = ?, 
                communication_protocol = ?, 
                ip_address = ?,
                signal_strength = ?,
                status = ?,
                notes = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['device_type'] ?? null,
            $data['device_model'] ?? null,
            $data['firmware_version'] ?? null,
            $data['communication_protocol'] ?? null,
            $data['ip_address'] ?? null,
            $data['signal_strength'] ?? null,
            $data['status'] ?? null,
            $data['notes'] ?? null,
            $communication_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Communication device updated successfully',
            'communication_id' => $communication_id
        ]);
    } else {
        // INSERT new communication device
        $insert = $pdo->prepare("
            INSERT INTO report_communications 
            (report_id, device_type, device_model, firmware_version, communication_protocol, 
             ip_address, signal_strength, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['device_type'] ?? null,
            $data['device_model'] ?? null,
            $data['firmware_version'] ?? null,
            $data['communication_protocol'] ?? null,
            $data['ip_address'] ?? null,
            $data['signal_strength'] ?? null,
            $data['status'] ?? null,
            $data['notes'] ?? null
        ]);

        $communication_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Communication device saved successfully',
            'communication_id' => (int)$communication_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
