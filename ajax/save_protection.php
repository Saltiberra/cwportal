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

    // If protection_id provided, it's an UPDATE
    if (isset($data['protection_id'])) {
        $protection_id = (int)$data['protection_id'];

        $update = $pdo->prepare("
            UPDATE report_protection 
            SET main_breaker_status = ?, 
                main_breaker_rating = ?, 
                dc_breaker_status = ?, 
                dc_breaker_rating = ?,
                surge_protection_status = ?,
                surge_protection_type = ?,
                grounding_status = ?,
                grounding_resistance = ?
            WHERE id = ? AND report_id = ?
        ");

        $update->execute([
            $data['main_breaker_status'] ?? null,
            $data['main_breaker_rating'] ?? null,
            $data['dc_breaker_status'] ?? null,
            $data['dc_breaker_rating'] ?? null,
            $data['surge_protection_status'] ?? null,
            $data['surge_protection_type'] ?? null,
            $data['grounding_status'] ?? null,
            $data['grounding_resistance'] ?? null,
            $protection_id,
            $report_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Protection data updated successfully',
            'protection_id' => $protection_id
        ]);
    } else {
        // INSERT new protection record
        $insert = $pdo->prepare("
            INSERT INTO report_protection 
            (report_id, main_breaker_status, main_breaker_rating, dc_breaker_status, dc_breaker_rating, 
             surge_protection_status, surge_protection_type, grounding_status, grounding_resistance)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $report_id,
            $data['main_breaker_status'] ?? null,
            $data['main_breaker_rating'] ?? null,
            $data['dc_breaker_status'] ?? null,
            $data['dc_breaker_rating'] ?? null,
            $data['surge_protection_status'] ?? null,
            $data['surge_protection_type'] ?? null,
            $data['grounding_status'] ?? null,
            $data['grounding_resistance'] ?? null
        ]);

        $protection_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Protection data saved successfully',
            'protection_id' => (int)$protection_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
