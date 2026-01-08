<?php

/**
 * Verifica se a tabela report_drafts existe
 */
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'report_drafts'");
    $tableExists = $stmt->rowCount() > 0;

    $response = [
        'exists' => $tableExists,
        'columns' => 0,
        'last_updated' => null
    ];

    if ($tableExists) {
        // Contar colunas
        $stmt = $pdo->query("SHOW COLUMNS FROM report_drafts");
        $response['columns'] = $stmt->rowCount();

        // Pegar última atualização (usar updated_at que existe na tabela)
        $stmt = $pdo->query("SELECT MAX(updated_at) as last_updated FROM report_drafts");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['last_updated'] = $row['last_updated'];
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
