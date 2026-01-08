
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Save Report Handler
 * 
 * This script processes the commissioning form submission,
 * saves data to the database, and redirects to report generation
 */

// Output buffering to ensure headers can be sent
ob_start();

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/audit.php';

// Helper: execute with logging to assist debugging SQL errors
function safeExecute($stmt, $params = [])
{
    try {
        // DEBUG: Mostrar os parâmetros que vão ser usados no INSERT, antes de qualquer validação
        $paramsArray = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'project_name' => $projectName,
            'date' => $date,
            'responsible' => $responsible,
            'plant_location' => $plantLocation,
            'gps' => $gps,
            'map_area_m2' => $mapAreaM2,
            'map_azimuth_deg' => $mapAzimuthDeg,
            'map_polygon_coords' => $mapPolygonCoords,
            'epc_id' => $epcId,
            'representative_id' => $representativeId,
            'commissioning_responsible_id' => $commissioningResponsibleId,
            'technician' => $technician,
            'installed_power' => $installedPower,
            'total_power' => $totalPower,
            'certified_power' => $certifiedPower,
            'cpe' => $cpe
        ];
        error_log("[SAVE_REPORT] EXECUTE - SQL: " . $stmt->queryString . " Params: " . json_encode($params));
    } catch (Exception $e) {
        // ignore if queryString not available
    }
    try {
        return $stmt->execute($params);
    } catch (Exception $e) {
        try {
            error_log("[SAVE_REPORT] SQL ERROR: " . $stmt->queryString . " Params: " . json_encode($params) . " - " . $e->getMessage());
            echo "SQL ERROR: " . $stmt->queryString . " Params: " . json_encode($params) . " - " . $e->getMessage();
        } catch (Exception $ee) {
        }
        throw $e;
    }
}

// ðŸ”’ Get session ID and user ID for concurrency control
$sessionId = session_id();
$userId = $_SESSION['user_id'] ?? null;  // For future login system

