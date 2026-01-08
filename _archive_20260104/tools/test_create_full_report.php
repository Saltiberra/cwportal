<?php
// Full-field automated test: login and create a comprehensive site survey report
$base = 'http://localhost/cleanwattsportal/';
$cookieJar = __DIR__ . '/cookiejar_full.txt';
@unlink($cookieJar);

function curlPost($url, $fields, $cookieJar)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    if ($res === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);
    return [$res, $info];
}

function curlPostJson($url, $data, $cookieJar)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_POST, true);
    $payload = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    if ($res === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);
    return [$res, $info];
}

try {
    echo "== Login admin/admin ==\n";
    list($body, $info) = curlPost($base . 'login.php', ['username' => 'admin', 'password' => 'admin'], $cookieJar);
    echo "Login HTTP: " . ($info['http_code'] ?? 'N/A') . "\n";

    // init session
    echo "== Init form session ==\n";
    list($initRes, $initInfo) = curlPost($base . 'ajax/init_form_session.php?report_id=0', [], $cookieJar);
    echo "Init HTTP: " . ($initInfo['http_code'] ?? 'N/A') . " Response: " . substr($initRes, 0, 400) . "\n";

    // Build a comprehensive payload
    $payload = [
        'action' => 'create',
        'project_name' => 'FULL TEST SURVEY ' . date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'location' => 'Test Address, Industrial Park',
        'gps' => '40.270110,-8.107783',
        'map_area_m2' => 2500.12,
        'map_azimuth_deg' => 45,
        'map_polygon_coords' => [[40.27, -8.10], [40.28, -8.11], [40.26, -8.12]],
        'site_survey_responsible_id' => 3,
        'responsible' => 'Admin Tester',
        'accompanied_by_name' => 'Client Rep',
        'accompanied_by_phone' => '111222333',
        'power_to_install' => 50.0,
        'certified_power' => 49.5,
        'parapet_height_m' => 0.8,
        'mount_location_type' => 'Edge',
        'roof_type' => 'Metal',
        'support_structure' => 'Truss',
        'survey_notes' => 'All fields filled for automated QA test',
        'challenges' => 'Access limited on east side',
        // Electrical
        'injection_point_type' => 'Panel',
        'circuit_type' => 'TT',
        'inverter_location' => 'Roof North',
        'pv_protection_board_location' => 'Near Inverter',
        'pv_board_to_injection_distance_m' => 12.5,
        'injection_has_space_for_switch' => 1,
        'injection_has_busbar_space' => 1,
        'panel_cable_exterior_to_main_gauge' => '4x95mm2',
        'panel_brand_model' => 'Schneider X',
        'breaker_brand_model' => 'ABB T',
        'breaker_rated_current_a' => 63,
        'breaker_short_circuit_current_ka' => 10.0,
        'residual_current_ma' => 300,
        'earth_measurement_ohms' => 5.2,
        'is_bidirectional_meter' => 1,
        'generator_exists' => 1,
        'generator_mode' => 'Automatico',
        'generator_scope' => 'Toda Instalacao',
        // Communications
        'comm_wifi_near_pv' => 1,
        'comm_ethernet_near_pv' => 1,
        'comm_utp_requirement' => 'Yes',
        'comm_utp_length_m' => 30,
        'comm_router_port_open_available' => 1,
        'comm_router_port_number' => 2,
        'comm_mobile_coverage_level' => 4,
        // Installation extras
        'roof_access_available' => 1,
        'permanent_ladder_feasible' => 1,
        // Buildings (array)
        'building_details' => [
            ['name' => 'Building A', 'parapet_height_m' => 0.7, 'mount_location_type' => 'Edge', 'roof_type' => 'Metal', 'support_structure' => 'Steel'],
            ['name' => 'Building B', 'parapet_height_m' => 0.4, 'mount_location_type' => 'Center', 'roof_type' => 'Concrete', 'support_structure' => 'RC']
        ],
        // Roof details grouped by building
        'roof_details' => [
            'Building A' => [
                ['identification' => 'A-R1', 'angle_pitch' => 10, 'orientation' => 'S', 'roof_condition' => 1, 'structure_visual' => 'Good', 'structure_weight_load' => 'High', 'structure_wind_coverage' => 'Low']
            ],
            'Building B' => [
                ['identification' => 'B-R1', 'angle_pitch' => 5, 'orientation' => 'E', 'roof_condition' => 2, 'structure_visual' => 'Fair']
            ]
        ],
        // Shading with objects
        'shading_details' => [
            'Building A' => ['status' => 'partial', 'viable' => 1, 'notes' => 'Trees on NE corner', 'objects' => [['object_type' => 'tree', 'cause' => 'natural', 'height_m' => 6, 'quantity' => 3, 'notes' => 'prune recommended']]],
            'Building B' => ['status' => 'none', 'viable' => 1, 'notes' => 'No shading', 'objects' => []]
        ],
        // Checklist
        'checklist_data' => [
            ['key' => 's1', 'label' => 'Access ok', 'status' => 'ok', 'note' => 'clear'],
            ['key' => 's2', 'label' => 'Structure OK', 'status' => 'ok', 'note' => 'no issues']
        ],
        'photo_checklist' => [
            ['key' => 'p1', 'label' => 'Facade'],
            ['key' => 'p2', 'label' => 'Roof R1']
        ],
        'photos_link' => 'https://example.com/qa/photos_full_test',
    ];

    echo "== Sending full payload to save_site_survey.php ==\n";
    list($resp, $rinfo) = curlPostJson($base . 'save_site_survey.php', $payload, $cookieJar);
    echo "Save HTTP: " . ($rinfo['http_code'] ?? 'N/A') . "\n";
    echo "Save response: " . substr($resp, 0, 800) . "\n";

    $dec = json_decode($resp, true);
    if (!$dec || empty($dec['success'])) {
        throw new Exception('Save failed: ' . $resp);
    }
    $surveyId = intval($dec['survey_id'] ?? 0);
    if ($surveyId <= 0) throw new Exception('Invalid survey_id returned');
    echo "Survey created id = $surveyId\n";

    // DB checks
    require __DIR__ . '/../config/database.php';
    $checks = [];
    $tables = [
        'site_survey_reports' => "SELECT * FROM site_survey_reports WHERE id = ?",
        'site_survey_buildings' => "SELECT * FROM site_survey_buildings WHERE report_id = ?",
        'site_survey_roofs' => "SELECT * FROM site_survey_roofs WHERE report_id = ?",
        'site_survey_shading' => "SELECT * FROM site_survey_shading WHERE report_id = ?",
        'site_survey_shading_objects' => "SELECT * FROM site_survey_shading_objects WHERE report_id = ?",
        'site_survey_items' => "SELECT * FROM site_survey_items WHERE report_id = ?"
    ];
    foreach ($tables as $name => $sql) {
        $st = $pdo->prepare($sql);
        $st->execute([$surveyId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo "Table $name: " . count($rows) . " rows\n";
        if ($name === 'site_survey_items') {
            foreach ($rows as $r) {
                echo " - item_type=" . ($r['item_type'] ?? '') . " item_key=" . ($r['item_key'] ?? '') . " value=" . ($r['value'] ?? '') . "\n";
            }
        }
    }

    echo "== Full test completed OK ==\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(2);
}
