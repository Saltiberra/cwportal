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

    // Load notes from database
    $stmt = $pdo->prepare("SELECT data_value FROM form_drafts WHERE user_id = ? AND data_key = 'notes' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $notes = json_decode($result['data_value'], true);
    } else {
        $notes = '';
    }

    http_response_json([
        'success' => true,
        'notes' => $notes
    ]);
} catch (Exception $e) {
    error_log('Error loading notes draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
