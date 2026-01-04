<?php
/**
 * AJAX Handler for fetching report equipment data
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get parameters from request
$reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
$equipmentType = isset($_GET['type']) ? $_GET['type'] : '';

if ($reportId <= 0) {
    echo json_encode(['error' => 'Invalid report ID']);
    exit;
}

try {
    // Query database for equipment
    $stmt = $pdo->prepare("
        SELECT * FROM report_equipment 
        WHERE report_id = ? 
        " . ($equipmentType ? "AND equipment_type = ?" : "") . "
        ORDER BY id
    ");
    
    if ($equipmentType) {
        $stmt->execute([$reportId, $equipmentType]);
    } else {
        $stmt->execute([$reportId]);
    }
    
    $equipment = $stmt->fetchAll();
    
    // Return equipment as JSON
    echo json_encode($equipment);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>