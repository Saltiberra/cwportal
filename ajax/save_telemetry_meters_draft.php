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

    if (!isset($input['telemetry_meters']) || !is_array($input['telemetry_meters'])) {
        http_response_json(['success' => false, 'error' => 'Invalid telemetry meters data']);
        exit;
    }

    $telemetryMeters = $input['telemetry_meters'];
    $userId = $_SESSION['user_id'];

    // Delete existing telemetry meters for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'telemetry_meters'");
    $stmt->execute([$userId]);

    // Insert new telemetry meters
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'telemetry_meters', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($telemetryMeters)]);

    http_response_json([
        'success' => true,
        'count' => count($telemetryMeters),
        'message' => 'Telemetry meters draft saved successfully'
    ]);
} catch (Exception $e) {
    error_log('Error saving telemetry meters draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
