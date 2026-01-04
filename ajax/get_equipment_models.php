<?php
/**
 * AJAX Handler for fetching equipment models
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get parameters from request
$type = isset($_GET['type']) ? $_GET['type'] : '';
$brandId = isset($_GET['brand_id']) ? intval($_GET['brand_id']) : 0;

// Map type to database table
$tableMap = [
    'pv_module' => 'pv_module_models',
    'inverter' => 'inverter_models',
    'cable' => 'cable_models',
    'circuit_breaker' => 'circuit_breaker_models',
    'differential' => 'differential_models',
    'meter' => 'meter_models',
    'energy_meter' => 'energy_meter_models'
];

// Check if type is valid
if (!array_key_exists($type, $tableMap) || $brandId <= 0) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Get table name
$tableName = $tableMap[$type];

try {
    // Query database for models based on brand ID
    if ($type === 'pv_module') {
        $query = "SELECT id, model_name, power_options FROM {$tableName} WHERE brand_id = ? ORDER BY model_name";
    } elseif ($type === 'inverter') {
        $query = "SELECT id, model_name, nominal_power FROM {$tableName} WHERE brand_id = ? ORDER BY nominal_power ASC";
    } else {
        $query = "SELECT id, model_name FROM {$tableName} WHERE brand_id = ? ORDER BY model_name";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute([$brandId]);
    $models = $stmt->fetchAll();
    
    // Return the array of models in the format expected by JavaScript
    echo json_encode($models);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>