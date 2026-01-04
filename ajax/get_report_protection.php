<?php

/**
 * Get Report Protection Data
 * 
 * Carrega os dados de proteção (circuit breakers, diferenciais, etc.) 
 * de um relatório específico
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Validar report_id
$reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : null;

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'report_id required']);
    exit;
}

try {
    // Carregar dados de proteção deste relatório
    $stmt = $pdo->prepare("
        SELECT * FROM report_protection
        WHERE report_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$reportId]);
    $protectionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se encontrou dados, devolver
    if ($protectionData) {
        echo json_encode([
            'success' => true,
            'data' => $protectionData
        ]);
    } else {
        // Sem dados de proteção
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
    }
} catch (PDOException $e) {
    error_log("Error loading report protection: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