try {
    error_log("[SAVE_REPORT] START - session_id={$sessionId} user_id={$userId}");
    error_log("[SAVE_REPORT] POST data: " . json_encode(array_keys($_POST)));
    // Get POST data
    $reportId = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $projectName = isset($_POST['project_name']) ? trim($_POST['project_name']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $responsible = isset($_POST['responsible']) ? trim($_POST['responsible']) : '';
    $plantLocation = isset($_POST['plant_location']) ? trim($_POST['plant_location']) : '';
    $gps = isset($_POST['gps']) ? trim($_POST['gps']) : '';
    // Map fields
    $mapAreaM2 = isset($_POST['map_area_m2']) && $_POST['map_area_m2'] !== '' ? (float)$_POST['map_area_m2'] : null;
    $mapAzimuthDeg = isset($_POST['map_azimuth_deg']) && $_POST['map_azimuth_deg'] !== '' ? (float)$_POST['map_azimuth_deg'] : null;
    $mapPolygonCoords = isset($_POST['map_polygon_coords']) && $_POST['map_polygon_coords'] !== '' ? trim($_POST['map_polygon_coords']) : null;
    $epcId = isset($_POST['epc_id']) ? intval($_POST['epc_id']) : null;
    $representativeId = isset($_POST['representative_id']) ? intval($_POST['representative_id']) : null;
    $commissioningResponsibleId = isset($_POST['commissioning_responsible_id']) ? intval($_POST['commissioning_responsible_id']) : null;

    // Convert 0 to null (empty selection)
    if ($epcId === 0) $epcId = null;
    if ($representativeId === 0) $representativeId = null;
    if ($commissioningResponsibleId === 0) $commissioningResponsibleId = null;

    $technician = isset($_POST['technician']) ? trim($_POST['technician']) : '';
    $installedPower = isset($_POST['installed_power']) ? floatval($_POST['installed_power']) : 0;
    $totalPower = isset($_POST['total_power']) ? floatval($_POST['total_power']) : 0;
    $certifiedPower = isset($_POST['certified_power']) ? floatval($_POST['certified_power']) : 0;
    $cpe = isset($_POST['cpe']) ? trim($_POST['cpe']) : '';

    // Validate foreign keys - ensure they exist in database
    if ($epcId) {
        $stmt = $pdo->prepare("SELECT id FROM epcs WHERE id = ?");
        safeExecute($stmt, [$epcId]);
        if (!$stmt->fetch()) {
            $epcId = null;
        }
    }
    if ($representativeId) {
        $stmt = $pdo->prepare("SELECT id FROM representatives WHERE id = ?");
        safeExecute($stmt, [$representativeId]);
        if (!$stmt->fetch()) {
            $representativeId = null;
        }
    }
    if ($commissioningResponsibleId) {
        $stmt = $pdo->prepare("SELECT id FROM commissioning_responsibles WHERE id = ?");
        safeExecute($stmt, [$commissioningResponsibleId]);
        if (!$stmt->fetch()) {
            $commissioningResponsibleId = null;
        }
    }

    // Begin transaction
    error_log("[SAVE_REPORT] About to beginTransaction");
    $pdo->beginTransaction();
    error_log("[SAVE_REPORT] Transaction started, reportId={$reportId}");

    if ($reportId) {
        // ðŸ”’ SECURITY: Validate that this report belongs to this session/user before updating
        $stmt = $pdo->prepare("SELECT id, session_id, user_id FROM commissioning_reports WHERE id = ?");
        safeExecute($stmt, [$reportId]);
        $existingReport = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingReport) {
            throw new Exception("Report not found");
        }

        // NOTE: We update the session_id to current session to allow editing from different tabs/sessions
        // This is acceptable since we don't have a multi-user permission system yet
        // In production with proper login, we would check if user_id matches instead

        // Update existing report (explicitly set updated_at to ensure timestamp updates)
        $stmt = $pdo->prepare("
            UPDATE commissioning_reports SET
                project_name = ?,
                date = ?,
                responsible = ?,
                plant_location = ?,
                gps = ?,
                map_area_m2 = ?,
                map_azimuth_deg = ?,
                map_polygon_coords = ?,
                epc_id = ?,
                representative_id = ?,
                commissioning_responsible_id = ?,
                technician = ?,
                installed_power = ?,
                total_power = ?,
                certified_power = ?,
                cpe = ?,
                session_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        safeExecute($stmt, [
            $projectName,
            $date,
            $responsible,
            $plantLocation,
            $gps,
            $mapAreaM2,
            $mapAzimuthDeg,
            $mapPolygonCoords,
            $epcId,
            $representativeId,
            $commissioningResponsibleId,
            $technician,
            $installedPower,
            $totalPower,
            $certifiedPower,
            $cpe,
            $sessionId,
            $reportId
        ]);

        // ðŸ“ Log report edit
        logAction('report_edited', 'reports', $reportId, 'Report edited: ' . $projectName, $projectName);
    } else {


        $paramsArray = [
            'id' => null,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'project_name' => $projectName,
            'date' => $date,
            'responsible' => $responsible,
            'plant_location' => $plantLocation,
            'gps' => $gps,
            'map_area_m2' => $mapAreaM2,
            'map_azimuth_deg' => $mapAzimuthDeg,
            'map_polygon_coords' => $mapPolygonCoords,
            'epc_id' => $epcId,
            'representative_id' => $representativeId,
            'commissioning_responsible_id' => $commissioningResponsibleId,
            'technician' => $technician,
            'installed_power' => $installedPower,
            'total_power' => $totalPower,
            'certified_power' => $certifiedPower,
            'cpe' => $cpe
        ];

        $stmt = $pdo->prepare("
            INSERT INTO commissioning_reports (
                id, session_id, user_id, project_name, date, responsible, plant_location, gps,
                map_area_m2, map_azimuth_deg, map_polygon_coords,
                epc_id, representative_id, commissioning_responsible_id, technician, installed_power,
                total_power, certified_power, cpe
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        safeExecute($stmt, array_values($paramsArray));

        // Get new report ID
        $reportId = $pdo->lastInsertId();

        // ðŸ“ Log report creation
        logAction('report_created', 'reports', $reportId, 'Report created: ' . $projectName, $projectName);
    }

    // Save Homopolar protection info
    // First, delete existing homopolar protection for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Homopolar Protection'");
        safeExecute($stmt, [$reportId]);
    }

    $homInst = isset($_POST['homopolar_installer']) ? trim($_POST['homopolar_installer']) : '';
    $homBrand = isset($_POST['homopolar_brand']) ? trim($_POST['homopolar_brand']) : '';
    $homModel = isset($_POST['homopolar_model']) ? trim($_POST['homopolar_model']) : '';
    if ($homInst !== '' || $homBrand !== '' || $homModel !== '') {
        $characteristics = "Installer: {$homInst} | Brand: {$homBrand} | Model: {$homModel}";
        $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Homopolar Protection', NULL, NULL, NULL, 1, ?)");
        safeExecute($stmt, [$reportId, $characteristics]);
    }

    // Save Protection data (Circuit Breaker) from protection table
    // First, delete existing protection circuit breaker for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Protection - Circuit Breaker'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new protection items from protection_data JSON
    if (!empty($_POST['protection_data'])) {
        $protectionData = json_decode($_POST['protection_data'], true);
        if (is_array($protectionData)) {
            foreach ($protectionData as $item) {
                $scope = isset($item['scope_text']) ? $item['scope_text'] : '';
                $brand = isset($item['brand_name']) ? $item['brand_name'] : '';
                $model = isset($item['model_name']) ? $item['model_name'] : '';
                $rated = isset($item['rated_current']) ? $item['rated_current'] : '';

                // Build characteristics string
                $characteristics = '';
                $parts = [];
                if (!empty($scope)) $parts[] = "Scope: {$scope}";
                if (!empty($brand)) $parts[] = $brand;
                if (!empty($model)) $parts[] = $model;
                if (!empty($rated)) $parts[] = "Rated Current: {$rated}";
                $characteristics = implode(' | ', $parts);

                // Insert protection circuit breaker (now also storing rated_current in its own column)
                $ratedVal = null;
                if ($rated !== '' && $rated !== null) {
                    $ratedVal = is_numeric($rated) ? (float)$rated : (float)str_replace(',', '.', (string)$rated);
                }
                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, rated_current, characteristics) VALUES (?, 'Protection - Circuit Breaker', NULL, ?, ?, 1, ?, ?)");
                safeExecute($stmt, [$reportId, $brand, $model, $ratedVal, $characteristics]);
            }
        }
    }

    // Save Protection Cable list
    // First, delete existing protection cables for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Protection - Cable'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new cables (if any)
    if (!empty($_POST['protection_cable_data'])) {
        $cableData = json_decode($_POST['protection_cable_data'], true);
        if (is_array($cableData)) {
            foreach ($cableData as $item) {
                $scope = isset($item['scope_text']) ? $item['scope_text'] : '';
                $brand = isset($item['brand_name']) ? $item['brand_name'] : '';
                $model = isset($item['model_name']) ? $item['model_name'] : '';
                $size = isset($item['size']) ? $item['size'] : '';
                $ins = isset($item['insulation']) ? $item['insulation'] : '';

                // Normalize and remove trailing unit variants (mm, mm², mm2) to avoid duplication
                $size = preg_replace('/\s*(mm(Â?\s*²)?|mm²|mm2)\s*$/i', '', $size);

                $characteristics = '';
                if (!empty($scope)) $characteristics .= "Scope: {$scope} | ";
                if (!empty($size)) {
                    $trimSize = trim($size);
                    // If size is purely numeric, append standardized unit; otherwise preserve the textual value as provided
                    if (preg_match('/^[0-9]+(?:[\.,][0-9]+)?$/', $trimSize)) {
                        $characteristics .= "Size: {$trimSize}mmÂ² | ";
                    } else {
                        $characteristics .= "Size: {$trimSize} | ";
                    }
                }
                if (!empty($ins)) $characteristics .= "Insulation: {$ins} | ";
                $characteristics = rtrim($characteristics, ' | ');
                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Protection - Cable', NULL, ?, ?, 1, ?)");
                safeExecute($stmt, [$reportId, $brand, $model, $characteristics]);
            }
        }
    }

    // Process equipment data (PV Modules)
    // First delete any existing PV Modules for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'PV Module'");
        safeExecute($stmt, [$reportId]);
    }

    // Process new modules data from JSON
    if (!empty($_POST['modules_data'])) {
        $modulesData = json_decode($_POST['modules_data'], true);

        if (is_array($modulesData) && !empty($modulesData)) {
            foreach ($modulesData as $module) {
                $brandName = isset($module['brand_name']) ? $module['brand_name'] : '';
                $modelName = isset($module['model_name']) ? $module['model_name'] : '';
                $modelId = isset($module['model_id']) ? intval($module['model_id']) : null;
                $quantity = isset($module['quantity']) ? intval($module['quantity']) : 0;
                $status = isset($module['status']) ? $module['status'] : 'new';
                $powerRating = isset($module['power_rating']) ? floatval($module['power_rating']) : 0;

                // Build characteristics string
                $characteristics = '';
                if ($powerRating > 0) {
                    $characteristics = "Power: {$powerRating}W";
                }

                // Insert module into report_equipment
                $stmt = $pdo->prepare("
                    INSERT INTO report_equipment (
                        report_id, equipment_type, deployment_status, brand, model, model_id,
                        quantity, power_rating, characteristics
                    ) VALUES (?, 'PV Module', ?, ?, ?, ?, ?, ?, ?)
                ");

                safeExecute($stmt, [
                    $reportId,
                    $status,
                    $brandName,
                    $modelName,
                    $modelId,
                    $quantity,
                    $powerRating,
                    $characteristics
                ]);
            }
        }
    }

    // Process Inverters data
    // First, delete existing inverters for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Inverter'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new inverters (if any)
    if (!empty($_POST['inverters_data'])) {
        $invertersData = json_decode($_POST['inverters_data'], true);

        // DEBUG: Log received inverter data
        if (is_array($invertersData) && !empty($invertersData)) {
            foreach ($invertersData as $index => $inverter) {
                $brandName = isset($inverter['brand_name']) ? $inverter['brand_name'] : '';
                $modelName = isset($inverter['model_name']) ? $inverter['model_name'] : '';
                $modelId = isset($inverter['model_id']) ? intval($inverter['model_id']) : null;
                $quantity = isset($inverter['quantity']) ? intval($inverter['quantity']) : 1;
                $status = isset($inverter['status']) ? $inverter['status'] : 'new';
                $serialNumber = isset($inverter['serial_number']) ? $inverter['serial_number'] : '';
                $location = isset($inverter['location']) ? $inverter['location'] : '';
                $maxOutputCurrent = isset($inverter['max_output_current']) ? $inverter['max_output_current'] : '';

                // DEBUG: Log model_id

                // Build characteristics string
                $characteristics = '';

                if (!empty($serialNumber)) {
                    $characteristics .= "Serial: {$serialNumber} | ";
                }

                if (!empty($location)) {
                    $characteristics .= "Location: {$location} | ";
                }

                if (!empty($maxOutputCurrent)) {
                    $characteristics .= "Max Output Current: {$maxOutputCurrent} | ";
                }

                // Include circuit breaker fields if present
                if (!empty($inverter['circuit_breaker_brand_name']) || !empty($inverter['circuit_breaker_model_name']) || !empty($inverter['circuit_breaker_rated_current'])) {
                    $cbParts = [];
                    if (!empty($inverter['circuit_breaker_brand_name'])) $cbParts[] = $inverter['circuit_breaker_brand_name'];
                    if (!empty($inverter['circuit_breaker_model_name'])) $cbParts[] = $inverter['circuit_breaker_model_name'];
                    if (!empty($inverter['circuit_breaker_rated_current'])) $cbParts[] = "Rated: {$inverter['circuit_breaker_rated_current']}A";
                    $characteristics .= "Circuit Breaker: " . implode(' ', $cbParts) . " | ";
                }

                // Include differential fields if present
                if (!empty($inverter['differential_brand_name']) || !empty($inverter['differential_model_name']) || !empty($inverter['differential_rated_current']) || !empty($inverter['differential_current'])) {
                    $diffParts = [];
                    if (!empty($inverter['differential_brand_name'])) $diffParts[] = $inverter['differential_brand_name'];
                    if (!empty($inverter['differential_model_name'])) $diffParts[] = $inverter['differential_model_name'];
                    if (!empty($inverter['differential_rated_current'])) $diffParts[] = "Rated: {$inverter['differential_rated_current']}A";
                    if (!empty($inverter['differential_current'])) $diffParts[] = "{$inverter['differential_current']}mA";
                    $characteristics .= "Differential: " . implode(' ', $diffParts) . " | ";
                }

                // Include cable fields if present
                if (!empty($inverter['cable_brand_name']) || !empty($inverter['cable_size']) || !empty($inverter['cable_insulation'])) {
                    $cableParts = [];
                    if (!empty($inverter['cable_brand_name'])) $cableParts[] = $inverter['cable_brand_name'];
                    if (!empty($inverter['cable_model_name'])) $cableParts[] = $inverter['cable_model_name'];
                    if (!empty($inverter['cable_size'])) $cableParts[] = $inverter['cable_size'] . 'mmÂ²';
                    if (!empty($inverter['cable_insulation'])) $cableParts[] = $inverter['cable_insulation'];
                    $characteristics .= "Cable: " . implode(' ', $cableParts) . " | ";
                }

                // Include validation message (Safety Alert) if present
                if (!empty($inverter['validation_message'])) {
                    $characteristics .= "Validation: " . trim($inverter['validation_message']) . " | ";
                }

                // Add datasheet URL if provided
                if (!empty($inverter['datasheet_url'])) {
                    $characteristics .= "Datasheet: {$inverter['datasheet_url']} | ";
                }

                // Clean up trailing separator
                $characteristics = rtrim($characteristics, ' | ');

                // DEBUG: Log final characteristics
                // Save inverter equipment
                $stmt = $pdo->prepare("
                    INSERT INTO report_equipment (
                        report_id, equipment_type, deployment_status, brand, model, model_id,
                        quantity, characteristics, location
                    ) VALUES (?, 'Inverter', ?, ?, ?, ?, ?, ?, ?)
                ");

                safeExecute($stmt, [
                    $reportId,
                    $status,
                    $brandName,
                    $modelName,
                    $modelId,
                    $quantity,
                    $characteristics,
                    $location
                ]);
            }
        }
    }

    // Process layout data
    if (isset($_POST['roof_id']) && is_array($_POST['roof_id'])) {
        // Delete existing layouts from BOTH old and new tables
        $stmt = $pdo->prepare("DELETE FROM report_layout WHERE report_id = ?");
        safeExecute($stmt, [$reportId]);

        $stmt = $pdo->prepare("DELETE FROM report_system_layout WHERE report_id = ?");
        safeExecute($stmt, [$reportId]);

        // Insert new layouts
        foreach ($_POST['roof_id'] as $index => $roofId) {
            if (empty($roofId)) continue;

            $quantity = isset($_POST['layout_quantity'][$index]) ? intval($_POST['layout_quantity'][$index]) : 0;
            $azimuth = isset($_POST['azimuth'][$index]) ? floatval($_POST['azimuth'][$index]) : null;
            $tilt = isset($_POST['tilt'][$index]) ? floatval($_POST['tilt'][$index]) : null;
            $mounting = isset($_POST['mounting'][$index]) ? $_POST['mounting'][$index] : 'roof';

            // Save layout item to NEW table (report_system_layout)
            $stmt = $pdo->prepare("
                INSERT INTO report_system_layout (
                    report_id, roof_id, quantity, azimuth, tilt, mounting, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            safeExecute($stmt, [
                $reportId,
                $roofId,
                $quantity,
                $azimuth,
                $tilt,
                $mounting,
                $index
            ]);
        }
    } elseif (!empty($_POST['layouts_data'])) {
        // FALLBACK: If roof_id[] is empty, try processing layouts_data JSON (from autosave)

        // Delete existing layouts
        $stmt = $pdo->prepare("DELETE FROM report_layout WHERE report_id = ?");
        safeExecute($stmt, [$reportId]);

        $stmt = $pdo->prepare("DELETE FROM report_system_layout WHERE report_id = ?");
        safeExecute($stmt, [$reportId]);

        // Parse JSON and insert
        $layoutsData = json_decode($_POST['layouts_data'], true);
        if (is_array($layoutsData)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO report_system_layout (
                    report_id, roof_id, quantity, azimuth, tilt, mounting, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($layoutsData as $index => $layout) {
                safeExecute($insertStmt, [
                    $reportId,
                    $layout['roof_id'] ?? '',
                    intval($layout['quantity'] ?? 1),
                    floatval($layout['azimuth'] ?? 0),
                    floatval($layout['tilt'] ?? 0),
                    $layout['mounting'] ?? 'roof',
                    $index
                ]);
            }
        }
    }
    // Process Telemetry data - delete all telemetry for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type IN ('Telemetry - Credential','Telemetry - Meter')");
        safeExecute($stmt, [$reportId]);
    }

    // Telemetry Credential(s)
    // Then insert new credentials (if any)
    if (!empty($_POST['telemetry_credential_data'])) {
        $credRaw = json_decode($_POST['telemetry_credential_data'], true);
        // Normalize to array of credentials
        $creds = [];
        if (is_array($credRaw)) {
            // If associative (single object), wrap it
            $creds = array_keys($credRaw) !== range(0, count($credRaw) - 1) ? [$credRaw] : $credRaw;
        }

        foreach ($creds as $cred) {
            if (!is_array($cred)) continue;
            $invIdx = isset($cred['inverter_index']) ? $cred['inverter_index'] : '';
            // Prefer human-friendly inverter text if provided
            $invText = isset($cred['inverter_text']) ? $cred['inverter_text'] : $invIdx;
            $username = isset($cred['username']) ? $cred['username'] : '';
            $password = isset($cred['password']) ? $cred['password'] : '';
            $ip = isset($cred['ip']) ? $cred['ip'] : '';
            $characteristics = "Inverter Ref: {$invText} | Username: {$username} | Password: {$password} | IP: {$ip}";
            $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Telemetry - Credential', NULL, NULL, NULL, 1, ?)");
            safeExecute($stmt, [$reportId, $characteristics]);
        }
    }

    // Telemetry Meter - supports array of meters
    if (!empty($_POST['telemetry_meter_data'])) {
        $raw = json_decode($_POST['telemetry_meter_data'], true);
        if (is_array($raw)) {
            // If it's a single associative meter, wrap as array
            $meters = array_keys($raw) !== range(0, count($raw) - 1) ? [$raw] : $raw;
            foreach ($meters as $meter) {
                if (!is_array($meter)) continue;
                $mode = isset($meter['mode']) ? $meter['mode'] : '';
                $brand = isset($meter['brand_name']) ? $meter['brand_name'] : '';
                $model = isset($meter['model_name']) ? $meter['model_name'] : '';
                $serial = isset($meter['serial']) ? $meter['serial'] : '';
                $ct = isset($meter['ct_ratio']) ? $meter['ct_ratio'] : '';
                $sim = isset($meter['sim_number']) ? $meter['sim_number'] : '';
                $loc = isset($meter['location']) ? $meter['location'] : '';
                $led1 = isset($meter['led1']) ? $meter['led1'] : '';
                $led2 = isset($meter['led2']) ? $meter['led2'] : '';
                $led6 = isset($meter['led6']) ? $meter['led6'] : '';
                $gsm = isset($meter['gsm_signal']) ? $meter['gsm_signal'] : '';
                $characteristicsParts = [];
                if ($mode !== '') $characteristicsParts[] = "Mode: {$mode}";
                if ($serial !== '') $characteristicsParts[] = "Serial: {$serial}";
                if ($ct !== '') $characteristicsParts[] = "CT Ratio: {$ct}";
                if ($sim !== '') $characteristicsParts[] = "SIM: {$sim}";
                if ($loc !== '') $characteristicsParts[] = "Location: {$loc}";
                if ($led1 !== '') $characteristicsParts[] = "LED1: {$led1}";
                if ($led2 !== '') $characteristicsParts[] = "LED2: {$led2}";
                if ($led6 !== '') $characteristicsParts[] = "LED6: {$led6}";
                if ($gsm !== '') $characteristicsParts[] = "GSM: {$gsm}";
                $characteristics = implode(' | ', $characteristicsParts);
                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Telemetry - Meter', NULL, ?, ?, 1, ?)");
                safeExecute($stmt, [$reportId, $brand, $model, $characteristics]);
            }
        }
    }

    // Process Energy Meters data
    // First, delete existing energy meters for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Energy Meter'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new meters (if any)
    if (!empty($_POST['energy_meter_data'])) {
        $energyMeters = json_decode($_POST['energy_meter_data'], true);
        if (is_array($energyMeters)) {
            foreach ($energyMeters as $meter) {
                $scope = isset($meter['scope_text']) ? $meter['scope_text'] : '';
                $brand = isset($meter['brand_name']) ? $meter['brand_name'] : '';
                $model = isset($meter['model_name']) ? $meter['model_name'] : '';
                $rs485Address = isset($meter['rs485_address']) ? $meter['rs485_address'] : '';
                $ctRatio = isset($meter['ct_ratio']) ? $meter['ct_ratio'] : '';

                $characteristicsParts = [];
                if ($rs485Address !== '') $characteristicsParts[] = "RS485 Address: {$rs485Address}";
                if ($ctRatio !== '') $characteristicsParts[] = "CT Ratio: {$ctRatio}";
                $characteristics = implode(' | ', $characteristicsParts);

                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Energy Meter', ?, ?, ?, 1, ?)");
                safeExecute($stmt, [$reportId, $scope, $brand, $model, $characteristics]);
            }
        }
    }

    // Process Communications data
    // First, delete existing communications devices for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Communications'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new devices (if any)
    if (!empty($_POST['communications_data'])) {
        $communications = json_decode($_POST['communications_data'], true);
        if (is_array($communications)) {
            foreach ($communications as $device) {
                $equipment = isset($device['equipment']) ? $device['equipment'] : '';
                $model = isset($device['model']) ? $device['model'] : '';
                $serial = isset($device['serial']) ? $device['serial'] : '';
                $mac = isset($device['mac']) ? $device['mac'] : '';
                $ip = isset($device['ip']) ? $device['ip'] : '';
                $sim = isset($device['sim']) ? $device['sim'] : '';
                $location = isset($device['location']) ? $device['location'] : '';
                $ftpServer = isset($device['ftp_server']) ? $device['ftp_server'] : '';
                $ftpUsername = isset($device['ftp_username']) ? $device['ftp_username'] : '';
                $ftpPassword = isset($device['ftp_password']) ? $device['ftp_password'] : '';
                $fileFormat = isset($device['file_format']) ? $device['file_format'] : '';

                $characteristicsParts = [];
                if ($serial !== '') $characteristicsParts[] = "ID/Serial: {$serial}";
                if ($mac !== '') $characteristicsParts[] = "MAC: {$mac}";
                if ($ip !== '') $characteristicsParts[] = "IP: {$ip}";
                if ($sim !== '') $characteristicsParts[] = "SIM Card: {$sim}";
                if ($location !== '') $characteristicsParts[] = "Location: {$location}";
                if ($ftpServer !== '') $characteristicsParts[] = "FTP Server: {$ftpServer}";
                if ($ftpUsername !== '') $characteristicsParts[] = "FTP Username: {$ftpUsername}";
                if ($ftpPassword !== '') $characteristicsParts[] = "FTP Password: {$ftpPassword}";
                if ($fileFormat !== '') $characteristicsParts[] = "File Format: {$fileFormat}";
                $characteristics = implode(' | ', $characteristicsParts);

                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Communications', NULL, ?, ?, 1, ?)");
                safeExecute($stmt, [$reportId, $equipment, $model, $characteristics]);
            }
        }
    }

    // Process Clamp Measurements data
    // First, delete existing clamp measurements for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Amperometric Clamp'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new measurements (if any)
    if (!empty($_POST['clamp_measurements_data'])) {
        $clampMeasurements = json_decode($_POST['clamp_measurements_data'], true);
        if (is_array($clampMeasurements)) {
            foreach ($clampMeasurements as $measurement) {
                $equipment = isset($measurement['equipment']) ? $measurement['equipment'] : '';
                $l1Current = isset($measurement['l1_current']) ? $measurement['l1_current'] : '';
                $l2Current = isset($measurement['l2_current']) ? $measurement['l2_current'] : '';
                $l3Current = isset($measurement['l3_current']) ? $measurement['l3_current'] : '';
                $matchWithMeter = isset($measurement['match_with_meter']) ? $measurement['match_with_meter'] : 'no';

                $characteristicsParts = [];
                if ($equipment !== '') $characteristicsParts[] = "Equipment: {$equipment}";
                if ($l1Current !== '') $characteristicsParts[] = "L1 Current: {$l1Current}A";
                if ($l2Current !== '') $characteristicsParts[] = "L2 Current: {$l2Current}A";
                if ($l3Current !== '') $characteristicsParts[] = "L3 Current: {$l3Current}A";
                if ($matchWithMeter === 'yes') $characteristicsParts[] = "Matches with meter: Yes";
                $characteristics = implode(' | ', $characteristicsParts);

                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Amperometric Clamp', NULL, ?, NULL, 1, ?)");
                safeExecute($stmt, [$reportId, $equipment, $characteristics]);
            }
        }
    }

    // Note: Duplicate protection processing removed to avoid overwriting rated current and cable entries

    // Process Earth Protection Circuit data
    // First, delete existing earth protection for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Earth Protection Circuit'");
        safeExecute($stmt, [$reportId]);
    }

    // DEBUG: Log received earth_resistance
    error_log("EARTH SAVE: earth_resistance = '" . ($_POST['earth_resistance'] ?? 'NOT SET') . "'");

    if (isset($_POST['earth_resistance']) && trim($_POST['earth_resistance']) !== '') {
        $resistance = trim($_POST['earth_resistance']);

        $characteristicsParts = [];
        // Store unit as ASCII 'Ohm' to avoid encoding issues when files or DB are in the wrong charset
        if ($resistance !== '') $characteristicsParts[] = "Resistance: {$resistance} Ohm";

        // Check if reinforcement is needed based on resistance value
        $resistanceValue = floatval($resistance);
        if ($resistanceValue > 10) {
            $characteristicsParts[] = "Earthing/Reinforcement Needed: Yes";
        }

        $characteristics = implode(' | ', $characteristicsParts);

        $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Earth Protection Circuit', NULL, NULL, NULL, 1, ?)");
        safeExecute($stmt, [$reportId, $characteristics]);

        // Also persist the numeric resistance into the dedicated table report_earth_protection
        // Normalize numeric value
        $resistanceValue = is_numeric($resistance) ? (float)$resistance : floatval(str_replace(',', '.', $resistance));
        try {
            $stmtUp = $pdo->prepare("SELECT id FROM report_earth_protection WHERE report_id = ? LIMIT 1");
            $stmtUp->execute([$reportId]);
            $existing = $stmtUp->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $stmtUpd = $pdo->prepare("UPDATE report_earth_protection SET resistance_ohm = ?, updated_at = CURRENT_TIMESTAMP WHERE report_id = ?");
                $stmtUpd->execute([$resistanceValue, $reportId]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO report_earth_protection (report_id, resistance_ohm, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmtIns->execute([$reportId, $resistanceValue]);
            }
        } catch (PDOException $e) {
            error_log("[EARTH] ❌ Failed to upsert report_earth_protection for report_id={$reportId}: " . $e->getMessage());
        }
    }

    // Process Punch List data
    // First, delete existing punch list items for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Punch List Item'");
        safeExecute($stmt, [$reportId]);
    }

    // Then insert new items (if any)
    if (!empty($_POST['punch_list_data'])) {
        $punchListItems = json_decode($_POST['punch_list_data'], true);
        if (is_array($punchListItems)) {
            foreach ($punchListItems as $item) {
                $id = isset($item['id']) ? $item['id'] : '';
                $severity = isset($item['severity']) ? $item['severity'] : '';
                $description = isset($item['description']) ? $item['description'] : '';
                $openingDate = isset($item['opening_date']) ? $item['opening_date'] : '';
                $responsible = isset($item['responsible']) ? $item['responsible'] : '';
                $resolutionDate = isset($item['resolution_date']) ? $item['resolution_date'] : '';

                $characteristicsParts = [];
                if ($id !== '') $characteristicsParts[] = "ID: {$id}";
                if ($severity !== '') $characteristicsParts[] = "Severity: {$severity}";
                if ($description !== '') $characteristicsParts[] = "Description: {$description}";
                if ($openingDate !== '') $characteristicsParts[] = "Opening Date: {$openingDate}";
                if ($responsible !== '') $characteristicsParts[] = "Responsible: {$responsible}";
                if ($resolutionDate !== '') $characteristicsParts[] = "Resolution Date: {$resolutionDate}";
                $characteristics = implode(' | ', $characteristicsParts);

                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Punch List Item', NULL, NULL, NULL, 1, ?)");
                safeExecute($stmt, [$reportId, $characteristics]);
            }
        }
    }

    // Process String Measurements data
    // First, delete existing string measurements for this report
    if ($reportId) {
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement'");
        safeExecute($stmt, [$reportId]);
    }

    // If the frontend didn't send an aggregated JSON payload, try to assemble it
    // from individual patterned POST inputs (supports both with and without inverter index).
    if (empty($_POST['string_measurements_data'])) {
        $assembled = [];

        foreach ($_POST as $k => $v) {
            if (strpos($k, 'string_') !== 0) continue;

            $parts = explode('_', $k);
            // Expected forms:
            // 1) string_{metric}_{mppt}_{string}                (no inverter index)
            // 2) string_{metric}_{inverterIndex}_{mppt}_{string} (with inverter index)
            if (count($parts) === 4) {
                list(, $metric, $mppt, $str) = $parts;
                $invIndex = null;
            } elseif (count($parts) >= 5) {
                list(, $metric, $invIndex, $mppt, $str) = $parts;
            } else {
                continue;
            }

            $key = (is_null($invIndex) ? '0' : $invIndex) . "_{$mppt}_{$str}";
            if (!isset($assembled[$key])) {
                $assembled[$key] = [
                    'inverter_index' => $invIndex,
                    'mppt' => $mppt,
                    'string_num' => $str,
                    'metrics' => []
                ];
            }
            $assembled[$key]['metrics'][$metric] = $v;
        }

        if (!empty($assembled)) {
            // Try to enrich with inverter identifiers if inverters_data is available
            $invertersList = [];
            if (!empty($_POST['inverters_data'])) {
                $tmp = json_decode($_POST['inverters_data'], true);
                if (is_array($tmp)) $invertersList = $tmp;
            }

            $stringMeasurementsArr = [];
            foreach ($assembled as $entry) {
                $invIdx = $entry['inverter_index'];
                $inverterId = '';
                if ($invIdx !== null && isset($invertersList[$invIdx])) {
                    $inverterId = $invertersList[$invIdx]['model_id'] ?? '';
                } elseif ($invIdx !== null) {
                    // fallback textual inverter ref
                    $inverterId = 'INV' . str_pad(intval($invIdx) + 1, 3, '0', STR_PAD_LEFT);
                }

                $metrics = $entry['metrics'] ?? [];

                $stringMeasurementsArr[] = [
                    'inverter_id' => $inverterId,
                    'inverter_index' => $invIdx,
                    'mppt' => $entry['mppt'],
                    'string_num' => $entry['string_num'],
                    'voc' => isset($metrics['voc']) ? $metrics['voc'] : (isset($metrics['Voc']) ? $metrics['Voc'] : ''),
                    'isc' => isset($metrics['isc']) ? $metrics['isc'] : (isset($metrics['current']) ? $metrics['current'] : (isset($metrics['isc']) ? $metrics['isc'] : '')),
                    'vmp' => isset($metrics['vmp']) ? $metrics['vmp'] : '',
                    'imp' => isset($metrics['imp']) ? $metrics['imp'] : (isset($metrics['imp']) ? $metrics['imp'] : ''),
                    'rins' => isset($metrics['rins']) ? $metrics['rins'] : '',
                    'irr' => isset($metrics['irr']) ? $metrics['irr'] : '',
                    'temp' => isset($metrics['temp']) ? $metrics['temp'] : '',
                    'rlo' => isset($metrics['rlo']) ? $metrics['rlo'] : '',
                    'notes' => isset($metrics['notes']) ? $metrics['notes'] : (isset($metrics['Notes']) ? $metrics['Notes'] : ''),
                ];
            }

            if (!empty($stringMeasurementsArr)) {
                $_POST['string_measurements_data'] = json_encode($stringMeasurementsArr);
            }
        }
    }

    if (!empty($_POST['string_measurements_data'])) {
        error_log("[STRING_SAVE] Processing string_measurements_data for report_id={$reportId}");
        $stringMeasurements = json_decode($_POST['string_measurements_data'], true);
        if (is_array($stringMeasurements)) {
            // First, delete old String Measurement entries to avoid duplicates in report_equipment
            $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement'");
            safeExecute($stmt, [$reportId]);
            error_log("[STRING_SAVE] Deleted old string measurements from report_equipment");

            // Also ensure mppt_string_measurements array is initialized; we'll upsert per-entry below

            // Then insert new ones
            $count = 0;
            foreach ($stringMeasurements as $string) {
                $inverterId = isset($string['inverter_id']) ? $string['inverter_id'] : '';
                $mppt = isset($string['mppt']) ? $string['mppt'] : '';
                $stringNum = isset($string['string_num']) ? $string['string_num'] : '';
                $voc = isset($string['voc']) ? $string['voc'] : '';
                $isc = isset($string['isc']) ? $string['isc'] : (isset($string['current']) ? $string['current'] : '');
                $vmp = isset($string['vmp']) ? $string['vmp'] : '';
                $imp = isset($string['imp']) ? $string['imp'] : '';
                $rins = isset($string['rins']) ? $string['rins'] : '';
                $irr = isset($string['irr']) ? $string['irr'] : '';
                $temp = isset($string['temp']) ? $string['temp'] : '';
                $rlo = isset($string['rlo']) ? $string['rlo'] : '';
                $notes = isset($string['notes']) ? $string['notes'] : '';
                $current = isset($string['current']) ? $string['current'] : (isset($string['isc']) ? $string['isc'] : '');
                // Preserve raw user input strings for numeric fields (do not normalize)
                foreach (['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'current'] as $numKey) {
                    if (isset($$numKey) && is_string($$numKey)) {
                        // keep as entered
                    }
                }
                $characteristicsParts = [];
                if ($inverterId !== '') $characteristicsParts[] = "Inverter: {$inverterId}";
                if ($mppt !== '') $characteristicsParts[] = "MPPT: {$mppt}";
                if ($stringNum !== '') $characteristicsParts[] = "String: {$stringNum}";
                if ($voc !== '') $characteristicsParts[] = "Voc: {$voc}V";
                if ($isc !== '') $characteristicsParts[] = "Isc: {$isc}A";
                if ($vmp !== '') $characteristicsParts[] = "Vmp: {$vmp}V";
                if ($imp !== '') $characteristicsParts[] = "Imp: {$imp}A";
                if ($rins !== '') $characteristicsParts[] = "R.INS: {$rins}";
                if ($irr !== '') $characteristicsParts[] = "Irr: {$irr}";
                if ($temp !== '') $characteristicsParts[] = "Temp: {$temp}";
                if ($rlo !== '') $characteristicsParts[] = "R.LO: {$rlo}";
                if ($current !== '') $characteristicsParts[] = "Current: {$current}A";
                if ($notes !== '') $characteristicsParts[] = "Notes: {$notes}";
                $characteristics = implode(' | ', $characteristicsParts);
                $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'String Measurement', NULL, NULL, NULL, 1, ?)");
                safeExecute($stmt, [$reportId, $characteristics]);

                // Also persist into mppt_string_measurements (upsert) for consistency across load paths
                try {
                    $inverterIndex = 0;
                    if (!empty($invertersList) && $inverterId !== '') {
                        foreach ($invertersList as $ii => $inv) {
                            if ((string)($inv['model_id'] ?? '') === (string)$inverterId) {
                                $inverterIndex = $ii;
                                break;
                            }
                        }
                    }
                    $mpptUpsert = $pdo->prepare("INSERT INTO mppt_string_measurements (report_id, inverter_index, mppt, string_num, voc, isc, vmp, imp, rins, irr, temp, rlo, notes, current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE voc = VALUES(voc), isc = VALUES(isc), vmp = VALUES(vmp), imp = VALUES(imp), rins = VALUES(rins), irr = VALUES(irr), temp = VALUES(temp), rlo = VALUES(rlo), notes = VALUES(notes), current = VALUES(current)");
                    safeExecute($mpptUpsert, [$reportId, $inverterIndex, $mppt, $stringNum, $voc, $isc, $vmp, $imp, $rins, $irr, $temp, $rlo, $notes, $current]);
                } catch (PDOException $mpptEx) {
                    error_log('[STRING_SAVE] ❌ mppt upsert failed: ' . $mpptEx->getMessage());
                }
                $count++;
            }
            error_log("[STRING_SAVE] ✅ Saved {$count} string measurements to report_equipment");
        }
    }

    // Save Additional Notes from Finish tab (if provided) or fallback to preview cookie
    $notesText = '';
    if (isset($_POST['notes']) && trim($_POST['notes']) !== '') {
        $notesText = trim($_POST['notes']);
    } elseif (!empty($_COOKIE['commissioning_notes_preview'])) {
        // cookie is URL-encoded by the client
        $notesText = trim(urldecode($_COOKIE['commissioning_notes_preview']));
    }

    if ($notesText !== '') {
        // Remove any previous Additional Notes entries for this report to avoid duplicates
        $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Additional Notes'");
        safeExecute($stmt, [$reportId]);

        // Store raw notes; preserve newlines when rendering (we'll nl2br when showing)
        $characteristics = "Notes: " . $notesText;
        $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Additional Notes', NULL, NULL, NULL, 1, ?)");
        safeExecute($stmt, [$reportId, $characteristics]);

        // Expire preview cookie so it doesn't persist indefinitely
        setcookie('commissioning_notes_preview', '', time() - 3600, '/');
    }

    // Save Finish Photos Link (only update if a non-empty link is provided; otherwise preserve existing)
    // Avoid deleting existing link if the field is omitted or empty due to partial submits
    if (!empty($_POST['finish_photos_link'])) {
        $link = trim($_POST['finish_photos_link']);
        if ($link !== '') {
            // Replace previous link
            $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Finish - Photos Link'");
            safeExecute($stmt, [$reportId]);
            $characteristics = 'URL: ' . $link;
            $stmt = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Finish - Photos Link', NULL, 'OneDrive', NULL, 1, ?)");
            safeExecute($stmt, [$reportId, $characteristics]);
        }
    }

    // Save Finish Photos Checklist
    // Only delete/replace when a valid JSON payload is provided, to avoid accidental data loss
    if (isset($_POST['finish_photos_data'])) {
        $fpRaw = $_POST['finish_photos_data'];
        $fp = json_decode($fpRaw, true);
        if (is_array($fp)) {
            // Replace existing rows for this report
            $stmt = $pdo->prepare("DELETE FROM report_equipment WHERE report_id = ? AND equipment_type = 'Finish - Photo Checklist'");
            safeExecute($stmt, [$reportId]);

            $ins = $pdo->prepare("INSERT INTO report_equipment (report_id, equipment_type, deployment_status, brand, model, quantity, characteristics) VALUES (?, 'Finish - Photo Checklist', ?, ?, NULL, 1, ?)");
            foreach ($fp as $row) {
                if (!is_array($row)) continue;
                $key = isset($row['key']) ? trim($row['key']) : '';
                $label = isset($row['label']) ? trim($row['label']) : '';
                $status = isset($row['status']) ? trim($row['status']) : '';
                $note = isset($row['note']) ? trim($row['note']) : '';
                // Skip completely empty rows
                if ($label === '' && $status === '' && $note === '' && $key === '') continue;
                $charsParts = [];
                if ($key !== '') $charsParts[] = 'Key: ' . $key;
                if ($note !== '') $charsParts[] = 'Note: ' . $note;
                $chars = implode(' | ', $charsParts);
                safeExecute($ins, [$reportId, $status !== '' ? $status : null, $label !== '' ? $label : null, $chars]);
            }
        }
        // If payload is not a valid array, do nothing (preserve existing DB data)
    }
    // Commit transaction
    $pdo->commit();
    error_log("[SAVE_REPORT] COMMIT success - reportId={$reportId}");

    // Get the actual session ID for cleanup
    $actualSessionId = $_SESSION['draft_session_id'] ?? session_id();

    //  Limpar apenas o rascunho deste relatório nesta sessão (evitar apagar rascunhos de outros relatórios em paralelo)
    if ($reportId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE report_id = ? AND session_id = ?");
            safeExecute($stmt, [$reportId, $actualSessionId]);
        } catch (Exception $e) {
        }
    }
    // Optional: clean up orphaned drafts from this session without report_id (new report)
    try {
        $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE session_id = ? AND (report_id IS NULL OR report_id = 0)");
        safeExecute($stmt, [$actualSessionId]);
    } catch (Exception $e) {
    }

    // Redirect to report generation with appropriate success message
    $isUpdate = isset($_POST['report_id']) && intval($_POST['report_id']) > 0;
    $_SESSION['success'] = $isUpdate ? 'Report updated successfully.' : 'Report created successfully.';

    // DEBUG: Log redirect info

    ob_end_clean();
    error_log("[SAVE_REPORT] Redirecting to generate_report.php?id={$reportId}");
    header("Location: generate_report.php?id={$reportId}");
    exit;
} catch (PDOException $e) {
    error_log("[SAVE_REPORT] EXCEPTION: " . $e->getMessage());
    // Rollback transaction on error if active
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $e2) {
        // Ignore any errors during rollback attempt
    }

    $_SESSION['error'] = 'Error saving report: ' . $e->getMessage();
    ob_end_clean();
    error_log("[SAVE_REPORT] Redirecting to index.php due to error");
    echo 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
