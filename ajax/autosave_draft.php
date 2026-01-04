<?php

/**
 * AutoSave Draft Endpoint
 * Guarda automaticamente todos os dados do formulário no SQL
 */

// Start output buffering to prevent stray output from breaking JSON
ob_start();
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Obter dados do formulário
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// 🔧 SUPORTE PARA LIMPAR DRAFTS ANTIGOS
if (isset($data['action']) && $data['action'] === 'clear_old_drafts') {
    try {
        $sessionId = $data['session_id'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        if ($sessionId) {
            // Limpar drafts desta sessão que não têm report_id (novos relatórios não salvos)
            $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE session_id = ? AND (report_id IS NULL OR report_id = 0)");
            $stmt->execute([$sessionId]);
            $deleted = $stmt->rowCount();
            error_log("[CLEAR DRAFTS] Deleted {$deleted} old drafts for session {$sessionId}");
        }

        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['success' => true, 'message' => 'Old drafts cleared', 'deleted_count' => $deleted ?? 0]);
        exit;
    } catch (Exception $e) {
        error_log("[CLEAR DRAFTS] Error: " . $e->getMessage());
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// 🎯 SISTEMA SIMPLIFICADO: Usar APENAS session_id do PHP
$phpSessionId = session_id();
$reportId = isset($data['report_id']) && !empty($data['report_id']) ? intval($data['report_id']) : null;
$userId = $_SESSION['user_id'] ?? null;

error_log("[AUTOSAVE] session_id={$phpSessionId}, report_id={$reportId}, user_id={$userId}");

// Obter current_tab se existir
$currentTab = isset($data['current_tab']) ? $data['current_tab'] : null;

try {
    // 🎯 LÓGICA SIMPLES: 
    // 1. Se temos report_id → procurar por report_id
    // 2. Se NÃO temos report_id → procurar por session_id + user_id + report_id NULL
    $existingDraft = null;

    if ($reportId) {
        // EDITANDO relatório existente
        $stmt = $pdo->prepare("
            SELECT id FROM report_drafts
            WHERE report_id = ?
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$reportId]);
        $existingDraft = $stmt->fetch();
        error_log("[AUTOSAVE] Edit mode - looking for draft with report_id={$reportId}, found: " . ($existingDraft ? 'YES' : 'NO'));
    } else {
        // NOVO relatório - procurar por session_id + user_id + report_id NULL
        $stmt = $pdo->prepare("
            SELECT id FROM report_drafts
            WHERE session_id = ? 
            AND (user_id = ? OR user_id IS NULL)
            AND report_id IS NULL
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$phpSessionId, $userId]);
        $existingDraft = $stmt->fetch();
        error_log("[AUTOSAVE] New report mode - looking for draft with session_id={$phpSessionId}, user_id={$userId}, found: " . ($existingDraft ? 'YES' : 'NO'));
    }

    // Serializar todos os dados do formulário
    $formDataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    error_log("[AUTOSAVE DRAFT] 📊 Saving report_id={$reportId}");
    error_log("[AUTOSAVE DRAFT] Form data keys: " . implode(', ', array_keys($data)));
    error_log("[AUTOSAVE DRAFT] protection_data present: " . (isset($data['protection_data']) ? 'YES' : 'NO'));
    if (isset($data['protection_data'])) {
        error_log("[AUTOSAVE DRAFT] protection_data type: " . gettype($data['protection_data']));
        error_log("[AUTOSAVE DRAFT] protection_data count: " . count($data['protection_data']));
        if (is_array($data['protection_data']) && count($data['protection_data']) > 0) {
            error_log("[AUTOSAVE DRAFT] protection_data[0]: " . json_encode($data['protection_data'][0]));
        }
    }
    error_log("[AUTOSAVE DRAFT] JSON size: " . strlen($formDataJson) . " bytes");

    if ($existingDraft) {
        // Atualizar rascunho existente
        error_log("[AUTOSAVE] ✏️ Updating draft id={$existingDraft['id']}");
        $stmt = $pdo->prepare("
            UPDATE report_drafts 
            SET form_data = ?, 
                report_id = ?,
                user_id = ?,
                session_id = ?,
                current_tab = ?,
                updated_at = CURRENT_TIMESTAMP,
                version = version + 1
            WHERE id = ?
        ");
        $stmt->execute([$formDataJson, $reportId, $userId, $phpSessionId, $currentTab, $existingDraft['id']]);
        $draftId = $existingDraft['id'];
        error_log("[AUTOSAVE] ✅ Updated draft id={$draftId}");
    } else {
        // Criar novo rascunho
        error_log("[AUTOSAVE] ➕ Creating new draft");

        // Gerar draft_id único
        $draftIdUnique = hash('sha256', $phpSessionId . ':' . ($userId ?? 'anon') . ':' . time() . ':' . uniqid());

        $stmt = $pdo->prepare("
            INSERT INTO report_drafts (
                draft_id, 
                session_id, 
                user_id,
                form_data, 
                report_id, 
                current_tab, 
                version, 
                is_completed, 
                expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, 0, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");

        $stmt->execute([
            $draftIdUnique,
            $phpSessionId,
            $userId,
            $formDataJson,
            $reportId,
            $currentTab
        ]);
        $draftId = $pdo->lastInsertId();
        error_log("[AUTOSAVE] ✅ Created new draft id={$draftId}");
    }

    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'success' => true,
        'draft_id' => $draftId,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Draft saved successfully',
        'data_size' => strlen($formDataJson)
    ]);
    exit;
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate entry') !== false && strpos($msg, "for key 'PRIMARY'") !== false) {
        error_log("[AUTOSAVE] Detected duplicate primary key error; attempting fallback insert. Error: {$msg}");
        try {
            // Calculate a safe new id
            $maxRow = $pdo->query("SELECT COALESCE(MAX(id),0) AS maxid FROM report_drafts")->fetch();
            $safeId = (int)$maxRow['maxid'] + 1;

            // Use the same draft_id unique or generate a new one if not available
            $fallbackDraftId = $draftIdUnique ?? hash('sha256', $phpSessionId . ':' . ($userId ?? 'anon') . ':' . time() . ':' . uniqid());

            // Prepare manual insert including id
            $ins = $pdo->prepare("INSERT INTO report_drafts (id, draft_id, session_id, user_id, form_data, report_id, current_tab, version, is_completed, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $ins->execute([$safeId, $fallbackDraftId, $phpSessionId, $userId, $formDataJson, $reportId, $currentTab]);
            $draftId = $safeId;
            error_log("[AUTOSAVE] ✅ Fallback created new draft id={$draftId}");

            if (ob_get_length()) {
                ob_clean();
            }
            echo json_encode([
                'success' => true,
                'draft_id' => $draftId,
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Draft saved successfully (fallback)',
                'data_size' => strlen($formDataJson)
            ]);
            exit;
        } catch (PDOException $e2) {
            error_log("[AUTOSAVE] Fallback insert also failed: " . $e2->getMessage());
            http_response_code(500);
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e2->getMessage()]);
            exit;
        }
    }
    error_log("Autosave error: " . $e->getMessage());
    http_response_code(500);
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
    exit;
}
