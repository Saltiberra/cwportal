<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['report_id'])) {
        throw new Exception("report_id is required");
    }

    $report_id = (int)$data['report_id'];

    // Verify report exists
    $check = $pdo->prepare("SELECT id FROM commissioning_reports WHERE id = ?");
    $check->execute([$report_id]);
    if (!$check->fetch()) {
        throw new Exception("Report not found");
    }

    // If item_id provided, it's an UPDATE
    if (isset($data['item_id'])) {
        $item_id = (int)$data['item_id'];

        $update = $pdo->prepare("
            UPDATE report_punch_list 
            SET issue_description = ?, 
                issue_priority = ?, 
                issue_status = ?, 
                assigned_to = ?, 
                due_date = ?,
                resolution_notes = ?
            WHERE id = ? AND report_id = ?
        ");

        // Normalize priority to IEC-style values (High, Medium, Low)
        $rawPriority = $data['issue_priority'] ?? null;
        $priority = null;
        if ($rawPriority !== null) {
            $rp = strtolower((string)$rawPriority);
            if (in_array($rp, ['high', 'severe', 'critical'])) $priority = 'High';
            elseif (in_array($rp, ['medium', 'major'])) $priority = 'Medium';
            elseif ($rp === 'low' || $rp === 'minor' || $rp === '') $priority = 'Low';
            else $priority = ucfirst($rp);
        }

        $update->execute([
            $data['issue_description'] ?? null,
            $priority,
            $data['issue_status'] ?? null,
            $data['assigned_to'] ?? null,
            $data['due_date'] ?? null,
            $data['resolution_notes'] ?? null,
            $item_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Punch list item updated successfully',
            'item_id' => $item_id
        ]);
    } else {
        // INSERT new punch list item
        $insert = $pdo->prepare("
            INSERT INTO report_punch_list 
            (report_id, issue_description, issue_priority, issue_status, assigned_to, due_date, resolution_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        // Normalize priority to IEC-style values (High, Medium, Low)
        $rawPriority = $data['issue_priority'] ?? null;
        $priority = null;
        if ($rawPriority !== null) {
            $rp = strtolower((string)$rawPriority);
            if (in_array($rp, ['high', 'severe', 'critical'])) $priority = 'High';
            elseif (in_array($rp, ['medium', 'major'])) $priority = 'Medium';
            elseif ($rp === 'low' || $rp === 'minor' || $rp === '') $priority = 'Low';
            else $priority = ucfirst($rp);
        }

        $insert->execute([
            $report_id,
            $data['issue_description'] ?? null,
            $priority,
            $data['issue_status'] ?? null,
            $data['assigned_to'] ?? null,
            $data['due_date'] ?? null,
            $data['resolution_notes'] ?? null
        ]);

        $item_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Punch list item saved successfully',
            'item_id' => (int)$item_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
