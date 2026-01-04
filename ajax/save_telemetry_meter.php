<?php
header('Content-Type: application/json');
require_once '../config/database.php';

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

    // If meter_id provided, it's an UPDATE
    if (isset($data['meter_id'])) {
        $meter_id = (int)$data['meter_id'];

        $update = $pdo->prepare("
            UPDATE report_telemetry_meters 
            SET meter_model = ?, 
                meter_serial = ?, 
                meter_type = ?, 
                location = ?, 
                ip_address = ?,
                connection_type = ?,
                status = ?,
                notes = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['meter_model'] ?? null,
            $data['meter_serial'] ?? null,
            $data['meter_type'] ?? null,
            $data['location'] ?? null,
            $data['ip_address'] ?? null,
            $data['connection_type'] ?? null,
            $data['status'] ?? null,
            $data['notes'] ?? null,
            $meter_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Telemetry meter updated successfully',
            'meter_id' => $meter_id
        ]);
    } else {
        // INSERT new telemetry meter
        $insert = $pdo->prepare("
            INSERT INTO report_telemetry_meters 
            (report_id, meter_model, meter_serial, meter_type, location, 
             ip_address, connection_type, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['meter_model'] ?? null,
            $data['meter_serial'] ?? null,
            $data['meter_type'] ?? null,
            $data['location'] ?? null,
            $data['ip_address'] ?? null,
            $data['connection_type'] ?? null,
            $data['status'] ?? null,
            $data['notes'] ?? null
        ]);

        $meter_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Telemetry meter saved successfully',
            'meter_id' => (int)$meter_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
