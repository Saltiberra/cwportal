<?php

/**
 * Check Session Status
 *
 * Simple endpoint to verify if user session is still valid
 * Returns JSON response indicating login status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    if (!isSessionValid()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session expired']);
        exit;
    }

    // Refresh sliding expiration
    refreshSession();

    $user = getCurrentUser();
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
