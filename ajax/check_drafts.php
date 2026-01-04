<?php

/**
 * Verifica estatísticas dos rascunhos na base de dados
 */
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Contar rascunhos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM report_drafts");
    $countRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Contar sessões únicas
    $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) as sessions FROM report_drafts");
    $sessionsRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Tamanho médio
    $stmt = $pdo->query("SELECT AVG(LENGTH(form_data)) as avg_size FROM report_drafts");
    $sizeRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Última atualização (usar updated_at que existe na tabela)
    $stmt = $pdo->query("SELECT MAX(updated_at) as last_updated FROM report_drafts");
    $updateRow = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => (int)$countRow['count'],
        'sessions' => (int)$sessionsRow['sessions'],
        'avg_size' => round($sizeRow['avg_size'] ?? 0),
        'last_updated' => $updateRow['last_updated']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
