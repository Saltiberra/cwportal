<?php
/**
 * Add EPC Company
 * 
 * This script handles the addition of new EPC companies to the database
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
$epcName = isset($_POST['epc_name']) ? trim($_POST['epc_name']) : '';

// Validate inputs
if (empty($epcName)) {
    http_response_code(400);
    echo json_encode(['error' => 'EPC name is required']);
    exit;
}

try {
    // Check if EPC already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM epcs WHERE name = ?");
    $stmt->execute([$epcName]);
    
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'EPC company already exists']);
        exit;
    }
    
    // Insert new EPC
    $stmt = $pdo->prepare("INSERT INTO epcs (name) VALUES (?)");
    $stmt->execute([$epcName]);
    
    // Get the new EPC ID
    $epcId = $pdo->lastInsertId();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'EPC company added successfully',
        'epc' => [
            'id' => $epcId,
            'name' => $epcName
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>