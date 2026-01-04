<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/ajax_security.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // 🔒 SECURITY: Validate report ownership before proceeding
    validateAjaxRequest($pdo, $data);

    $report_id = (int)$data['report_id'];

    // Report ownership already validated above
    if (!$check->fetch()) {
        throw new Exception("Report not found");
    }

    // If note_id provided, it's an UPDATE
    if (isset($data['note_id'])) {
        $note_id = (int)$data['note_id'];

        $update = $pdo->prepare("
            UPDATE report_additional_notes 
            SET note_title = ?, 
                note_content = ?, 
                note_category = ?, 
                note_type = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['note_title'] ?? null,
            $data['note_content'] ?? null,
            $data['note_category'] ?? null,
            $data['note_type'] ?? null,
            $note_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Note updated successfully',
            'note_id' => $note_id
        ]);
    } else {
        // INSERT new note
        $insert = $pdo->prepare("
            INSERT INTO report_additional_notes 
            (report_id, note_title, note_content, note_category, note_type)
            VALUES (?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['note_title'] ?? null,
            $data['note_content'] ?? null,
            $data['note_category'] ?? null,
            $data['note_type'] ?? null
        ]);

        $note_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Note saved successfully',
            'note_id' => (int)$note_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
