<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/ajax_security.php'; // Ensure request is authenticated and authorized

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['table']) || !isset($data['id'])) {
        throw new Exception("table and id are required");
    }

    $table = $data['table'];
    $id = (int)$data['id'];

    // Whitelist allowed tables to prevent SQL injection
    $allowed_tables = [
        'report_modules',
        'report_inverters',
        'report_inverter_equipment',
        'report_equipment', // allow generic deletions of equipment rows (inverters, protections, etc.)
        'report_layouts',
        'report_protection',
        'report_protection_cables',
        'report_clamp_measurements',
        'report_earth_protection',
        'report_homopolar_protection',
        'report_string_measurements',
        'report_telemetry_credentials',
        'report_communications',
        'report_telemetry_meters',
        'report_energy_meters',
        'report_punch_list',
        'report_additional_notes'
    ];

    if (!in_array($table, $allowed_tables)) {
        throw new Exception("Invalid table");
    }

    // If this is a report-specific table, try to validate ownership using report_id
    if (strpos($table, 'report_') === 0) {
        try {
            $stmtRpt = $pdo->prepare("SELECT report_id FROM `$table` WHERE id = ?");
            $stmtRpt->execute([$id]);
            $r = $stmtRpt->fetch(PDO::FETCH_ASSOC);
            if ($r && isset($r['report_id'])) {
                // Validate that the current user can operate on this report
                validateAjaxRequest($pdo, ['report_id' => $r['report_id']]);
            }
        } catch (Throwable $e) {
            // If query fails (table has no report_id column), continue — other endpoints may provide additional checks
        }
    }

    // Delete the record
    $delete = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
    $delete->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Record deleted successfully',
        'rows_affected' => $delete->rowCount()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
