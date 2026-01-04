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

    // If layout_id provided, it's an UPDATE
    if (isset($data['layout_id'])) {
        $layout_id = (int)$data['layout_id'];

        $update = $pdo->prepare("
            UPDATE report_layouts 
            SET roof_area_id = ?, 
                module_quantity = ?, 
                azimuth = ?, 
                tilt = ?, 
                mounting_type = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['roof_area_id'] ?? null,
            $data['module_quantity'] ?? null,
            $data['azimuth'] ?? null,
            $data['tilt'] ?? null,
            $data['mounting_type'] ?? null,
            $layout_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Layout updated successfully',
            'layout_id' => $layout_id
        ]);
    } else {
        // INSERT new layout
        $insert = $pdo->prepare("
            INSERT INTO report_layouts 
            (report_id, roof_area_id, module_quantity, azimuth, tilt, mounting_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['roof_area_id'] ?? null,
            $data['module_quantity'] ?? null,
            $data['azimuth'] ?? null,
            $data['tilt'] ?? null,
            $data['mounting_type'] ?? null
        ]);

        $layout_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Layout saved successfully',
            'layout_id' => (int)$layout_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
