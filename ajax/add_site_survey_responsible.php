<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required']);
    exit;
}

try {
    // Check duplicate
    $check = $pdo->prepare('SELECT id FROM site_survey_responsibles WHERE name = ?');
    $check->execute([$name]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Responsible already exists']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO site_survey_responsibles (name) VALUES (?)');
    $stmt->execute([$name]);
    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'responsible' => [
            'id' => $id,
            'name' => $name
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add site survey responsible: ' . $e->getMessage()]);
}
