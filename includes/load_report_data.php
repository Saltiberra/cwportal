<?php
// Function to load all report data from normalized tables
// Called from comissionamento.php when loading an existing report

function loadReportData($report_id, $pdo)
{
    $data = [];

    try {
        // Load PV Modules
        $modules = $pdo->prepare("
            SELECT m.*, b.name as brand_name, mo.model as model_name
            FROM report_modules m
            LEFT JOIN pv_module_brands b ON m.pv_module_brand_id = b.id
            LEFT JOIN pv_module_models mo ON m.pv_module_model_id = mo.id
            WHERE m.report_id = ?
            ORDER BY m.created_at DESC
        ");
        $modules->execute([$report_id]);
        $data['modules'] = $modules->fetchAll(PDO::FETCH_ASSOC);

        // Load Inverters with their equipment
        $inverters = $pdo->prepare("
            SELECT i.*, ib.name as brand_name, im.model as model_name
            FROM report_inverters i
            LEFT JOIN inverter_brands ib ON i.inverter_brand_id = ib.id
            LEFT JOIN inverter_models im ON i.inverter_model_id = im.id
            WHERE i.report_id = ?
            ORDER BY i.created_at DESC
        ");
        $inverters->execute([$report_id]);
        $data['inverters'] = $inverters->fetchAll(PDO::FETCH_ASSOC);
        // Build a mapping from inverter model_id to the numeric inverter_index (array index)
        $inverterIndexByModelId = [];
        if (!empty($data['inverters'])) {
            foreach ($data['inverters'] as $ii => $inv) {
                if (isset($inv['inverter_model_id']) && $inv['inverter_model_id'] !== '') {
                    $inverterIndexByModelId[(string)$inv['inverter_model_id']] = $ii;
                } elseif (isset($inv['model_id']) && $inv['model_id'] !== '') {
                    $inverterIndexByModelId[(string)$inv['model_id']] = $ii;
                }
            }
        }

        // Load Inverter Equipment (CB, Differential, Cables)
        $inv_equipment = $pdo->prepare("
            SELECT * FROM report_inverter_equipment
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $inv_equipment->execute([$report_id]);
        $data['inverter_equipment'] = $inv_equipment->fetchAll(PDO::FETCH_ASSOC);

        // Load Layouts
        $layouts = $pdo->prepare("
            SELECT * FROM report_system_layout
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $layouts->execute([$report_id]);
        $data['layouts'] = $layouts->fetchAll(PDO::FETCH_ASSOC);

        // Load Protection Data
        $protection = $pdo->prepare("
            SELECT * FROM report_protection
            WHERE report_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $protection->execute([$report_id]);
        $data['protection'] = $protection->fetch(PDO::FETCH_ASSOC);

        // Load Protection Cables
        $protection_cables = $pdo->prepare("
            SELECT * FROM report_protection_cables
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $protection_cables->execute([$report_id]);
        $data['protection_cables'] = $protection_cables->fetchAll(PDO::FETCH_ASSOC);

        // Load Clamp Measurements
        $clamp = $pdo->prepare("
            SELECT * FROM report_clamp_measurements
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $clamp->execute([$report_id]);
        $data['clamp_measurements'] = $clamp->fetchAll(PDO::FETCH_ASSOC);

        // Load Earth Protection
        $earth = $pdo->prepare("
            SELECT * FROM report_earth_protection
            WHERE report_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $earth->execute([$report_id]);
        $data['earth_protection'] = $earth->fetch(PDO::FETCH_ASSOC);

        // Load Homopolar Protection
        $homopolar = $pdo->prepare("
            SELECT * FROM report_homopolar_protection
            WHERE report_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $homopolar->execute([$report_id]);
        $data['homopolar_protection'] = $homopolar->fetch(PDO::FETCH_ASSOC);

        // Load String Measurements — merge report_equipment fallback with mppt_string_measurements
        $parsedMeasurements = [];

        // 1) Always load equipment-based (legacy) String Measurement rows first, so they act as fallback
        $strings = $pdo->prepare("SELECT * FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' ORDER BY id ASC");
        $strings->execute([$report_id]);
        $stringMeasurements = $strings->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stringMeasurements as $measurement) {
            $characteristics = $measurement['characteristics'];
            $parsed = [
                'id' => $measurement['id'],
                'inverter_id' => null,
                'mppt' => null,
                'string_num' => null,
                'voc' => null,
                'vmp' => null,
                'isc' => null,
                'imp' => null,
                'power_wp' => null,
                'temp' => null,
                'irr' => null,
                'rins' => null,
                'rlo' => null,
                'notes' => null
            ];
            if (!empty($characteristics)) {
                $parts = explode(' | ', $characteristics);
                foreach ($parts as $part) {
                    if (strpos($part, 'Inverter:') === 0) {
                        $parsed['inverter_id'] = trim(substr($part, 9));
                    } elseif (strpos($part, 'MPPT:') === 0) {
                        $parsed['mppt'] = (int) trim(substr($part, 5));
                    } elseif (strpos($part, 'String:') === 0) {
                        $parsed['string_num'] = (int) trim(substr($part, 7));
                    } elseif (strpos($part, 'Voc:') === 0) {
                        $parsed['voc'] = trim(preg_replace('/V$/i', '', substr($part, 4)));
                    } elseif (strpos($part, 'Vmp:') === 0) {
                        $parsed['vmp'] = trim(preg_replace('/V$/i', '', substr($part, 4)));
                    } elseif (strpos($part, 'Current:') === 0) {
                        $parsed['isc'] = trim(preg_replace('/A$/i', '', substr($part, 8)));
                    } elseif (strpos($part, 'Isc:') === 0) {
                        $parsed['isc'] = trim(preg_replace('/A$/i', '', substr($part, 4)));
                    } elseif (strpos($part, 'Imp:') === 0) {
                        $parsed['imp'] = trim(preg_replace('/A$/i', '', substr($part, 4)));
                    } elseif (strpos($part, 'Power:') === 0) {
                        $parsed['power_wp'] = trim(substr($part, 6));
                    } elseif (strpos($part, 'Temp:') === 0) {
                        $parsed['temp'] = trim(substr($part, 5));
                    } elseif (strpos($part, 'Temperature:') === 0) {
                        $parsed['temp'] = trim(substr($part, 12));
                    } elseif (strpos($part, 'Irr:') === 0) {
                        $parsed['irr'] = trim(substr($part, 4));
                    } elseif (strpos($part, 'Irrad:') === 0) {
                        $parsed['irr'] = trim(substr($part, 6));
                    } elseif (strpos($part, 'R.INS:') === 0) {
                        $parsed['rins'] = trim(substr($part, 6));
                    } elseif (strpos($part, 'R.LO:') === 0) {
                        $parsed['rlo'] = trim(substr($part, 5));
                    } elseif (strpos($part, 'Notes:') === 0) {
                        $parsed['notes'] = trim(substr($part, 6));
                    }
                }
            }
            // Map parsed inverter model ID to the array index (inverter_index) if possible
            if (!empty($parsed['inverter_id']) && isset($inverterIndexByModelId[(string)$parsed['inverter_id']])) {
                $parsed['inverter_index'] = $inverterIndexByModelId[(string)$parsed['inverter_id']];
            } else {
                $parsed['inverter_index'] = null;
            }
            $parsedMeasurements[] = $parsed;
        }

        // 2) Now load MPPT table rows and merge by inverter_index/mppt/string_num; mppt values override only when meaningfully non-empty
        $mpptStmt = $pdo->prepare("SELECT * FROM mppt_string_measurements WHERE report_id = ? ORDER BY inverter_index, mppt, string_num");
        $mpptStmt->execute([$report_id]);
        $mpptRows = $mpptStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($mpptRows)) {
            // Build an index for parsedMeasurements for quick lookup: key -> index in parsedMeasurements array
            $indexMap = [];
            foreach ($parsedMeasurements as $idx => $pm) {
                $ik = (isset($pm['inverter_index']) && $pm['inverter_index'] !== null) ? $pm['inverter_index'] : ($pm['inverter_index'] ?? '');
                $key = sprintf('%s_%s_%s', $ik, $pm['mppt'] ?? '', $pm['string_num'] ?? '');
                $indexMap[$key] = $idx;
            }
            foreach ($mpptRows as $r) {
                $invIdx = isset($r['inverter_index']) ? intval($r['inverter_index']) : '';
                $mppt = isset($r['mppt']) ? intval($r['mppt']) : '';
                $snum = isset($r['string_num']) ? intval($r['string_num']) : '';
                $key = sprintf('%s_%s_%s', $invIdx, $mppt, $snum);
                $newRow = [
                    'id' => $r['id'],
                    'inverter_index' => $invIdx,
                    'mppt' => $mppt,
                    'string_num' => $snum,
                    'voc' => isset($r['voc']) ? trim($r['voc']) : null,
                    'isc' => isset($r['isc']) ? trim($r['isc']) : null,
                    'vmp' => isset($r['vmp']) ? trim($r['vmp']) : null,
                    'imp' => isset($r['imp']) ? trim($r['imp']) : null,
                    'rins' => isset($r['rins']) ? trim($r['rins']) : null,
                    'irr' => isset($r['irr']) ? trim($r['irr']) : null,
                    'temp' => isset($r['temp']) ? trim($r['temp']) : null,
                    'rlo' => isset($r['rlo']) ? trim($r['rlo']) : null,
                    'notes' => isset($r['notes']) ? trim($r['notes']) : null
                ];
                // If found in equipment fallback, merge per-field
                if (isset($indexMap[$key])) {
                    $idx = $indexMap[$key];
                    // For each field, override only if mppt value is non-empty and not a numeric zero when existing is non-numeric
                    foreach (['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'notes'] as $field) {
                        $newVal = $newRow[$field] ?? null;
                        if ($newVal === null || trim((string)$newVal) === '') continue;
                        $existingVal = $parsedMeasurements[$idx][$field] ?? '';
                        $existingTrim = trim((string)$existingVal);
                        $newTrim = trim((string)$newVal);
                        $newNum = is_numeric(str_replace(',', '.', $newTrim));
                        $existNum = $existingTrim !== '' ? is_numeric(str_replace(',', '.', $existingTrim)) : false;
                        $newIsZeroNumeric = $newNum && floatval(str_replace(',', '.', $newTrim)) == 0.0;
                        if ($existingTrim !== '' && !$existNum && $newIsZeroNumeric) {
                            // skip override; keep textual equipment value
                            continue;
                        }
                        // override
                        $parsedMeasurements[$idx][$field] = $newTrim;
                    }
                } else {
                    // No existing equipment fallback, just append the mppt row
                    $parsedMeasurements[] = $newRow;
                }
            }
        } // end if !empty(mpptRows)
        // fallback logic handled above when MPPT table is empty — already implemented

        $data['string_measurements'] = $parsedMeasurements;

        // Load Telemetry Credentials
        $telemetry_creds = $pdo->prepare("
            SELECT * FROM report_telemetry_credentials
            WHERE report_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $telemetry_creds->execute([$report_id]);
        $data['telemetry_credentials'] = $telemetry_creds->fetch(PDO::FETCH_ASSOC);

        // Load Communications Devices
        $communications = $pdo->prepare("
            SELECT * FROM report_communications
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $communications->execute([$report_id]);
        $data['communications'] = $communications->fetchAll(PDO::FETCH_ASSOC);

        // Load Telemetry Meters
        $telemetry_meters = $pdo->prepare("
            SELECT * FROM report_telemetry_meters
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $telemetry_meters->execute([$report_id]);
        $data['telemetry_meters'] = $telemetry_meters->fetchAll(PDO::FETCH_ASSOC);

        // Load Energy Meters
        $energy_meters = $pdo->prepare("
            SELECT * FROM report_energy_meters
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $energy_meters->execute([$report_id]);
        $data['energy_meters'] = $energy_meters->fetchAll(PDO::FETCH_ASSOC);

        // Load Punch List
        $punch_list = $pdo->prepare("
            SELECT * FROM report_punch_list
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $punch_list->execute([$report_id]);
        $data['punch_list'] = $punch_list->fetchAll(PDO::FETCH_ASSOC);

        // Load Additional Notes
        $notes = $pdo->prepare("
            SELECT * FROM report_additional_notes
            WHERE report_id = ?
            ORDER BY created_at DESC
        ");
        $notes->execute([$report_id]);
        $data['additional_notes'] = $notes->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}
