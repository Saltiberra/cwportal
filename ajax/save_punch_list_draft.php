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

    if (!isset($input['punch_list']) || !is_array($input['punch_list'])) {
        http_response_json(['success' => false, 'error' => 'Invalid punch list data']);
        exit;
    }

    $punchList = $input['punch_list'];
    $userId = $_SESSION['user_id'];

    // Delete existing punch list for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'punch_list'");
    $stmt->execute([$userId]);

    // Insert new punch list
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'punch_list', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($punchList)]);

    http_response_json([
        'success' => true,
        'count' => count($punchList),
        'message' => 'Punch list draft saved successfully'
    ]);
} catch (Exception $e) {
    error_log('Error saving punch list draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
