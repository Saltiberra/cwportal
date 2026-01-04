<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_json(['success' => false, 'error' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['clamp_measurements']) || !is_array($input['clamp_measurements'])) {
        http_response_json(['success' => false, 'error' => 'Invalid clamp measurements data']);
        exit;
    }

    $clampMeasurements = $input['clamp_measurements'];
    $userId = $_SESSION['user_id'];

    // Delete existing clamp measurements for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'clamp_measurements'");
    $stmt->execute([$userId]);

    // Insert new clamp measurements
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'clamp_measurements', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($clampMeasurements)]);

    http_response_json([
        'success' => true,
        'count' => count($clampMeasurements),
        'message' => 'Clamp measurements draft saved successfully'
    ]);
} catch (Exception $e) {
    error_log('Error saving clamp measurements draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
