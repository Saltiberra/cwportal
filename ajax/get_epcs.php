<?php
/**
 * AJAX Handler for fetching EPCs
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Query database for EPCs
    $stmt = $pdo->prepare("SELECT id, name FROM epcs ORDER BY name");
    $stmt->execute();
    $epcs = $stmt->fetchAll();
    
    // Return EPCs as JSON
    echo json_encode($epcs);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>