<?php

/**
 * ajax/mppt_crud.php
 * CRUD operations para MPPT String Measurements
 * 
 * Operações:
 * - GET /mppt_crud.php?action=load&report_id=X     → Carrega todas as medições
 * - POST /mppt_crud.php?action=save                 → Salva uma medição
 * - POST /mppt_crud.php?action=autosave             → Autosave rápido
 * - DELETE /mppt_crud.php?action=delete&id=X        → Deleta uma medição
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);
session_start();

require_once '../config/database.php';

// Verificar login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'load':
            handleLoadMeasurements();
            break;

        case 'save':
            handleSaveMeasurement();
            break;

        case 'autosave':
            handleAutosaveMeasurement();
            break;

        case 'delete':
            handleDeleteMeasurement();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('[MPPT_CRUD] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Carrega todas as medições de um relatório
 */
function handleLoadMeasurements()
{
    global $pdo;

    $reportId = intval($_GET['report_id'] ?? 0);

    if (!$reportId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing report_id']);
        exit;
    }

    // Load inverters for this report to map inverter model_id -> inverter_index
    $invStmt = $pdo->prepare("SELECT * FROM report_inverters WHERE report_id = ? ORDER BY id");
    $invStmt->execute([$reportId]);
    $inverters = $invStmt->fetchAll(PDO::FETCH_ASSOC);
    $invIndexByModelId = [];
    foreach ($inverters as $ii => $inv) {
        if (isset($inv['inverter_model_id']) && $inv['inverter_model_id'] !== '') {
            $invIndexByModelId[(string)$inv['inverter_model_id']] = $ii;
        } elseif (isset($inv['model_id']) && $inv['model_id'] !== '') {
            $invIndexByModelId[(string)$inv['model_id']] = $ii;
        }
    }
    // Reverse map: by index -> model id
    $invModelIdByIndex = [];
    foreach ($inverters as $ii => $inv) {
        if (isset($inv['inverter_model_id']) && $inv['inverter_model_id'] !== '') $invModelIdByIndex[$ii] = (string)$inv['inverter_model_id'];
        else if (isset($inv['model_id']) && $inv['model_id'] !== '') $invModelIdByIndex[$ii] = (string)$inv['model_id'];
        else $invModelIdByIndex[$ii] = '';
    }

    // 1) Load equipment fallback (legacy storage)
    $fallback = [];
    $equipStmt = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement'");
    $equipStmt->execute([$reportId]);
    $equipRows = $equipStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($equipRows as $er) {
        $chars = $er['characteristics'] ?? '';
        $parts = array_map('trim', explode('|', $chars));
        $item = ['inverter_id' => '', 'mppt' => '', 'string_num' => '', 'voc' => '', 'isc' => '', 'vmp' => '', 'imp' => '', 'rins' => '', 'irr' => '', 'temp' => '', 'rlo' => '', 'notes' => '', 'current' => ''];
        foreach ($parts as $p) {
            if (stripos($p, 'Inverter:') === 0) $item['inverter_id'] = trim(substr($p, strlen('Inverter:')));
            elseif (stripos($p, 'MPPT:') === 0) $item['mppt'] = trim(substr($p, strlen('MPPT:')));
            elseif (stripos($p, 'String:') === 0) $item['string_num'] = trim(substr($p, strlen('String:')));
            elseif (stripos($p, 'Voc:') === 0) $item['voc'] = trim(preg_replace('/V$/i', '', substr($p, strlen('Voc:'))));
            elseif (stripos($p, 'Isc:') === 0 || stripos($p, 'Current:') === 0) $item['isc'] = trim(preg_replace('/A$/i', '', substr($p, strpos($p, ':') + 1)));
            elseif (stripos($p, 'Vmp:') === 0) $item['vmp'] = trim(preg_replace('/V$/i', '', substr($p, strlen('Vmp:'))));
            elseif (stripos($p, 'Imp:') === 0) $item['imp'] = trim(preg_replace('/A$/i', '', substr($p, strlen('Imp:'))));
            elseif (stripos($p, 'R.INS:') === 0) $item['rins'] = trim(substr($p, strlen('R.INS:')));
            elseif (stripos($p, 'Irr:') === 0 || stripos($p, 'Irrad:') === 0) $item['irr'] = trim(substr($p, strpos($p, ':') + 1));
            elseif (stripos($p, 'Temp:') === 0 || stripos($p, 'Temperature:') === 0) $item['temp'] = trim(substr($p, strpos($p, ':') + 1));
            elseif (stripos($p, 'R.LO:') === 0) $item['rlo'] = trim(substr($p, strlen('R.LO:')));
            elseif (stripos($p, 'Notes:') === 0) $item['notes'] = trim(substr($p, strlen('Notes:')));
        }
        // map model id -> inv index
        $invIndex = '';
        if (!empty($item['inverter_id']) && isset($invIndexByModelId[(string)$item['inverter_id']])) {
            $invIndex = intval($invIndexByModelId[(string)$item['inverter_id']]);
        }
        $key = sprintf('%s_%s_%s', $invIndex, $item['mppt'] ?? '', $item['string_num'] ?? '');
        $fallback[$key] = $item;
    }

    // 2) Load mppt table
    $stmt = $pdo->prepare("SELECT * FROM mppt_string_measurements WHERE report_id = ? ORDER BY inverter_index, mppt, string_num");
    $stmt->execute([$reportId]);
    $mpRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $merged = [];
    // initialize merged with fallback
    foreach ($fallback as $k => $v) {
        $merged[$k] = $v;
    }
    // merge mppt rows
    foreach ($mpRows as $r) {
        $idx = intval($r['inverter_index']);
        $mppt = intval($r['mppt']);
        $str = intval($r['string_num']);
        $key = sprintf('%s_%s_%s', $idx, $mppt, $str);
        $new = [
            'id' => $r['id'],
            'report_id' => $r['report_id'],
            'inverter_index' => $idx,
            'mppt' => $mppt,
            'string_num' => $str,
            'voc' => isset($r['voc']) ? trim($r['voc']) : null,
            'isc' => isset($r['isc']) ? trim($r['isc']) : null,
            'vmp' => isset($r['vmp']) ? trim($r['vmp']) : null,
            'imp' => isset($r['imp']) ? trim($r['imp']) : null,
            'rins' => isset($r['rins']) ? trim($r['rins']) : null,
            'irr' => isset($r['irr']) ? trim($r['irr']) : null,
            'temp' => isset($r['temp']) ? trim($r['temp']) : null,
            'rlo' => isset($r['rlo']) ? trim($r['rlo']) : null,
            'notes' => isset($r['notes']) ? trim($r['notes']) : null,
            'current' => isset($r['current']) ? trim($r['current']) : null
        ];
        if (!isset($merged[$key])) {
            $merged[$key] = $new;
        } else {
            // merge each field, prefer existing textual fallback if mppt stored value is numeric zero
            foreach (['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'notes', 'current'] as $f) {
                $newVal = $new[$f] ?? null;
                if ($newVal === null || trim((string)$newVal) === '') continue;
                $existingVal = $merged[$key][$f] ?? '';
                $existingTrim = trim((string)$existingVal);
                $newTrim = trim((string)$newVal);
                $newNum = is_numeric(str_replace(',', '.', $newTrim));
                $existNum = $existingTrim !== '' ? is_numeric(str_replace(',', '.', $existingTrim)) : false;
                $newIsZeroNumeric = $newNum && floatval(str_replace(',', '.', $newTrim)) == 0.0;
                if ($existingTrim !== '' && !$existNum && $newIsZeroNumeric) {
                    // keep fallback textual value
                    continue;
                }
                $merged[$key][$f] = $newTrim;
            }
        }
        // (No writebacks during load)
    }

    // convert merged to measurements array
    $measurements = [];
    foreach ($merged as $k => $m) {
        // ensure id/report_id/inverter_index present
        $m['id'] = isset($m['id']) ? (int)$m['id'] : null;
        $m['report_id'] = isset($m['report_id']) ? (int)$m['report_id'] : $reportId;
        $m['inverter_index'] = isset($m['inverter_index']) ? (int)$m['inverter_index'] : 0;
        $m['mppt'] = isset($m['mppt']) ? (int)$m['mppt'] : 0;
        $m['string_num'] = isset($m['string_num']) ? (int)$m['string_num'] : 0;
        $measurements[] = $m;
    }

    // Preserve raw strings for measurement fields (do not cast to numeric)
    foreach ($measurements as &$m) {
        $m['id'] = (int)$m['id'];
        $m['report_id'] = (int)$m['report_id'];
        $m['inverter_index'] = (int)$m['inverter_index'];
        $m['mppt'] = (int)$m['mppt'];
        $m['string_num'] = (int)$m['string_num'];
        $m['voc'] = $m['voc'] !== null ? (string)$m['voc'] : null;
        $m['isc'] = $m['isc'] !== null ? (string)$m['isc'] : null;
        $m['vmp'] = $m['vmp'] !== null ? (string)$m['vmp'] : null;
        $m['imp'] = $m['imp'] !== null ? (string)$m['imp'] : null;
        $m['rins'] = $m['rins'] !== null ? (string)$m['rins'] : null;
        $m['irr'] = $m['irr'] !== null ? (string)$m['irr'] : null;
        $m['temp'] = $m['temp'] !== null ? (string)$m['temp'] : null;
        $m['rlo'] = $m['rlo'] !== null ? (string)$m['rlo'] : null;
        $m['current'] = $m['current'] !== null ? (string)$m['current'] : null;
    }

    echo json_encode([
        'success' => true,
        'count' => count($measurements),
        'measurements' => $measurements
    ]);
}

/**
 * Get inverter model id by inverter index for a report
 */
function getInverterModelIds($pdo, $reportId)
{
    $invStmt = $pdo->prepare("SELECT inverter_model_id, model_id FROM report_inverters WHERE report_id = ? ORDER BY id");
    $invStmt->execute([$reportId]);
    $inverters = $invStmt->fetchAll(PDO::FETCH_ASSOC);
    $invModelIdByIndex = [];
    foreach ($inverters as $i => $inv) {
        if (isset($inv['inverter_model_id']) && $inv['inverter_model_id'] !== '') $invModelIdByIndex[$i] = (string)$inv['inverter_model_id'];
        elseif (isset($inv['model_id']) && $inv['model_id'] !== '') $invModelIdByIndex[$i] = (string)$inv['model_id'];
        else $invModelIdByIndex[$i] = '';
    }
    return $invModelIdByIndex;
}

/**
 * Salva ou atualiza uma medição individual
 */
function handleSaveMeasurement()
{
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['report_id', 'inverter_index', 'mppt', 'string_num'];

    // Preserve original string formatting in incoming values (do not normalize/comma-to-dot)
    foreach (['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'current'] as $numField) {
        if (isset($data[$numField]) && is_string($data[$numField])) {
            // Keep value as string as-is
        }
    }
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
            exit;
        }
    }

    $reportId = intval($data['report_id']);
    $inverterId = intval($data['inverter_index']);
    $mppt = intval($data['mppt']);
    $stringNum = intval($data['string_num']);

    // Preparar valores -- keep them as raw strings (null if empty)
    $values = [
        'voc' => (isset($data['voc']) && $data['voc'] !== '') ? $data['voc'] : null,
        'isc' => (isset($data['isc']) && $data['isc'] !== '') ? $data['isc'] : null,
        'vmp' => (isset($data['vmp']) && $data['vmp'] !== '') ? $data['vmp'] : null,
        'imp' => (isset($data['imp']) && $data['imp'] !== '') ? $data['imp'] : null,
        'rins' => (isset($data['rins']) && $data['rins'] !== '') ? $data['rins'] : null,
        'irr' => (isset($data['irr']) && $data['irr'] !== '') ? $data['irr'] : null,
        'temp' => (isset($data['temp']) && $data['temp'] !== '') ? $data['temp'] : null,
        'rlo' => (isset($data['rlo']) && $data['rlo'] !== '') ? $data['rlo'] : null,
        'notes' => $data['notes'] ?? null,
        'current' => (isset($data['current']) && $data['current'] !== '') ? $data['current'] : null
    ];

    // UPSERT: Tenta INSERT, se falhar faz UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO mppt_string_measurements 
        (report_id, inverter_index, mppt, string_num, voc, isc, vmp, imp, rins, irr, temp, rlo, notes, current)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        voc = VALUES(voc),
        isc = VALUES(isc),
        vmp = VALUES(vmp),
        imp = VALUES(imp),
        rins = VALUES(rins),
        irr = VALUES(irr),
        temp = VALUES(temp),
        rlo = VALUES(rlo),
        notes = VALUES(notes),
        current = VALUES(current)
    ");

    $stmt->execute([
        $reportId,
        $inverterId,
        $mppt,
        $stringNum,
        $values['voc'],
        $values['isc'],
        $values['vmp'],
        $values['imp'],
        $values['rins'],
        $values['irr'],
        $values['temp'],
        $values['rlo'],
        $values['notes'],
        $values['current']
    ]);

    error_log("[MPPT_CRUD] ✅ Saved: report_id=$reportId, inverter=$inverterId, mppt=$mppt, string=$stringNum");

    echo json_encode([
        'success' => true,
        'message' => 'Measurement saved',
        'data' => $values
    ]);
}

