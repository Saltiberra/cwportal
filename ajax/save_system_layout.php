<?php

/**
 * Save System Layout to Database (Autosave)
 * Endpoint: /ajax/save_system_layout.php
 * 
 * Recebe array de layouts e grava na tabela report_system_layout
 * DELETE todos os layouts antigos e INSERT os novos (idempotente)
 */

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // LOG para debug
    error_log("[Autosave Layout] Request received: " . print_r($data, true));

    if (!isset($data['report_id'])) {
        throw new Exception("report_id é obrigatório");
    }

    $report_id = intval($data['report_id']);
    $layouts = $data['layouts'] ?? [];

    // Validar que report existe
    $check = $pdo->prepare("SELECT id FROM commissioning_reports WHERE id = ?");
    $check->execute([$report_id]);
    if (!$check->fetch()) {
        throw new Exception("Report não encontrado");
    }

    // BEGIN TRANSACTION
    $pdo->beginTransaction();

    // DELETE todos os layouts antigos
    $delete = $pdo->prepare("DELETE FROM report_system_layout WHERE report_id = ?");
    $delete->execute([$report_id]);

    // INSERT layouts novos
    $insert = $pdo->prepare("
        INSERT INTO report_system_layout 
        (report_id, roof_id, quantity, azimuth, tilt, mounting, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($layouts as $index => $layout) {
        $insert->execute([
            $report_id,
            $layout['roof_id'] ?? '',
            intval($layout['quantity'] ?? 1),
            floatval($layout['azimuth'] ?? 0),
            floatval($layout['tilt'] ?? 0),
            $layout['mounting'] ?? '',
            $index
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Layouts guardados com sucesso',
        'count' => count($layouts),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
