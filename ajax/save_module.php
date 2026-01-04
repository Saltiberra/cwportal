<?php

/**
 * Save PV Module - Progressive Save Endpoint
 * 
 * This endpoint saves a PV module to the database immediately
 * instead of waiting for the user to click "Save Report".
 * 
 * Called from: main.js when user adds/updates a module
 */

require_once '../config/database.php';
require_once '../includes/ajax_security.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        $data = $_POST;
    }

    // 🔒 SECURITY: Validate report ownership before proceeding
    validateAjaxRequest($pdo, $data);

    $reportId = isset($data['report_id']) ? intval($data['report_id']) : null;
    $moduleId = isset($data['module_id']) ? intval($data['module_id']) : null;
    $brandId = isset($data['brand_id']) ? intval($data['brand_id']) : null;
    $modelId = isset($data['model_id']) ? intval($data['model_id']) : null;
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    $status = isset($data['status']) ? $data['status'] : 'new';
    $powerRating = isset($data['power_rating']) ? floatval($data['power_rating']) : null;
    $serialNumber = isset($data['serial_number']) ? $data['serial_number'] : null;
    $datasheetUrl = isset($data['datasheet_url']) ? $data['datasheet_url'] : null;

    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    if ($moduleId) {
        // UPDATE existing module
        $stmt = $pdo->prepare("
            UPDATE report_modules 
            SET 
                pv_module_brand_id = ?,
                pv_module_model_id = ?,
                quantity = ?,
                deployment_status = ?,
                power_rating = ?,
                serial_number = ?,
                datasheet_url = ?,
                updated_at = NOW()
            WHERE id = ? AND report_id = ?
        ");

        $stmt->execute([
            $brandId,
            $modelId,
            $quantity,
            $status,
            $powerRating,
            $serialNumber,
            $datasheetUrl,
            $moduleId,
            $reportId
        ]);

        $message = 'Module updated successfully';
        $newModuleId = $moduleId;
    } else {
        // INSERT new module
        $stmt = $pdo->prepare("
            INSERT INTO report_modules (
                report_id,
                pv_module_brand_id,
                pv_module_model_id,
                quantity,
                deployment_status,
                power_rating,
                serial_number,
                datasheet_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $reportId,
            $brandId,
            $modelId,
            $quantity,
            $status,
            $powerRating,
            $serialNumber,
            $datasheetUrl
        ]);

        $newModuleId = $pdo->lastInsertId();
        $message = 'Module saved successfully';
    }

    // Get updated data with brand and model names
    $stmt = $pdo->prepare("
        SELECT 
            rm.*,
            pmb.brand_name,
            pmm.model_name,
            pmm.power_options
        FROM report_modules rm
        LEFT JOIN pv_module_brands pmb ON rm.pv_module_brand_id = pmb.id
        LEFT JOIN pv_module_models pmm ON rm.pv_module_model_id = pmm.id
        WHERE rm.id = ?
    ");
    $stmt->execute([$newModuleId]);
    $module = $stmt->fetch();

    // Calculate total installed power for the report
    // Use power_rating from report_modules, or extract from power_options if available
    $stmt = $pdo->prepare("
        SELECT SUM(rm.quantity * COALESCE(rm.power_rating, 0)) as total_power
        FROM report_modules rm
        WHERE rm.report_id = ?
    ");
    $stmt->execute([$reportId]);
    $powerResult = $stmt->fetch();
    $totalPower = $powerResult['total_power'] ? floatval($powerResult['total_power']) / 1000 : 0; // Convert to kWp

    // Update commissioned report's installed_power
    $stmt = $pdo->prepare("UPDATE commissioning_reports SET installed_power = ? WHERE id = ?");
    $stmt->execute([$totalPower, $reportId]);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'module_id' => $newModuleId,
        'module' => $module,
        'total_power' => $totalPower
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
