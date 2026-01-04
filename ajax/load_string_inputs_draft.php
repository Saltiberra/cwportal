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
    $userId = $_SESSION['user_id'];

    // Load string inputs draft
    $stmt = $pdo->prepare("
        SELECT data_value FROM form_drafts
        WHERE user_id = ? AND data_key = 'string_inputs'
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'success' => true,
            'string_inputs' => json_decode($result['data_value'], true)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No string inputs data found'
        ]);
    }
} catch (Exception $e) {
    http_response_json(['success' => false, 'error' => $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
