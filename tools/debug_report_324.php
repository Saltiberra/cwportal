<?php
// Force local config
$cfg = [
    'host' => 'localhost',
    'name' => 'cleanwattsportal',
    'user' => 'root',
    'pass' => '',
    'port' => 3306,
];

$dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$reportId = 324;

echo "Checking inverters for report_id=$reportId\n";
$stmt = $pdo->prepare("SELECT id, inverter_brand_id, inverter_model_id FROM report_inverters WHERE report_id = ? ORDER BY id");
$stmt->execute([$reportId]);
$inverters = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Inverters: " . count($inverters) . " rows\n";
foreach ($inverters as $i => $inv) {
    echo "  Index $i: ID {$inv['id']}, inverter_model_id: '{$inv['inverter_model_id']}'\n";
}

echo "Checking draft for report_id=$reportId\n";
$stmt = $pdo->prepare("SELECT id, form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$reportId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);
if ($draft) {
    echo "Draft found, ID: {$draft['id']}\n";
    $formData = json_decode($draft['form_data'], true);
    if (isset($formData['string_measurements_data'])) {
        $sm = $formData['string_measurements_data'];
        echo "string_measurements_data: " . (is_array($sm) ? count($sm) : gettype($sm)) . " items\n";
        if (is_array($sm)) {
            foreach ($sm as $i => $item) {
                echo "  Item $i: " . json_encode($item) . "\n";
            }
        }
    } else {
        echo "No string_measurements_data in draft\n";
    }
} else {
    echo "No draft found\n";
}
echo "\n";

echo "Checking data for report_id=$reportId\n\n";

// Check mppt_string_measurements
$stmt = $pdo->prepare("SELECT * FROM mppt_string_measurements WHERE report_id = ?");
$stmt->execute([$reportId]);
$mppt = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "mppt_string_measurements: " . count($mppt) . " rows\n";
foreach ($mppt as $row) {
    echo "  ID: {$row['id']}, Inv: {$row['inverter_index']}, MPPT: {$row['mppt']}, Str: {$row['string_num']}, Voc: '{$row['voc']}', Isc: '{$row['isc']}', Notes: '{$row['notes']}'\n";
}

echo "\n";

// Check report_equipment fallback
$stmt = $pdo->prepare("SELECT * FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement'");
$stmt->execute([$reportId]);
$equip = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "report_equipment (String Measurement): " . count($equip) . " rows\n";
foreach ($equip as $row) {
    echo "  ID: {$row['id']}, Characteristics: '{$row['characteristics']}'\n";
}

echo "\n";

// Test loadReportData
require_once '../includes/load_report_data.php';
$data = loadReportData($reportId, $pdo);
$measurements = $data['string_measurements'] ?? [];
echo "loadReportData string_measurements: " . count($measurements) . " rows\n";
foreach ($measurements as $m) {
    echo "  InvIdx: {$m['inverter_index']}, MPPT: {$m['mppt']}, Str: {$m['string_num']}, Voc: '{$m['voc']}', Isc: '{$m['isc']}', Notes: '{$m['notes']}'\n";
}