// Also upsert a `report_equipment` String Measurement fallback to preserve textual values
try {
    $invModelMap = getInverterModelIds($pdo, $reportId);
    $invModelId = isset($invModelMap[$inverterId]) ? $invModelMap[$inverterId] : '';
    $parts = [];
    if ($invModelId !== '') $parts[] = 'Inverter: ' . $invModelId;
    $parts[] = 'MPPT: ' . $mppt;
    $parts[] = 'String: ' . $stringNum;
    if (!empty($values['voc'])) $parts[] = 'Voc: ' . $values['voc'] . 'V';
    if (!empty($values['isc'])) $parts[] = 'Isc: ' . $values['isc'] . 'A';
    if (!empty($values['vmp'])) $parts[] = 'Vmp: ' . $values['vmp'] . 'V';
    if (!empty($values['imp'])) $parts[] = 'Imp: ' . $values['imp'] . 'A';
    if (!empty($values['rins'])) $parts[] = 'R.INS: ' . $values['rins'];
    if (!empty($values['irr'])) $parts[] = 'Irr: ' . $values['irr'];
    if (!empty($values['temp'])) $parts[] = 'Temp: ' . $values['temp'];
    if (!empty($values['rlo'])) $parts[] = 'R.LO: ' . $values['rlo'];
    if (!empty($values['notes'])) $parts[] = 'Notes: ' . $values['notes'];
    $characteristics = implode(' | ', $parts);

    $stmtFind = $pdo->prepare("SELECT id FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' AND characteristics LIKE ? LIMIT 1");
    $likePattern = "%MPPT: {$mppt}%String: {$stringNum}%";
    $stmtFind->execute([$reportId, $likePattern]);
    $foundId = $stmtFind->fetchColumn();
    if ($foundId) {
        $stmtUpd = $pdo->prepare("UPDATE report_equipment SET characteristics = ? WHERE id = ?");
        $stmtUpd->execute([$characteristics, $foundId]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'String Measurement', NULL, NULL, NULL, 1, ?)");
        $stmtIns->execute([$reportId, $characteristics]);
    }
} catch (Exception $e) {
    error_log('[MPPT_CRUD] Could not upsert report_equipment fallback (save): ' . $e->getMessage());
}

