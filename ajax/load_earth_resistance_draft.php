<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_json(['success' => false, 'error' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'];

    // If report_id is provided, try to load from report_drafts for this session
    $reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : null;
    if ($reportId) {
        $sessionId = session_id();
        // 1) Try session-specific draft (preferred)
        $stmt = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$reportId, $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $formData = json_decode($row['form_data'], true) ?: [];
            $earthResistance = isset($formData['earth_resistance']) ? $formData['earth_resistance'] : '';
            http_response_json(['success' => true, 'earth_resistance' => $earthResistance, 'source' => 'report_drafts_session']);
            exit;
        }

        // 2) If not found, try the latest draft for this report_id (tolerant fallback)
        $stmt2 = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt2->execute([$reportId]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row2) {
            $formData = json_decode($row2['form_data'], true) ?: [];
            $earthResistance = isset($formData['earth_resistance']) ? $formData['earth_resistance'] : '';
            http_response_json(['success' => true, 'earth_resistance' => $earthResistance, 'source' => 'report_drafts_any']);
            exit;
        }
        // No report_drafts found: fallthrough to user-level form_drafts
    }

    // Fallback: load from per-user form_drafts
    $stmt = $pdo->prepare("SELECT data_value FROM form_drafts WHERE user_id = ? AND data_key = 'earth_resistance' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $earthResistance = json_decode($result['data_value'], true);
    } else {
        $earthResistance = '';
    }

    http_response_json([
        'success' => true,
        'earth_resistance' => $earthResistance,
        'source' => 'user_form_drafts'
    ]);
} catch (Exception $e) {
    error_log('Error loading earth resistance draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
