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

    // Load energy meters from database
    $stmt = $pdo->prepare("SELECT data_value FROM form_drafts WHERE user_id = ? AND data_key = 'energy_meters' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $energyMeters = json_decode($result['data_value'], true);
        if (!is_array($energyMeters)) {
            $energyMeters = [];
        }
    } else {
        $energyMeters = [];
    }

    http_response_json([
        'success' => true,
        'energy_meters' => $energyMeters
    ]);
} catch (Exception $e) {
    error_log('Error loading energy meters draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
