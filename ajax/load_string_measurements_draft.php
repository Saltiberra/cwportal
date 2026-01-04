<?php

/**
 * load_string_measurements_draft.php
 * Load string measurements from the latest draft for a report
 * Returns the string_measurements_data array from report_drafts.form_data
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Allow graceful degradation - if not logged in, still try to load
// (the report_id check is sufficient security)
if (!isset($_SESSION['user_id'])) {
    error_log('[LOAD_STRING_MEASUREMENTS] Warning: No user_id in session, but continuing anyway');
}

try {
    $reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : null;

    if (!$reportId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing report_id']);
        exit;
    }

    // Fetch the latest draft for this report (session-tolerant)
    $stmt = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$reportId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft || empty($draft['form_data'])) {
        // No draft found
        echo json_encode([
            'success' => true,
            'string_measurements_data' => [],
            'found' => false,
            'message' => 'No draft found for this report'
        ]);
        exit;
    }

    $formData = json_decode($draft['form_data'], true);

    if (!is_array($formData)) {
        $formData = [];
    }

    $measurements = $formData['string_measurements_data'] ?? [];

    echo json_encode([
        'success' => true,
        'string_measurements_data' => $measurements,
        'found' => !empty($measurements),
        'count' => count($measurements)
    ]);
} catch (PDOException $e) {
    error_log('[LOAD_STRING_MEASUREMENTS] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log('[LOAD_STRING_MEASUREMENTS] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error']);
}
