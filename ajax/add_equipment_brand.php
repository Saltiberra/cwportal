<?php

/**
 * Add Equipment Brand
 * 
 * This script handles the addition of new equipment brands to the database
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
$brandName = isset($_POST['brand_name']) ? trim($_POST['brand_name']) : '';

// Validate inputs
if (empty($type) || empty($brandName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Type and brand name are required']);
    exit;
}

// Determine table based on equipment type
$tableName = '';
switch ($type) {
    case 'pv_module':
        $tableName = 'pv_module_brands';
        break;
    case 'inverter':
        $tableName = 'inverter_brands';
        break;
    case 'circuit_breaker':
        $tableName = 'circuit_breaker_brands';
        break;
    case 'differential':
        $tableName = 'differential_brands';
        break;
    case 'meter':
        $tableName = 'meter_brands';
        break;
    case 'energy_meter':
        $tableName = 'energy_meter_brands';
        break;
    case 'communications':
        $tableName = 'communications_brands';
        break;
    case 'smart_meter':
        $tableName = 'smart_meter_brands';
        break;
    case 'cable':
        http_response_code(400);
        echo json_encode(['error' => 'Adding cable brands is not allowed']);
        exit;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid equipment type']);
        exit;
}

try {
    // Check if brand already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE brand_name = ?");
    $stmt->execute([$brandName]);

    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Brand already exists']);
        exit;
    }

    // Insert new brand
    $stmt = $pdo->prepare("INSERT INTO {$tableName} (brand_name) VALUES (?)");
    $stmt->execute([$brandName]);

    // Get the new brand ID
    $brandId = $pdo->lastInsertId();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Brand added successfully',
        'brand' => [
            'id' => $brandId,
            'brand_name' => $brandName
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
