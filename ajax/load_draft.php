<?php

/**
 * Load Draft Endpoint
 * Carrega o rascunho mais recente do SQL
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// 🎯 SISTEMA SIMPLIFICADO: Usar APENAS session_id do PHP
$phpSessionId = session_id();
$userId = $_SESSION['user_id'] ?? null;
$reportId = isset($_GET['report_id']) && !empty($_GET['report_id']) ? intval($_GET['report_id']) : null;

error_log("[LOAD DRAFT] session_id={$phpSessionId}, user_id={$userId}, report_id={$reportId}");

try {
    $draft = null;

    if ($reportId) {
        // EDIT MODE: try to find a draft attached to this report_id and return it
        $stmt = $pdo->prepare("SELECT form_data, updated_at, id, version, current_tab FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$reportId]);
        $draft = $stmt->fetch();
        error_log("[LOAD DRAFT] Edit mode - looking for draft for report_id={$reportId}, found: " . ($draft ? 'YES (id=' . $draft['id'] . ')' : 'NO'));
        if ($draft) {
            $formData = json_decode($draft['form_data'], true);
            echo json_encode([
                'success' => true,
                'data' => $formData,
                'last_updated' => $draft['updated_at'],
                'draft_id' => $draft['id'],
                'version' => $draft['version'],
                'current_tab' => $draft['current_tab']
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No draft found for this report'
            ]);
            exit;
        }
    } else {
        // NOVO relatório - procurar por session_id + user_id + report_id NULL
        $stmt = $pdo->prepare("
            SELECT form_data, updated_at, id, version, current_tab
            FROM report_drafts 
            WHERE session_id = ?
            AND (user_id = ? OR user_id IS NULL)
            AND report_id IS NULL
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$phpSessionId, $userId]);
        $draft = $stmt->fetch();

        error_log("[LOAD DRAFT] New report mode - looking for draft, found: " . ($draft ? 'YES (id=' . $draft['id'] . ')' : 'NO'));
    }

    if ($draft) {
        $formData = json_decode($draft['form_data'], true);
        echo json_encode([
            'success' => true,
            'data' => $formData,
            'last_updated' => $draft['updated_at'],
            'draft_id' => $draft['id'],
            'version' => $draft['version'],
            'current_tab' => $draft['current_tab']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No draft found'
        ]);
    }
} catch (PDOException $e) {
    error_log("Load draft error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
