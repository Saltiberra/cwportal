<?php
/**
 * AJAX Handler for fetching inverter data
 * 
 * Returns MPPT and string configuration for a specific inverter model
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get inverter ID from request
$inverterId = isset($_GET['inverter_id']) ? intval($_GET['inverter_id']) : 0;

if ($inverterId <= 0) {
    echo json_encode(['error' => 'Invalid inverter ID']);
    exit;
}

try {
    // Query database for inverter details
    $stmt = $pdo->prepare("
        SELECT 
            im.id,
            im.model_name,
            im.nominal_power,
            im.max_output_current,
            im.mppts,
            im.strings_per_mppt,
            ib.brand_name
        FROM 
            inverter_models im
        JOIN 
            inverter_brands ib ON im.brand_id = ib.id
        WHERE 
            im.id = ?
    ");
    
    $stmt->execute([$inverterId]);
    $inverter = $stmt->fetch();
    
    if (!$inverter) {
        echo json_encode(['error' => 'Inverter not found']);
        exit;
    }
    
    // Return inverter data as JSON
    echo json_encode($inverter);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>