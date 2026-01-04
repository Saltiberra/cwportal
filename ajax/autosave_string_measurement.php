<?php

/**
 * autosave_string_measurement.php
 * Autosave endpoint for individual string measurements
 * Saves MPPT table data to report_drafts in real-time as the user types
 * 
 * Expected POST parameters:
 * - report_id (int): the commissioning report ID
 * - inverter_index (int): index of the inverter in the list
 * - mppt (int): MPPT number
 * - string_num (int): String number
 * - metric (string): field name (voc, isc, vmp, imp, rins, irr, temp, rlo, notes)
 * - value (string): the measurement value
 * - inverter_id (string, optional): inverter model ID for reference
 * - mppt_data (array, optional): if provided, save entire MPPT dataset instead of single metric
 */

session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_json(['success' => false, 'error' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required parameters
    if (!isset($input['report_id']) || !is_numeric($input['report_id'])) {
        http_response_json(['success' => false, 'error' => 'Invalid or missing report_id']);
        exit;
    }

    $reportId = intval($input['report_id']);
    $sessionId = session_id();
    $metricName = isset($input['metric']) ? $input['metric'] : 'batch';

    error_log("[STRING_AUTOSAVE] Received autosave for report_id={$reportId}, metric={$metricName}");

    // 🔒 Fetch the latest draft for this report (ignore session for tolerance)
    $stmt = $pdo->prepare("SELECT id, form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$reportId]);
    $draftRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $formData = [];
    $draftId = null;

    if ($draftRow) {
        $draftId = $draftRow['id'];
        $formData = json_decode($draftRow['form_data'], true) ?: [];
    } else {
        // If no draft exists, create an empty one
        error_log("[STRING_AUTOSAVE] No draft found for report_id={$reportId}; will create one");
        $draftId = null; // Will be created below
    }

    // Initialize string_measurements_data if not present
    if (!isset($formData['string_measurements_data']) || !is_array($formData['string_measurements_data'])) {
        $formData['string_measurements_data'] = [];
    }

    // Determine if this is a single metric update or batch update
    $updateType = isset($input['mppt_data']) ? 'batch' : 'single';

    if ($updateType === 'batch' && is_array($input['mppt_data'])) {
        // Batch update: entire MPPT table(s) passed
        error_log("[STRING_AUTOSAVE] 📦 Batch update received with " . count($input['mppt_data']) . " entries");
        $formData['string_measurements_data'] = $input['mppt_data'];
    } else {
        // Single metric update: find or create the measurement entry
        $inverterIndex = isset($input['inverter_index']) ? intval($input['inverter_index']) : null;
        $mppt = isset($input['mppt']) ? intval($input['mppt']) : null;
        $stringNum = isset($input['string_num']) ? intval($input['string_num']) : null;
        $metric = isset($input['metric']) ? $input['metric'] : null;
        $value = isset($input['value']) ? $input['value'] : '';

        if ($inverterIndex === null || $mppt === null || $stringNum === null || $metric === null) {
            http_response_json(['success' => false, 'error' => 'Missing required fields for single metric update']);
            exit;
        }

        // Find existing entry in string_measurements_data
        $entryKey = null;
        foreach ($formData['string_measurements_data'] as $idx => $entry) {
            if (($entry['inverter_index'] ?? null) == $inverterIndex &&
                ($entry['mppt'] ?? null) == $mppt &&
                ($entry['string_num'] ?? null) == $stringNum
            ) {
                $entryKey = $idx;
                break;
            }
        }

        // If entry not found, create a new one
        if ($entryKey === null) {
            $entryKey = count($formData['string_measurements_data']);
            $formData['string_measurements_data'][$entryKey] = [
                'inverter_index' => $inverterIndex,
                'inverter_id' => $input['inverter_id'] ?? '',
                'mppt' => $mppt,
                'string_num' => $stringNum,
                'voc' => '',
                'isc' => '',
                'vmp' => '',
                'imp' => '',
                'rins' => '',
                'irr' => '',
                'temp' => '',
                'rlo' => '',
                'notes' => '',
                'current' => ''
            ];
            error_log("[STRING_AUTOSAVE] ➕ Created new entry for inv={$inverterIndex}, mppt={$mppt}, str={$stringNum}");
        }

        // Update the metric
        $oldValue = $formData['string_measurements_data'][$entryKey][$metric] ?? '';
        $formData['string_measurements_data'][$entryKey][$metric] = $value;

        error_log("[STRING_AUTOSAVE] 📝 Updated {$metric}: '{$oldValue}' → '{$value}' for inv={$inverterIndex}, mppt={$mppt}, str={$stringNum}");
    }

    // Serialize updated form_data
    $newFormData = json_encode($formData, JSON_UNESCAPED_UNICODE);

    // Update or create draft
    if ($draftId) {
        // Update existing draft
        $stmt = $pdo->prepare("UPDATE report_drafts SET form_data = ?, updated_at = CURRENT_TIMESTAMP, version = version + 1 WHERE id = ?");
        $stmt->execute([$newFormData, $draftId]);
        error_log("[STRING_AUTOSAVE] ✏️ Updated existing draft id={$draftId}");
    } else {
        // Create new draft
        $draftIdUnique = hash('sha256', session_id() . ':' . time() . ':' . uniqid());
        $stmt = $pdo->prepare("INSERT INTO report_drafts (draft_id, session_id, form_data, report_id, version, is_completed, expires_at) VALUES (?, ?, ?, ?, 1, 0, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->execute([$draftIdUnique, $sessionId, $newFormData, $reportId]);
        $draftId = $pdo->lastInsertId();
        error_log("[STRING_AUTOSAVE] ➕ Created new draft id={$draftId} for report_id={$reportId}");
    }

    http_response_json([
        'success' => true,
        'message' => 'String measurement autosaved successfully',
        'draft_id' => $draftId,
        'timestamp' => date('Y-m-d H:i:s'),
        'update_type' => $updateType,
        'data_size' => strlen($newFormData)
    ]);
} catch (PDOException $e) {
    error_log('[STRING_AUTOSAVE] ❌ Database error: ' . $e->getMessage());
    http_response_code(500);
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('[STRING_AUTOSAVE] ❌ Unexpected error: ' . $e->getMessage());
    http_response_code(500);
    http_response_json(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
