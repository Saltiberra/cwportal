<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id, name FROM site_survey_responsibles WHERE active = 1 ORDER BY name");
    $stmt->execute();
    $responsibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($responsibles);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load site survey responsibles: ' . $e->getMessage()]);
}
