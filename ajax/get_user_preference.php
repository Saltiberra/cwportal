<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// If the request is made via AJAX/API and the session is not authenticated,
// return a JSON 401 response instead of letting requireLogin() redirect.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
try {
    $key = isset($_GET['key']) ? trim($_GET['key']) : null;
    if (!$key) throw new Exception('Missing key');

    try {
        $stmt = $pdo->prepare("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'key' => $key, 'value' => $row ? $row['pref_value'] : null]);
    } catch (PDOException $pe) {
        // If the preferences table doesn't exist, return success with null value
        if ($pe->getCode() === '42S02') {
            echo json_encode(['success' => true, 'key' => $key, 'value' => null, 'warning' => 'preferences_table_missing']);
        } else {
            throw $pe;
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
