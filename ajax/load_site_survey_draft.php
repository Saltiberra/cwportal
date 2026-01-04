<?php

/**
 * Load Site Survey Draft from SQL
 */

require_once 'includes/auth.php';
requireLogin();
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    $surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;

    if ($surveyId > 0) {
        // Load a specific survey draft by id
        $stmt = $pdo->prepare("
        SELECT
            id, project_name, date, location, gps, responsible,
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
            permanent_ladder_feasible
        FROM site_survey_reports
        WHERE id = ? AND is_deleted = FALSE
        LIMIT 1
    ");

        $stmt->execute([$surveyId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Survey not found']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // No survey_id - attempt to load latest draft for current user
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT
            id, project_name, date, location, gps, responsible,
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
            permanent_ladder_feasible
        FROM site_survey_reports
        WHERE user_id = ? AND is_deleted = FALSE AND is_draft = 1
        ORDER BY updated_at DESC
        LIMIT 1");

    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No draft found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log('[LOAD_SITE_SURVEY_DRAFT] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
