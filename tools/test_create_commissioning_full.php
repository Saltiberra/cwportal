<?php
// Automated test: create a comprehensive commissioning report and fetch generated HTML
$base = 'http://localhost/cleanwattsportal/';
$cookieJar = __DIR__ . '/cookiejar_comm.txt';
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

try {
    echo "== Login admin/admin ==\n";
    list($body, $info) = curlPost($base . 'login.php', ['username' => 'admin', 'password' => 'admin'], $cookieJar);
    echo "Login HTTP: " . ($info['http_code'] ?? 'N/A') . "\n";

    // Build payload with a wide set of fields
    $payload = [
        'project_name' => 'COMMISSIONING FULL TEST ' . date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'responsible' => 'Commission Tester',
        'plant_location' => 'Test Plant 1, Industrial Park',
        'gps' => '40.2701,-8.1077',
        'map_area_m2' => 3000.5,
        'map_azimuth_deg' => 90,
        'map_polygon_coords' => json_encode([[40.27, -8.10], [40.28, -8.11], [40.26, -8.12]]),
        'epc_id' => 1,
        'representative_id' => 1,
        'commissioning_responsible_id' => 1,
        'technician' => 'Tech Unit 1',
        'installed_power' => 60.5,
        'total_power' => 61.0,
        'certified_power' => 59.8,
        'cpe' => 'CPE-12345',
        // protection
        'protection_data' => json_encode([
            ['scope_text' => 'Main', 'brand_name' => 'BrandA', 'model_name' => 'CB-200', 'rated_current' => '63']
        ]),
        'protection_cable_data' => json_encode([
            ['scope_text' => 'Main', 'brand_name' => 'BrandCable', 'model_name' => 'CableX', 'size' => '4x95']
        ]),
        // modules
        'modules_data' => json_encode([
            ['brand_name' => 'ModuleBrand', 'model_name' => 'M-380', 'model_id' => 1, 'quantity' => 160, 'status' => 'new', 'power_rating' => 380]
        ]),
        // inverters
        'inverters_data' => json_encode([
            ['brand_name' => 'InverterBrand', 'model_name' => 'I-50kW', 'model_id' => 1, 'quantity' => 1, 'status' => 'new', 'serial_number' => 'SN123', 'location' => 'Inverter room', 'max_output_current' => 100]
        ]),
        // homopolar
        'homopolar_installer' => 'Installer Ltd',
        'homopolar_brand' => 'HomBrand',
        'homopolar_model' => 'H-1',
        // system layout example
        'layout_data' => json_encode([
            ['label' => 'Array 1', 'description' => 'Roof east array', 'sort_order' => 1]
        ]),
    ];

    echo "== Posting to save_report.php ==\n";
    list($resp, $rinfo) = curlPost($base . 'save_report.php', $payload, $cookieJar);
    echo "Save HTTP: " . ($rinfo['http_code'] ?? 'N/A') . "\n";
    echo "Save response snippet: " . substr($resp, 0, 400) . "\n";

    // Try to extract created report id from response
    if (preg_match('/Report created: .*\b(\d+)\b/', $resp, $m)) {
        $reportId = intval($m[1]);
    } else {
        // fallback: query latest created by this session/user
        require __DIR__ . '/../config/database.php';
        $stmt = $pdo->prepare('SELECT id FROM commissioning_reports ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $reportId = intval($r['id'] ?? 0);
    }

    if ($reportId <= 0) throw new Exception('Could not determine created report id');

    echo "Created report id = $reportId\n";

    // Fetch generated HTML
    $genUrl = $base . 'generate_report_new.php?id=' . $reportId;
    echo "== Fetching generated HTML for report $reportId ==\n";
    $ch = curl_init($genUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    echo "Generate HTTP: " . ($info['http_code'] ?? 'N/A') . "\n";

    $outFile = __DIR__ . '/generated_commissioning_report_' . $reportId . '.html';
    file_put_contents($outFile, $html);
    echo "Saved generated HTML to $outFile\n";

    // Basic checks: look for common error indicators
    $errors = [];
    if (stripos($html, 'Warning') !== false) $errors[] = 'Contains "Warning"';
    if (stripos($html, 'Notice') !== false) $errors[] = 'Contains "Notice"';
    if (stripos($html, 'Fatal error') !== false) $errors[] = 'Contains "Fatal error"';
    if (stripos($html, 'Undefined') !== false) $errors[] = 'Contains "Undefined"';

    if (!empty($errors)) {
        echo "Potential issues found in generated HTML:\n" . implode("\n", $errors) . "\n";
    } else {
        echo "No obvious PHP notices or errors found in generated HTML.\n";
    }

    echo "== Done ==\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(2);
}
