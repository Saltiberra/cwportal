<?php

/**
 * Autosave Site Survey Draft to SQL
 * Similar to autosave_draft.php but for site surveys
 */

require_once 'includes/auth.php';
requireLogin();
require_once 'config/database.php';

header('Content-Type: application/json');

function json_response($success, $data = [], $message = '')
{
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        json_response(false, [], 'Invalid JSON');
    }

    // Helper: normalize boolean-like values
    function normBool($v)
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_numeric($v)) return (int)$v;
        $s = strtoupper(trim((string)$v));
        if ($s === 'YES' || $s === 'SIM' || $s === 'TRUE' || $s === '1') return 1;
        if ($s === 'NO' || $s === 'NAO' || $s === 'NÃO' || $s === 'FALSE' || $s === '0') return 0;
        return null;
    }

    $surveyId = $payload['survey_id'] ?? null;
    $projectName = trim($payload['project_name'] ?? '');
    $date = $payload['date'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Basic validation: allow partial drafts. Skip if entire payload has no meaningful values
    $hasMeaningful = false;
    foreach ($payload as $pv) {
        if ($pv === null) continue;
        if (is_string($pv) && trim($pv) === '') continue;
        $hasMeaningful = true;
        break;
    }
    if (!$hasMeaningful) {
        json_response(false, [], 'Nothing to save');
    }

    // Se é novo (survey_id null), criar novo
    if ($surveyId === null) {
        $stmt = $pdo->prepare("
            INSERT INTO site_survey_reports (
                project_name, date, location, gps, responsible,
                site_survey_responsible_id, accompanied_by_name, accompanied_by_phone,
                power_to_install, certified_power,
                injection_point_type, circuit_type, inverter_location, pv_protection_board_location,
                pv_board_to_injection_distance_m, injection_has_space_for_switch, injection_has_busbar_space,
                panel_cable_exterior_to_main_gauge, panel_brand_model, breaker_brand_model,
                breaker_rated_current_a, breaker_short_circuit_current_ka, residual_current_ma,
                earth_measurement_ohms, is_bidirectional_meter,
                generator_exists, generator_mode, generator_scope,
                comm_wifi_near_pv, comm_ethernet_near_pv, comm_utp_requirement, comm_utp_length_m,
                comm_router_port_open_available, comm_router_port_number, comm_mobile_coverage_level,
                survey_notes, challenges, installation_site_notes, roof_access_available,
                permanent_ladder_feasible, is_draft, user_id, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $projectName,
            $date,
            $payload['location'] ?? '',
            $payload['gps'] ?? '',
            $payload['responsible'] ?? '',
            $payload['site_survey_responsible_id'] ?? null,
            $payload['accompanied_by_name'] ?? '',
            $payload['accompanied_by_phone'] ?? '',
            $payload['power_to_install'] ?? null,
            $payload['certified_power'] ?? null,
            $payload['injection_point_type'] ?? '',
            $payload['circuit_type'] ?? '',
            $payload['inverter_location'] ?? '',
            $payload['pv_protection_board_location'] ?? '',
            $payload['pv_board_to_injection_distance_m'] ?? null,
            normBool($payload['injection_has_space_for_switch'] ?? null),
            normBool($payload['injection_has_busbar_space'] ?? null),
            $payload['panel_cable_exterior_to_main_gauge'] ?? '',
            $payload['panel_brand_model'] ?? '',
            $payload['breaker_brand_model'] ?? '',
            $payload['breaker_rated_current_a'] ?? null,
            $payload['breaker_short_circuit_current_ka'] ?? null,
            $payload['residual_current_ma'] ?? null,
            $payload['earth_measurement_ohms'] ?? null,
            $payload['is_bidirectional_meter'] ?? null,
            $payload['generator_exists'] ?? null,
            $payload['generator_mode'] ?? '',
            $payload['generator_scope'] ?? '',
            $payload['comm_wifi_near_pv'] ?? null,
            $payload['comm_ethernet_near_pv'] ?? null,
            $payload['comm_utp_requirement'] ?? '',
            $payload['comm_utp_length_m'] ?? null,
            $payload['comm_router_port_open_available'] ?? null,
            $payload['comm_router_port_number'] ?? null,
            $payload['comm_mobile_coverage_level'] ?? null,
            $payload['survey_notes'] ?? '',
            $payload['challenges'] ?? '',
            $payload['installation_site_notes'] ?? '',
            normBool($payload['roof_access_available'] ?? null),
            normBool($payload['permanent_ladder_feasible'] ?? null),
            1,
            $userId
        ]);

        $surveyId = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'survey_id' => $surveyId, 'data' => ['survey_id' => $surveyId], 'message' => 'Survey created and autosaved']);
        exit;
    } else {
        // Atualizar existente
        $stmt = $pdo->prepare("
            UPDATE site_survey_reports SET
                project_name = ?, date = ?, location = ?, gps = ?, responsible = ?,
                site_survey_responsible_id = ?, accompanied_by_name = ?, accompanied_by_phone = ?,
                power_to_install = ?, certified_power = ?,
                injection_point_type = ?, circuit_type = ?, inverter_location = ?, pv_protection_board_location = ?,
                pv_board_to_injection_distance_m = ?, injection_has_space_for_switch = ?, injection_has_busbar_space = ?,
                panel_cable_exterior_to_main_gauge = ?, panel_brand_model = ?, breaker_brand_model = ?,
                breaker_rated_current_a = ?, breaker_short_circuit_current_ka = ?, residual_current_ma = ?,
                earth_measurement_ohms = ?, is_bidirectional_meter = ?,
                generator_exists = ?, generator_mode = ?, generator_scope = ?,
                comm_wifi_near_pv = ?, comm_ethernet_near_pv = ?, comm_utp_requirement = ?, comm_utp_length_m = ?,
                comm_router_port_open_available = ?, comm_router_port_number = ?, comm_mobile_coverage_level = ?,
                survey_notes = ?, challenges = ?, installation_site_notes = ?, roof_access_available = ?,
                permanent_ladder_feasible = ?, is_draft = 1, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $projectName,
            $date,
            $payload['location'] ?? '',
            $payload['gps'] ?? '',
            $payload['responsible'] ?? '',
            $payload['site_survey_responsible_id'] ?? null,
            $payload['accompanied_by_name'] ?? '',
            $payload['accompanied_by_phone'] ?? '',
            $payload['power_to_install'] ?? null,
            $payload['certified_power'] ?? null,
            $payload['injection_point_type'] ?? '',
            $payload['circuit_type'] ?? '',
            $payload['inverter_location'] ?? '',
            $payload['pv_protection_board_location'] ?? '',
            $payload['pv_board_to_injection_distance_m'] ?? null,
            $payload['injection_has_space_for_switch'] ?? null,
            $payload['injection_has_busbar_space'] ?? null,
            $payload['panel_cable_exterior_to_main_gauge'] ?? '',
            $payload['panel_brand_model'] ?? '',
            $payload['breaker_brand_model'] ?? '',
            $payload['breaker_rated_current_a'] ?? null,
            $payload['breaker_short_circuit_current_ka'] ?? null,
            $payload['residual_current_ma'] ?? null,
            $payload['earth_measurement_ohms'] ?? null,
            $payload['is_bidirectional_meter'] ?? null,
            $payload['generator_exists'] ?? null,
            $payload['generator_mode'] ?? '',
            $payload['generator_scope'] ?? '',
            $payload['comm_wifi_near_pv'] ?? null,
            $payload['comm_ethernet_near_pv'] ?? null,
            $payload['comm_utp_requirement'] ?? '',
            $payload['comm_utp_length_m'] ?? null,
            $payload['comm_router_port_open_available'] ?? null,
            $payload['comm_router_port_number'] ?? null,
            $payload['comm_mobile_coverage_level'] ?? null,
            $payload['survey_notes'] ?? '',
            $payload['challenges'] ?? '',
            $payload['installation_site_notes'] ?? '',
            normBool($payload['roof_access_available'] ?? null),
            normBool($payload['permanent_ladder_feasible'] ?? null),
            $surveyId
        ]);

        echo json_encode(['success' => true, 'survey_id' => $surveyId, 'data' => ['survey_id' => $surveyId], 'message' => 'Survey autosaved']);
        exit;
    }
} catch (Exception $e) {
    error_log('[AUTOSAVE_SITE_SURVEY] Error: ' . $e->getMessage());
    json_response(false, [], $e->getMessage());
}
