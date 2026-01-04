<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required']);
    exit;
}

try {
    // Check if responsible already exists
    $checkStmt = $pdo->prepare("SELECT id FROM commissioning_responsibles WHERE name = ?");
    $checkStmt->execute([$name]);

    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Commissioning responsible with this name already exists']);
        exit;
    }

    // Insert new responsible
    $insertStmt = $pdo->prepare("INSERT INTO commissioning_responsibles (name) VALUES (?)");
    $insertStmt->execute([$name]);

    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'responsible' => [
            'id' => $newId,
            'name' => $name
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add commissioning responsible: ' . $e->getMessage()]);
}
