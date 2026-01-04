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
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['earth_resistance'])) {
        http_response_json(['success' => false, 'error' => 'Invalid earth resistance data']);
        exit;
    }

    $earthResistance = $input['earth_resistance'];
    $userId = $_SESSION['user_id'];

    // If report_id is provided, save into report_drafts (merge into existing form_data)
    $reportId = isset($input['report_id']) && !empty($input['report_id']) ? intval($input['report_id']) : null;
    if ($reportId) {
        $sessionId = session_id();

        // Try to fetch the most recent draft for this report/session
        $stmt = $pdo->prepare("SELECT id, form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$reportId, $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $formData = json_decode($row['form_data'], true) ?: [];
            $formData['earth_resistance'] = $earthResistance;
            $newJson = json_encode($formData, JSON_UNESCAPED_UNICODE);

            $upd = $pdo->prepare("UPDATE report_drafts SET form_data = ?, updated_at = CURRENT_TIMESTAMP, version = version + 1 WHERE id = ?");
            $upd->execute([$newJson, $row['id']]);
        } else {
            // Create a minimal form_data object containing the earth_resistance
            $formData = ['earth_resistance' => $earthResistance];
            $formDataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);

            $draftIdUnique = hash('sha256', session_id() . ':' . time() . ':' . uniqid());
            $ins = $pdo->prepare("INSERT INTO report_drafts (draft_id, session_id, form_session_id, form_data, report_id, current_tab, version, is_completed, expires_at) VALUES (?, ?, NULL, ?, ?, NULL, 1, 0, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $ins->execute([$draftIdUnique, $sessionId, $formDataJson, $reportId]);
        }

        http_response_json([
            'success' => true,
            'message' => 'Earth resistance saved into report_drafts',
            'report_id' => $reportId
        ]);
        exit;
    }

    // Fallback: save into per-user form_drafts (legacy behavior)
    // Delete existing earth resistance for this user
    $stmt = $pdo->prepare("DELETE FROM form_drafts WHERE user_id = ? AND data_key = 'earth_resistance'");
    $stmt->execute([$userId]);

    // Insert new earth resistance
    $stmt = $pdo->prepare("INSERT INTO form_drafts (user_id, data_key, data_value, created_at, updated_at)
                          VALUES (?, 'earth_resistance', ?, NOW(), NOW())");
    $stmt->execute([$userId, json_encode($earthResistance, JSON_UNESCAPED_UNICODE)]);

    http_response_json([
        'success' => true,
        'message' => 'Earth resistance draft saved successfully (user-level)'
    ]);
} catch (Exception $e) {
    error_log('Error saving earth resistance draft: ' . $e->getMessage());
    http_response_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function http_response_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
