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

    if (!isset($input['protection_cables']) || !is_array($input['protection_cables'])) {
        http_response_json(['success' => false, 'error' => 'Invalid protection cables data']);
        exit;
    }

    $protection_cables = $input['protection_cables'];
    $userId = $_SESSION['user_id'];

    // Delete existing protection_cables for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'protection_cables'");
    $stmt->execute([$userId]);

    // Insert new protection_cables
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'protection_cables', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($protection_cables)]);

    http_response_json([
        'success' => true,
        'count' => count($protection_cables),
        'message' => 'Protection cables draft saved successfully'
    ]);
} catch (Exception $e) {
    error_log('Error saving protection cables draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
