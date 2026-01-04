<?php
/**
 * AJAX Handler for fetching communications models
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get parameters from request
$equipmentType = isset($_GET['equipment_type']) ? $_GET['equipment_type'] : '';

try {
    // Query database for communications models
    if (!empty($equipmentType)) {
        // Filter by equipment type
        $query = "SELECT id, model_name, manufacturer, characteristics FROM communications_models WHERE equipment_type = ? ORDER BY model_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$equipmentType]);
    } else {
        // Return all models
        $query = "SELECT id, model_name, equipment_type, manufacturer, characteristics FROM communications_models ORDER BY equipment_type, model_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    }

    $models = $stmt->fetchAll();

    // Return models in the format expected by JavaScript
    echo json_encode($models);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}