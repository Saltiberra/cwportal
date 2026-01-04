<?php
require_once __DIR__ . '/../config/database.php';
$reportId = isset($argv[1]) ? intval($argv[1]) : 0;
if (!$reportId) {
    echo "Usage: php debug_strings_mapping.php <report_id>\n";
    exit(1);
}
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Load inverters for that report
    $invertersStmt = $pdo->prepare("SELECT model, model_id, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Inverter'");
    $invertersStmt->execute([$reportId]);
    $inverters = $invertersStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found inverters: " . count($inverters) . "\n";
    foreach ($inverters as $i => $inv) {
        echo "  Index {$i} -> model_id=" . ($inv['model_id'] ?? '') . " model=" . ($inv['model'] ?? '') . "\n";
    }

    // Load all equipment rows for this report to see what exists
    $stmtAll = $pdo->prepare("SELECT id, equipment_type, characteristics, model_id FROM report_equipment WHERE report_id = ? ORDER BY id");
    $stmtAll->execute([$reportId]);
    $allEquip = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
    echo "Total equipment rows for this report: " . count($allEquip) . "\n";
    foreach ($allEquip as $ae) {
        echo "  id={$ae['id']} type={$ae['equipment_type']} model_id=" . ($ae['model_id'] ?? '') . " chars=" . substr($ae['characteristics'] ?? '', 0, 120) . "\n";
    }
    echo "\n";

    // Show any equipment rows that have MPPT in characteristics across database (help find report owner)
    $stmtMppt = $pdo->prepare("SELECT id, report_id, characteristics FROM report_equipment WHERE characteristics LIKE '%MPPT:%' ORDER BY report_id, id LIMIT 100");
    $stmtMppt->execute();
    $mpptRows = $stmtMppt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found MPPT rows across DB: " . count($mpptRows) . "\n";
    foreach ($mpptRows as $mr) {
        echo "  report_id={$mr['report_id']} id={$mr['id']} chars=" . substr($mr['characteristics'] ?? '', 0, 120) . "\n";
    }
    echo "\n";

    // Load string measurements specifically
    $stmtSE = $pdo->prepare("SELECT id, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' ORDER BY id");
    $stmtSE->execute([$reportId]);
    $rowsSE = $stmtSE->fetchAll(PDO::FETCH_ASSOC);
    echo "Found string measurements in equipment: " . count($rowsSE) . "\n";

    // Also show any mppt_string_measurements for this report (AJAX-populated table)
    $stmtMpptSpecific = $pdo->prepare("SELECT id, inverter_index, mppt, string_num, voc, isc, rins, irr, temp, rlo, notes FROM mppt_string_measurements WHERE report_id = ? ORDER BY inverter_index, mppt, string_num");
    $stmtMpptSpecific->execute([$reportId]);
    $mpptSpecificRows = $stmtMpptSpecific->fetchAll(PDO::FETCH_ASSOC);
    echo "Found mppt_string_measurements for this report: " . count($mpptSpecificRows) . "\n";
    foreach ($mpptSpecificRows as $mr) {
        echo "  id=" . ($mr['id'] ?? '') . " idx=" . ($mr['inverter_index'] ?? '') . " mppt=" . ($mr['mppt'] ?? '') . " s=" . ($mr['string_num'] ?? '') . " voc=" . ($mr['voc'] ?? '') . "\n";
    }

    $stringMeasurements = [];
    foreach ($rowsSE as $row) {
        $item = [
            'inverter_id' => '',
            'mppt' => '',
            'string_num' => '',
            'voc' => '',
            'isc' => '',
            'vmp' => '',
            'imp' => '',
            'rins' => '',
            'irr' => '',
            'temp' => '',
            'rlo' => '',
            'notes' => ''
        ];
        $parts = array_map('trim', explode('|', $row['characteristics'] ?? ''));
        foreach ($parts as $p) {
            if (stripos($p, 'Inverter:') === 0) $item['inverter_id'] = trim(substr($p, strlen('Inverter:')));
            elseif (stripos($p, 'MPPT:') === 0) $item['mppt'] = trim(substr($p, strlen('MPPT:')));
            elseif (stripos($p, 'String:') === 0) $item['string_num'] = trim(substr($p, strlen('String:')));
            elseif (stripos($p, 'Voc:') === 0) $item['voc'] = trim(preg_replace('/V$/i', '', substr($p, strlen('Voc:'))));
            elseif (stripos($p, 'Current:') === 0) $item['isc'] = trim(preg_replace('/A$/i', '', substr($p, strlen('Current:'))));
            elseif (stripos($p, 'Isc:') === 0) $item['isc'] = trim(preg_replace('/A$/i', '', substr($p, strlen('Isc:'))));
            elseif (stripos($p, 'Vmp:') === 0) $item['vmp'] = trim(preg_replace('/V$/i', '', substr($p, strlen('Vmp:'))));
            elseif (stripos($p, 'Imp:') === 0) $item['imp'] = trim(preg_replace('/A$/i', '', substr($p, strlen('Imp:'))));
            elseif (stripos($p, 'R.INS:') === 0) $item['rins'] = trim(substr($p, strlen('R.INS:')));
            elseif (stripos($p, 'Irr:') === 0) $item['irr'] = trim(substr($p, strlen('Irr:')));
            elseif (stripos($p, 'Irrad:') === 0) $item['irr'] = trim(substr($p, strlen('Irrad:')));
            elseif (stripos($p, 'Temp:') === 0) $item['temp'] = trim(substr($p, strlen('Temp:')));
            elseif (stripos($p, 'Temperature:') === 0) $item['temp'] = trim(substr($p, strlen('Temperature:')));
            elseif (stripos($p, 'R.LO:') === 0) $item['rlo'] = trim(substr($p, strlen('R.LO:')));
            elseif (stripos($p, 'Notes:') === 0) $item['notes'] = trim(substr($p, strlen('Notes:')));
        }
        $stringMeasurements[] = $item;
    }

    // Merge equipment and mppt measurements and build server side mapping
    $serverToApply = [];
    // Build initial mapping from legacy equipment entries
    $serversMap = [];
    foreach ($stringMeasurements as $sm) {
        $invIdx = 0;
        foreach ($inverters as $ii => $inv) {
            if (isset($inv['model_id']) && (string)$inv['model_id'] === (string)($sm['inverter_id'] ?? '')) {
                $invIdx = $ii;
                break;
            }
        }
        $mppt = isset($sm['mppt']) ? intval($sm['mppt']) : 1;
        $s = isset($sm['string_num']) ? intval($sm['string_num']) : 1;
        $key = "{$invIdx}_{$mppt}_{$s}";
        if (!isset($serversMap[$key])) $serversMap[$key] = [];
        if (!empty($sm['voc'])) $serversMap[$key]['voc'] = $sm['voc'];
        if (!empty($sm['isc'])) $serversMap[$key]['isc'] = $sm['isc'];
        if (!empty($sm['rins'])) $serversMap[$key]['rins'] = $sm['rins'];
        if (!empty($sm['irr'])) $serversMap[$key]['irr'] = $sm['irr'];
        if (!empty($sm['temp'])) $serversMap[$key]['temp'] = $sm['temp'];
        if (!empty($sm['rlo'])) $serversMap[$key]['rlo'] = $sm['rlo'];
    }

    // Overlay mppt-specific rows on top of legacy ones

    // Overlay mppt-specific rows on top of legacy ones
    foreach ($mpptSpecificRows as $mr) {
        $invIdx = isset($mr['inverter_index']) ? intval($mr['inverter_index']) : 0;
        $mppt = isset($mr['mppt']) ? intval($mr['mppt']) : 1;
        $s = isset($mr['string_num']) ? intval($mr['string_num']) : 1;
        $key = "{$invIdx}_{$mppt}_{$s}";
        if (!isset($serversMap[$key])) $serversMap[$key] = [];
        if (!empty($mr['voc'])) $serversMap[$key]['voc'] = $mr['voc'];
        if (!empty($mr['isc'])) $serversMap[$key]['isc'] = $mr['isc'];
        if (!empty($mr['rins'])) $serversMap[$key]['rins'] = $mr['rins'];
        if (!empty($mr['irr'])) $serversMap[$key]['irr'] = $mr['irr'];
        if (!empty($mr['temp'])) $serversMap[$key]['temp'] = $mr['temp'];
        if (!empty($mr['rlo'])) $serversMap[$key]['rlo'] = $mr['rlo'];
    }

    echo "Merged server mapping keys (" . count($serversMap) . "):\n";
    foreach ($serversMap as $key => $metrics) {
        echo "  key={$key} -> " . json_encode($metrics) . "\n";
    }
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
