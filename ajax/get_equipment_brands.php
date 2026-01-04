<?php
/**
 * AJAX Handler for fetching equipment brands
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get equipment type from request
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Map type to database table
$tableMap = [
    'pv_module' => 'pv_module_brands',
    'inverter' => 'inverter_brands',
    'cable' => 'cable_brands',
    'circuit_breaker' => 'circuit_breaker_brands',
    'differential' => 'differential_brands',
    'meter' => 'meter_brands',
    'energy_meter' => 'energy_meter_brands'
];

// Check if type is valid
if (!array_key_exists($type, $tableMap)) {
    echo json_encode(['error' => 'Invalid equipment type']);
    exit;
}

// Get table name
$tableName = $tableMap[$type];

try {
    // Query database for brands
    $query = "SELECT id, brand_name FROM {$tableName} ORDER BY brand_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $brands = $stmt->fetchAll();
    
    // Simple: return just the array of brands without additional structure
    // This is the format that the original JavaScript expects
    echo json_encode($brands);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>