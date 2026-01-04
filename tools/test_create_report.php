<?php
// Automated test: login and create a site survey report via JSON API
$base = 'http://localhost/cleanwattsportal/';
$cookieJar = __DIR__ . '/cookiejar.txt';
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
    // 1) Login
    echo "== Logging in as admin/admin ==\n";
    list($body, $info) = curlPost($base . 'login.php', ['username' => 'admin', 'password' => 'admin'], $cookieJar);
    echo "Login HTTP code: " . ($info['http_code'] ?? 'N/A') . "\n";

    // 2) Ensure session works by calling init_form_session.php
    echo "== Initializing form session (sanity) ==\n";
    $initUrl = $base . 'ajax/init_form_session.php?report_id=0';
    $ch = curl_init($initUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    $initRes = curl_exec($ch);
    $initInfo = curl_getinfo($ch);
    curl_close($ch);
    echo "Init HTTP code: " . ($initInfo['http_code'] ?? 'N/A') . "\n";
    echo "Init response: " . substr($initRes, 0, 400) . "\n";

    // 3) Prepare payload
    $payload = [
        'action' => 'create',
        'project_name' => 'AUTO TEST PROJECT ' . date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'location' => 'Test Location',
        'gps' => '40.0,-8.0',
        'map_area_m2' => 123.45,
        'map_azimuth_deg' => 180,
        'site_survey_responsible_id' => null,
        'responsible' => 'Automated Tester',
        'accompanied_by_name' => 'Auto Assist',
        'accompanied_by_phone' => '000000000',
        'power_to_install' => 5.5,
        'certified_power' => 5.5,
        'survey_notes' => 'Automated test note',
        'checklist_data' => [['key' => 'c1', 'label' => 'Check 1', 'status' => 'ok', 'note' => 'note 1']],
        'photo_checklist' => [['key' => 'p1', 'label' => 'Photo 1']],
        'building_details' => [['name' => 'Building A', 'parapet_height_m' => 0.5, 'mount_location_type' => 'edge', 'roof_type' => 'metal', 'support_structure' => 'truss']],
        'roof_details' => ['Building A' => [['identification' => 'R1', 'angle_pitch' => 10, 'orientation' => 'S', 'roof_condition' => 1, 'structure_visual' => 'ok']]],
        'shading_details' => ['Building A' => ['status' => 'partial', 'viable' => 1, 'notes' => 'Small trees', 'objects' => [['object_type' => 'tree', 'cause' => 'natural', 'height_m' => 4, 'quantity' => 2, 'notes' => 'near edge']]]],
        'photos_link' => 'https://example.com/photos_repo/test',
        // minimal electrical/comm fields
        'injection_point_type' => 'panel',
        'comm_wifi_near_pv' => 1,
    ];

    echo "== Sending create request to save_site_survey.php ==\n";
    list($resp, $rinfo) = curlPostJson($base . 'save_site_survey.php', $payload, $cookieJar);

    echo "Save HTTP code: " . ($rinfo['http_code'] ?? 'N/A') . "\n";
    echo "Save response: " . substr($resp, 0, 800) . "\n";

    $dec = json_decode($resp, true);
    if (!$dec || empty($dec['success'])) {
        throw new Exception('Save failed or returned non-success: ' . $resp);
    }

    $surveyId = intval($dec['survey_id'] ?? 0);
    if ($surveyId <= 0) throw new Exception('Invalid survey_id returned: ' . $resp);

    echo "Created survey id = $surveyId\n";

    // 4) Sanity checks in DB
    require __DIR__ . '/../config/database.php';
    $c = $pdo->prepare('SELECT COUNT(*) AS c FROM site_survey_reports WHERE id = ?');
    $c->execute([$surveyId]);
    $r = $c->fetch(PDO::FETCH_ASSOC);
    echo "Reports found: " . intval($r['c']) . "\n";

    $b = $pdo->prepare('SELECT COUNT(*) AS c FROM site_survey_buildings WHERE report_id = ?');
    $b->execute([$surveyId]);
    echo "Buildings saved: " . intval($b->fetch(PDO::FETCH_ASSOC)['c']) . "\n";

    $ro = $pdo->prepare('SELECT COUNT(*) AS c FROM site_survey_roofs WHERE report_id = ?');
    $ro->execute([$surveyId]);
    echo "Roofs saved: " . intval($ro->fetch(PDO::FETCH_ASSOC)['c']) . "\n";

    $sh = $pdo->prepare('SELECT COUNT(*) AS c FROM site_survey_shading WHERE report_id = ?');
    $sh->execute([$surveyId]);
    echo "Shading saved: " . intval($sh->fetch(PDO::FETCH_ASSOC)['c']) . "\n";

    $so = $pdo->prepare('SELECT COUNT(*) AS c FROM site_survey_shading_objects WHERE report_id = ?');
    $so->execute([$surveyId]);
    echo "Shading objects saved: " . intval($so->fetch(PDO::FETCH_ASSOC)['c']) . "\n";

    $ph = $pdo->prepare("SELECT value FROM site_survey_items WHERE report_id = ? AND item_type = 'Survey - Photos Link' LIMIT 1");
    $ph->execute([$surveyId]);
    $pv = $ph->fetch(PDO::FETCH_ASSOC);
    echo "Photos link saved: " . ($pv['value'] ?? 'NULL') . "\n";

    echo "== Test completed successfully ==\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(2);
}