/**
 * Autosave rápido (só atualiza um campo)
 */
function handleAutosaveMeasurement()
{
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['report_id', 'inverter_index', 'mppt', 'string_num', 'field', 'value'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
            exit;
        }
    }

    $reportId = intval($data['report_id']);
    $inverterId = intval($data['inverter_index']);
    $mppt = intval($data['mppt']);
    $stringNum = intval($data['string_num']);
    $field = $data['field'];
    $value = $data['value'];

    // Validar que field é um campo permitido
    $allowedFields = ['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'notes', 'current'];
    if (!in_array($field, $allowedFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid field']);
        exit;
    }

    // Preserve the field value as string (do not normalize/convert)
    if ($field !== 'notes') {
        $value = ($value === '') ? null : (string)$value;
    }

    // UPSERT
    $stmt = $pdo->prepare("
        INSERT INTO mppt_string_measurements 
        (report_id, inverter_index, mppt, string_num, $field)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        $field = VALUES($field)
    ");

    $stmt->execute([$reportId, $inverterId, $mppt, $stringNum, $value]);

    error_log("[MPPT_CRUD] ✅ Autosaved: $field=$value for measurement report_id=$reportId, inv=$inverterId, mppt=$mppt, str=$stringNum");

    echo json_encode([
        'success' => true,
        'message' => "Field $field updated",
        'field' => $field,
        'value' => $value
    ]);

    // Also update the equipment fallback characteristics for this single metric
    try {
        $invModelMap = getInverterModelIds($pdo, $reportId);
        $invModelId = isset($invModelMap[$inverterId]) ? $invModelMap[$inverterId] : '';
        // Try to find existing equipment by MPPT and String
        $stmtFind = $pdo->prepare("SELECT id, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' AND characteristics LIKE ? LIMIT 1");
        $likePattern = "%MPPT: {$mppt}%String: {$stringNum}%";
        $stmtFind->execute([$reportId, $likePattern]);
        $found = $stmtFind->fetch(PDO::FETCH_ASSOC);
        $existingChars = $found['characteristics'] ?? '';
        $newChars = [];
        if ($found) {
            // Parse and update existing parts
            $parts = array_map('trim', explode('|', $existingChars));
            $map = [];
            foreach ($parts as $p) {
                $kv = explode(':', $p, 2);
                if (count($kv) === 2) $map[trim($kv[0])] = trim($kv[1]);
            }
            // set parts
            if ($invModelId !== '') $map['Inverter'] = $invModelId;
            $map['MPPT'] = $mppt;
            $map['String'] = $stringNum;
            if ($field === 'voc') $map['Voc'] = ($value !== null ? $value . 'V' : '');
            if ($field === 'isc' || $field === 'current') $map['Isc'] = ($value !== null ? $value . 'A' : '');
            if ($field === 'vmp') $map['Vmp'] = ($value !== null ? $value . 'V' : '');
            if ($field === 'imp') $map['Imp'] = ($value !== null ? $value . 'A' : '');
            if ($field === 'rins') $map['R.INS'] = $value;
            if ($field === 'irr') $map['Irr'] = $value;
            if ($field === 'temp') $map['Temp'] = $value;
            if ($field === 'rlo') $map['R.LO'] = $value;
            if ($field === 'notes') $map['Notes'] = $value;
            // Rebuild characteristics
            $partsOut = [];
            foreach ($map as $k => $v) {
                if ($v !== null && $v !== '') $partsOut[] = $k . ': ' . $v;
            }
            $charsOut = implode(' | ', $partsOut);
            $stmtUpd = $pdo->prepare("UPDATE report_equipment SET characteristics = ? WHERE id = ?");
            $stmtUpd->execute([$charsOut, $found['id']]);
        } else {
            // Build new chars
            $parts = [];
            if ($invModelId !== '') $parts[] = 'Inverter: ' . $invModelId;
            $parts[] = 'MPPT: ' . $mppt;
            $parts[] = 'String: ' . $stringNum;
            if ($field === 'voc') $parts[] = 'Voc: ' . $value . 'V';
            if ($field === 'isc' || $field === 'current') $parts[] = 'Isc: ' . $value . 'A';
            if ($field === 'vmp') $parts[] = 'Vmp: ' . $value . 'V';
            if ($field === 'imp') $parts[] = 'Imp: ' . $value . 'A';
            if ($field === 'rins') $parts[] = 'R.INS: ' . $value;
            if ($field === 'irr') $parts[] = 'Irr: ' . $value;
            if ($field === 'temp') $parts[] = 'Temp: ' . $value;
            if ($field === 'rlo') $parts[] = 'R.LO: ' . $value;
            if ($field === 'notes') $parts[] = 'Notes: ' . $value;
            $charsOut = implode(' | ', $parts);
            $stmtIns = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'String Measurement', NULL, NULL, NULL, 1, ?)");
            $stmtIns->execute([$reportId, $charsOut]);
        }
    } catch (Exception $e) {
        error_log('[MPPT_CRUD] Could not upsert report_equipment fallback (autosave): ' . $e->getMessage());
    }
}

/**
 * Deleta uma medição
 */
function handleDeleteMeasurement()
{
    global $pdo;

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing measurement id']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM mppt_string_measurements WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Measurement deleted'
    ]);
}
