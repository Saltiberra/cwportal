<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load users: ' . $e->getMessage()]);
}
