<?php

/**
 * Add Equipment Model
 * 
 * This script handles the addition of new equipment models to the database
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$type = isset($_POST['type']) ? $_POST['type'] : '';
$brandId = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
$modelName = isset($_POST['model_name']) ? trim($_POST['model_name']) : '';
$characteristics = isset($_POST['characteristics']) ? trim($_POST['characteristics']) : '';
$powerOptions = isset($_POST['power_options']) ? trim($_POST['power_options']) : '';

// Validate inputs
if (empty($type) || empty($modelName) || $brandId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Type, brand ID, and model name are required']);
    exit;
}

try {
    /**
     * Helper: attempt insert, and if DB reports duplicate entry '0' for PRIMARY,
     * retry inserting with an explicit computed id = MAX(id)+1.
     *
     * @param PDO $pdo
     * @param string $table
     * @param array $columns  list of column names (in order)
     * @param array $values   list of values (in same order)
     * @return int inserted id
     */
    function insertWithIdFallback($pdo, $table, $columns, $values)
    {
        // Build placeholders
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colList = implode(', ', $columns);
        $sql = "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Detect duplicate entry '0' for PRIMARY (common on misconfigured hosts)
            if (strpos($msg, "Duplicate entry '0' for key 'PRIMARY'") !== false || (strpos($msg, "Duplicate entry '0'") !== false && strpos($msg, 'PRIMARY') !== false)) {
                // compute next id
                $nextStmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM {$table}");
                $nextId = (int)$nextStmt->fetchColumn();
                error_log("[ADD_EQUIP_MODEL] Detected duplicate 0 primary for table {$table}, retrying with id={$nextId}");

                // Prepend id to columns and values
                array_unshift($columns, 'id');
                array_unshift($values, $nextId);

                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $colList = implode(', ', $columns);
                $sql2 = "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})";

                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute($values);

                return $nextId;
            }

            // rethrow other DB errors
            throw $e;
        }
    }
    // Determine table and required fields based on equipment type
    switch ($type) {
        case 'pv_module':
            $tableName = 'pv_module_models';

            // For PV modules, characteristics is required
            if (empty($characteristics)) {
                $characteristics = "No specific characteristics provided";
            }

            // Validate power options
            if (empty($powerOptions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Power options are required for PV modules']);
                exit;
            }

            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics', 'power_options'], [$brandId, $modelName, $characteristics, $powerOptions]);
            break;

        case 'inverter':
            $tableName = 'inverter_models';
            $nominalPower = isset($_POST['nominal_power']) ? floatval($_POST['nominal_power']) : 0;
            $maxOutputCurrent = isset($_POST['max_output_current']) ? floatval($_POST['max_output_current']) : null;
            $mppts = isset($_POST['mppts']) ? intval($_POST['mppts']) : 1;
            $stringsPerMppt = isset($_POST['strings_per_mppt']) ? intval($_POST['strings_per_mppt']) : 1;

            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics', 'nominal_power', 'max_output_current', 'mppts', 'strings_per_mppt'], [$brandId, $modelName, $characteristics, $nominalPower, $maxOutputCurrent, $mppts, $stringsPerMppt]);
            break;

        case 'circuit_breaker':
            $tableName = 'circuit_breaker_models';
            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics'], [$brandId, $modelName, $characteristics]);
            break;
        case 'differential':
            $tableName = 'differential_models';
            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics'], [$brandId, $modelName, $characteristics]);
            break;
        case 'cable':
            $tableName = 'cable_models';
            // Optional cable-specific fields (if provided)
            $cableSection = isset($_POST['cable_section']) ? trim($_POST['cable_section']) : null;
            $conductorMaterial = isset($_POST['conductor_material']) ? trim($_POST['conductor_material']) : null;
            $insulationType = isset($_POST['insulation_type']) ? trim($_POST['insulation_type']) : null;
            $voltageRating = isset($_POST['voltage_rating']) ? trim($_POST['voltage_rating']) : null;
            $temperatureRating = isset($_POST['temperature_rating']) ? trim($_POST['temperature_rating']) : null;

            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'cable_section', 'conductor_material', 'insulation_type', 'voltage_rating', 'temperature_rating', 'characteristics'], [$brandId, $modelName, $cableSection, $conductorMaterial, $insulationType, $voltageRating, $temperatureRating, $characteristics]);
            break;
        case 'meter':
            $tableName = 'meter_models';
            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics'], [$brandId, $modelName, $characteristics]);
            break;
        case 'energy_meter':
            $tableName = 'energy_meter_models';
            // Optional energy meter specific fields
            $communicationProtocol = isset($_POST['communication_protocol']) ? trim($_POST['communication_protocol']) : null;
            $voltageRange = isset($_POST['voltage_range']) ? trim($_POST['voltage_range']) : null;
            $currentRange = isset($_POST['current_range']) ? trim($_POST['current_range']) : null;

            $modelId = insertWithIdFallback($pdo, $tableName, ['brand_id', 'model_name', 'characteristics', 'communication_protocol', 'voltage_range', 'current_range'], [$brandId, $modelName, $characteristics, $communicationProtocol, $voltageRange, $currentRange]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid equipment type']);
            exit;
    }

    // Ensure we have the new model ID (use lastInsertId only if helper didn't set it)
    if (!isset($modelId) || empty($modelId) || $modelId == 0) {
        $last = (int)$pdo->lastInsertId();
        if ($last > 0) {
            $modelId = $last;
        }
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Model added successfully',
        'model' => [
            'id' => $modelId,
            'model_name' => $modelName
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
