<?php

/**
 * CLOSE PUNCH ITEM ENDPOINT
 * 
 * Marca um punch list item como completo/fechado
 * 
 * POST params:
 * - id: punch list item ID
 * 
 * Response: JSON success/error
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// 🔒 Require authentication
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID do punch item é obrigatório'
    ]);
    exit;
}

try {
    $itemId = (int)$input['id'];

    // Determine which status column exists and build update accordingly
    $colsStmt = $pdo->query("SHOW COLUMNS FROM report_punch_list");
    if (!$colsStmt) {
        throw new Exception('Não foi possível ler colunas de report_punch_list');
    }
    $cols = array_map(function ($r) {
        return $r['Field'];
    }, $colsStmt->fetchAll(PDO::FETCH_ASSOC));

    // Detect resolution date column
    $resDateCol = null;
    foreach (['resolution_date', 'resolved_at', 'closed_at', 'date_resolved'] as $c) {
        if (in_array($c, $cols)) {
            $resDateCol = $c;
            break;
        }
    }

    // Build SET parts dynamically
    $setParts = [];
    $params = [];

    if (in_array('issue_status', $cols)) {
        $setParts[] = "issue_status = 'Closed'";
    } elseif (in_array('status', $cols)) {
        $setParts[] = "status = 'Closed'";
    } elseif (in_array('is_completed', $cols)) {
        $setParts[] = "is_completed = 1";
    } else {
        throw new Exception('Could not identify status column to close item');
    }

    if ($resDateCol) {
        // Use only date portion (YYYY-MM-DD) per requirement
        $setParts[] = "$resDateCol = CURDATE()";
    }
    if (in_array('updated_at', $cols)) {
        $setParts[] = "updated_at = NOW()";
    }

    $sql = "UPDATE report_punch_list SET " . implode(', ', $setParts) . " WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$itemId]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Punch item marcado como concluído'
        ]);
    } else {
        throw new Exception('Falha ao atualizar punch item');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
