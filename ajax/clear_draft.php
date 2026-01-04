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
    $key = $_GET['key'] ?? null;

    if (!$key) {
        http_response_json(['success' => false, 'error' => 'Missing key parameter']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Delete the specific draft for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = ?");
    $stmt->execute([$userId, $key]);

    http_response_json([
        'success' => true,
        'message' => 'Draft cleared successfully',
        'key' => $key
    ]);
} catch (Exception $e) {
    error_log('Error clearing draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
