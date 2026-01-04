<?php
/**
 * AJAX Handler for fetching representatives by EPC
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get EPC ID from request
$epcId = isset($_GET['epc_id']) ? intval($_GET['epc_id']) : 0;

if ($epcId <= 0) {
    echo json_encode(['error' => 'Invalid EPC ID']);
    exit;
}

try {
    // Query database for representatives
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            phone, 
            email
        FROM 
            representatives 
        WHERE 
            epc_id = ? 
        ORDER BY 
            name
    ");
    
    $stmt->execute([$epcId]);
    $representatives = $stmt->fetchAll();
    
    // Return representatives as JSON
    echo json_encode($representatives);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>