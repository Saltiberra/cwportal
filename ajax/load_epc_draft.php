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

    // Load EPC selection from database
    $stmt = $pdo->prepare("SELECT data_value FROM form_drafts WHERE user_id = ? AND data_key = 'epc_id' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $epcId = json_decode($result['data_value'], true);
    } else {
        $epcId = '';
    }

    http_response_json([
        'success' => true,
        'epc_id' => $epcId
    ]);
} catch (Exception $e) {
    error_log('Error loading EPC selection draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
