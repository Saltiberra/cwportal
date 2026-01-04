<?php

/**
 * AJAX Handler for fetching PV module model details
 */

// Include configuration file
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get module ID from request
$modelId = isset($_GET['model_id']) ? intval($_GET['model_id']) : 0;

if ($modelId <= 0) {
    echo json_encode(['error' => 'Invalid model ID']);
    exit;
}

try {
    // Query database for module details
    $query = "SELECT id, model_name, power_options FROM pv_module_models WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$modelId]);
    $model = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($model) {
        echo json_encode($model);
    } else {
        echo json_encode(['error' => 'Model not found']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
