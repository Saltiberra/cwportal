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
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $key = isset($payload['key']) ? trim($payload['key']) : (isset($_POST['key']) ? trim($_POST['key']) : null);
    $value = isset($payload['value']) ? $payload['value'] : (isset($_POST['value']) ? $_POST['value'] : null);
    if (!$key) throw new Exception('Missing key');

    // Upsert user preference
    try {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$_SESSION['user_id'], $key, $value]);
        echo json_encode(['success' => true, 'key' => $key]);
    } catch (PDOException $pe) {
        // If the table doesn't exist, attempt to create it and retry once
        if ($pe->getCode() === '42S02') {
            $createSql = "CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                pref_key VARCHAR(191) NOT NULL,
                pref_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY user_pref_unique (user_id, pref_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            try {
                $pdo->exec($createSql);
                // Retry insert
                $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$_SESSION['user_id'], $key, $value]);
                echo json_encode(['success' => true, 'key' => $key, 'note' => 'preferences_table_created']);
            } catch (Exception $e2) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create preferences table: ' . $e2->getMessage()]);
            }
        } else {
            throw $pe;
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
