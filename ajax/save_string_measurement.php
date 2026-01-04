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

    // If measurement_id provided, it's an UPDATE
    if (isset($data['measurement_id'])) {
        $measurement_id = (int)$data['measurement_id'];

        $update = $pdo->prepare("
            UPDATE report_string_measurements 
            SET string_name = ?, 
                string_number = ?, 
                voltage_open_circuit = ?, 
                voltage_under_load = ?, 
                current = ?,
                power = ?,
                temperature = ?,
                notes = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['string_name'] ?? null,
            $data['string_number'] ?? null,
            $data['voltage_open_circuit'] ?? null,
            $data['voltage_under_load'] ?? null,
            $data['current'] ?? null,
            $data['power'] ?? null,
            $data['temperature'] ?? null,
            $data['notes'] ?? null,
            $measurement_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'String measurement updated successfully',
            'measurement_id' => $measurement_id
        ]);
    } else {
        // INSERT new measurement
        $insert = $pdo->prepare("
            INSERT INTO report_string_measurements 
            (report_id, string_name, string_number, voltage_open_circuit, voltage_under_load, 
             current, power, temperature, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['string_name'] ?? null,
            $data['string_number'] ?? null,
            $data['voltage_open_circuit'] ?? null,
            $data['voltage_under_load'] ?? null,
            $data['current'] ?? null,
            $data['power'] ?? null,
            $data['temperature'] ?? null,
            $data['notes'] ?? null
        ]);

        $measurement_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'String measurement saved successfully',
            'measurement_id' => (int)$measurement_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
