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

    if (!isset($input['layouts']) || !is_array($input['layouts'])) {
        http_response_json(['success' => false, 'error' => 'Invalid layouts data']);
        exit;
    }

    $layouts = $input['layouts'];
    $userId = $_SESSION['user_id'];

    // Delete existing layouts for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'layouts'");
    $stmt->execute([$userId]);

    // Insert new layouts
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'layouts', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($layouts)]);

    http_response_json([
        'success' => true,
        'count' => count($layouts),
        'message' => 'Layouts draft saved successfully'
    ]);
} catch (Exception $e) {
    error_log('Error saving layouts draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
