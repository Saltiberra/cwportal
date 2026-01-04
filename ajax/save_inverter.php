<?php

/**
 * Save Inverter - Progressive Save Endpoint
 *
 * This endpoint saves an inverter to the database immediately
 *
 * Called from: main.js when user adds/updates an inverter
 */

require_once '../config/database.php';
require_once '../includes/ajax_security.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    // 🔒 SECURITY: Validate report ownership before proceeding
    validateAjaxRequest($pdo, $data);

    $reportId = isset($data['report_id']) ? intval($data['report_id']) : null;
    $inverterId = isset($data['inverter_id']) ? intval($data['inverter_id']) : null;
    $brandId = isset($data['brand_id']) ? intval($data['brand_id']) : null;
    $modelId = isset($data['model_id']) ? intval($data['model_id']) : null;
    $brandName = isset($data['brand_name']) ? $data['brand_name'] : '';
    $modelName = isset($data['model_name']) ? $data['model_name'] : '';
    $status = isset($data['status']) ? $data['status'] : 'new';
    $serialNumber = isset($data['serial_number']) ? $data['serial_number'] : null;
    $location = isset($data['location']) ? $data['location'] : null;
    $maxOutputCurrent = isset($data['max_output_current']) ? $data['max_output_current'] : null;
    $datasheetUrl = isset($data['datasheet_url']) ? $data['datasheet_url'] : null;

    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    // Verify report exists
    $stmt = $pdo->prepare("SELECT id FROM commissioning_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    if (!$stmt->fetch()) {
        throw new Exception('Report not found');
    }

    if ($inverterId) {
        // UPDATE existing inverter in report_equipment
        // Build characteristics string
        $characteristics = '';
        if (!empty($serialNumber)) {
            $characteristics .= "Serial: {$serialNumber} | ";
        }
        if (!empty($location)) {
            $characteristics .= "Location: {$location} | ";
        }
        if (!empty($maxOutputCurrent)) {
            $characteristics .= "Max Output Current: {$maxOutputCurrent} | ";
        }
        if (!empty($datasheetUrl)) {
            $characteristics .= "Datasheet: {$datasheetUrl} | ";
        }
        $characteristics = rtrim($characteristics, ' | ');

        $stmt = $pdo->prepare("
            UPDATE report_equipment 
            SET 
                deployment_status = ?,
                brand = ?,
                model = ?,
                model_id = ?,
                quantity = 1,
                characteristics = ?,
                location = ?
            WHERE id = ? AND report_id = ? AND equipment_type = 'Inverter'
        ");

        $stmt->execute([
            $status,
            $brandName,
            $modelName,
            $modelId,
            $characteristics,
            $location,
            $inverterId,
            $reportId
        ]);

        $message = 'Inverter updated successfully';
        $newInverterId = $inverterId;
    } else {
        // INSERT new inverter into report_equipment
        // Build characteristics string
        $characteristics = '';
        if (!empty($serialNumber)) {
            $characteristics .= "Serial: {$serialNumber} | ";
        }
        if (!empty($location)) {
            $characteristics .= "Location: {$location} | ";
        }
        if (!empty($maxOutputCurrent)) {
            $characteristics .= "Max Output Current: {$maxOutputCurrent} | ";
        }
        if (!empty($datasheetUrl)) {
            $characteristics .= "Datasheet: {$datasheetUrl} | ";
        }
        $characteristics = rtrim($characteristics, ' | ');

        $stmt = $pdo->prepare("
            INSERT INTO report_equipment (
                report_id,
                equipment_type,
                deployment_status,
                brand,
                model,
                model_id,
                quantity,
                characteristics,
                location
            ) VALUES (?, 'Inverter', ?, ?, ?, ?, 1, ?, ?)
        ");

        $stmt->execute([
            $reportId,
            $status,
            $brandName,
            $modelName,
            $modelId,
            $characteristics,
            $location
        ]);

        $newInverterId = $pdo->lastInsertId();
        $message = 'Inverter saved successfully';
    }

    // Get updated data with brand and model names
    $stmt = $pdo->prepare("
        SELECT 
            re.id,
            re.report_id,
            re.equipment_type,
            re.deployment_status as status,
            re.brand,
            re.model,
            re.model_id,
            re.quantity,
            re.characteristics,
            re.location
        FROM report_equipment re
        WHERE re.id = ? AND re.equipment_type = 'Inverter'
    ");
    $stmt->execute([$newInverterId]);
    $inverter = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'inverter_id' => $newInverterId,
        'inverter' => $inverter
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
