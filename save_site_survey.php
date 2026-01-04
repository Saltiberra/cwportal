<?php
// Save Site Survey - supports JSON (AJAX) and legacy form fallback
require_once 'includes/auth.php';
requireLogin();
require_once 'config/database.php';
require_once 'includes/audit.php';

// Helper: check if a column exists in the current database
function columnExists($pdo, $table, $column)
{
    try {
        $sql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

header('Content-Type: application/json');

function json_success($payload)
{
    echo json_encode($payload);
    exit;
}
function json_error($message, $code = 500)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;

    if ($isJson) {
        // New JSON workflow used by site_survey.php
        $raw = file_get_contents('php://input');
        error_log('[SAVE_SITE_SURVEY] Raw input length: ' . strlen($raw));
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            error_log('[SAVE_SITE_SURVEY] JSON decode failed: ' . json_last_error_msg());
            json_error('Invalid JSON payload', 400);
        }
        error_log('[SAVE_SITE_SURVEY] JSON decoded successfully, action: ' . ($data['action'] ?? 'create'));

        $action = $data['action'] ?? 'create';
        $projectName = isset($data['project_name']) ? trim($data['project_name']) : null;
        if ($projectName === '') $projectName = null;
        $date = $data['date'] ?? null;
        // project_name and date are no longer mandatory — allow null values

        $user = getCurrentUser();
        $userId = $user['id'] ?? ($_SESSION['user_id'] ?? null);

        // Map
        $location = trim($data['location'] ?? '');
        $gps = trim($data['gps'] ?? '');
        $mapAreaM2 = isset($data['map_area_m2']) && $data['map_area_m2'] !== '' ? (float)$data['map_area_m2'] : null;
        $mapAzimuthDeg = isset($data['map_azimuth_deg']) && $data['map_azimuth_deg'] !== '' ? (float)$data['map_azimuth_deg'] : null;
        $mapPolygonCoords = isset($data['map_polygon_coords']) && !empty($data['map_polygon_coords']) ? json_encode($data['map_polygon_coords']) : null;
        $siteSurveyResponsibleId = !empty($data['site_survey_responsible_id']) ? (int)$data['site_survey_responsible_id'] : null;
        $responsibleName = trim($data['responsible'] ?? '');
        $accompaniedByName = trim($data['accompanied_by_name'] ?? '');
        $accompaniedByPhone = trim($data['accompanied_by_phone'] ?? '');
        $powerToInstall = isset($data['power_to_install']) && $data['power_to_install'] !== '' ? (float)$data['power_to_install'] : null;
        $certifiedPower = isset($data['certified_power']) && $data['certified_power'] !== '' ? (float)$data['certified_power'] : null;
        // Site-level fields kept for backward compatibility (current UI uses per-building fields)
        $parapetHeight = isset($data['parapet_height_m']) && $data['parapet_height_m'] !== '' ? (float)$data['parapet_height_m'] : null;
        $mountLocationType = trim($data['mount_location_type'] ?? '');
        $roofType = trim($data['roof_type'] ?? '');
        $supportStructure = trim($data['support_structure'] ?? '');
        $notes = trim($data['survey_notes'] ?? '');
        // Electrical injection fields
        $injectionPointType = trim($data['injection_point_type'] ?? '');
        $circuitType = trim($data['circuit_type'] ?? '');
        $inverterLocation = trim($data['inverter_location'] ?? '');
        $pvProtectionBoardLocation = trim($data['pv_protection_board_location'] ?? '');
        $pvBoardToInjectionDistance = (isset($data['pv_board_to_injection_distance_m']) && $data['pv_board_to_injection_distance_m'] !== '') ? (float)$data['pv_board_to_injection_distance_m'] : null;
        $injectionHasSpaceForSwitch = isset($data['injection_has_space_for_switch']) && $data['injection_has_space_for_switch'] !== '' ? (int)$data['injection_has_space_for_switch'] : null;
        $injectionHasBusbarSpace = isset($data['injection_has_busbar_space']) && $data['injection_has_busbar_space'] !== '' ? (int)$data['injection_has_busbar_space'] : null;
        // Painel elétrico / quadro principal
        $panelCableExteriorToMainGauge = trim($data['panel_cable_exterior_to_main_gauge'] ?? '');
        $panelBrandModel = trim($data['panel_brand_model'] ?? '');
        $breakerBrandModel = trim($data['breaker_brand_model'] ?? '');
        $breakerRatedCurrentA = (isset($data['breaker_rated_current_a']) && $data['breaker_rated_current_a'] !== '') ? (float)$data['breaker_rated_current_a'] : null;
        $breakerShortCircuitCurrentKa = (isset($data['breaker_short_circuit_current_ka']) && $data['breaker_short_circuit_current_ka'] !== '') ? (float)$data['breaker_short_circuit_current_ka'] : null;
        $residualCurrentMa = (isset($data['residual_current_ma']) && $data['residual_current_ma'] !== '') ? (int)$data['residual_current_ma'] : null;
        $earthMeasurementOhms = (isset($data['earth_measurement_ohms']) && $data['earth_measurement_ohms'] !== '') ? (float)$data['earth_measurement_ohms'] : null;
        $isBidirectionalMeter = (isset($data['is_bidirectional_meter']) && $data['is_bidirectional_meter'] !== '') ? (int)$data['is_bidirectional_meter'] : null;
        // Gerador
        $generatorExists = (isset($data['generator_exists']) && $data['generator_exists'] !== '') ? (int)$data['generator_exists'] : null;
        $generatorMode = trim($data['generator_mode'] ?? '');
        $generatorScope = trim($data['generator_scope'] ?? '');
        // Comunicações
        $commWifiNearPv = (isset($data['comm_wifi_near_pv']) && $data['comm_wifi_near_pv'] !== '') ? (int)$data['comm_wifi_near_pv'] : null;
        $commEthernetNearPv = (isset($data['comm_ethernet_near_pv']) && $data['comm_ethernet_near_pv'] !== '') ? (int)$data['comm_ethernet_near_pv'] : null;
        $commUtpRequirement = trim($data['comm_utp_requirement'] ?? '');
        $commUtpLengthM = (isset($data['comm_utp_length_m']) && $data['comm_utp_length_m'] !== '') ? (float)$data['comm_utp_length_m'] : null;
        $commRouterPortOpenAvailable = (isset($data['comm_router_port_open_available']) && $data['comm_router_port_open_available'] !== '') ? (int)$data['comm_router_port_open_available'] : null;
        $commRouterPortNumber = (isset($data['comm_router_port_number']) && $data['comm_router_port_number'] !== '') ? (int)$data['comm_router_port_number'] : null;
        $commMobileCoverageLevel = (isset($data['comm_mobile_coverage_level']) && $data['comm_mobile_coverage_level'] !== '') ? (int)$data['comm_mobile_coverage_level'] : null;
        $installationSiteNotes = trim($data['installation_site_notes'] ?? '');
        $challenges = trim($data['challenges'] ?? '');
        // Installation Site extras
        $roofAccessAvailable = (isset($data['roof_access_available']) && $data['roof_access_available'] !== '') ? (int)$data['roof_access_available'] : null;
        $permanentLadderFeasible = (isset($data['permanent_ladder_feasible']) && $data['permanent_ladder_feasible'] !== '') ? (int)$data['permanent_ladder_feasible'] : null;
        // Prefer new building_details (array of objects), fallback to building_names (array of strings)
        $buildingDetails = is_array($data['building_details'] ?? null) ? $data['building_details'] : null;
        if ($buildingDetails === null) {
            $names = is_array($data['building_names'] ?? null) ? $data['building_names'] : [];
            $buildingDetails = array_map(function ($n) {
                return ['name' => $n];
            }, $names);
        }
        $checklist = is_array($data['checklist_data'] ?? null) ? $data['checklist_data'] : [];
        $photoChecklist = is_array($data['photo_checklist'] ?? null) ? $data['photo_checklist'] : [];
        $roofDetails = (isset($data['roof_details']) && is_array($data['roof_details'])) ? $data['roof_details'] : [];
        $shadingDetails = (isset($data['shading_details']) && is_array($data['shading_details'])) ? $data['shading_details'] : [];
        // Site assessment now part of each roof row (legacy site_assessment ignored)

        $pdo->beginTransaction();
        error_log('[SAVE_SITE_SURVEY] Transaction started for action: ' . $action);

        if ($action === 'update') {
            $surveyId = isset($data['survey_id']) ? (int)$data['survey_id'] : 0;
            if ($surveyId <= 0) json_error('Missing survey_id for update', 400);

            // Only include is_draft in the UPDATE if the column exists in the DB
            $hasIsDraft = columnExists($pdo, 'site_survey_reports', 'is_draft');
            $isDraftSegment = $hasIsDraft ? ", is_draft = 0" : "";
            $updateSql = "UPDATE site_survey_reports SET 
                project_name=?, date=?, location=?, gps=?, map_area_m2=?, map_azimuth_deg=?, map_polygon_coords=?,
                responsible=?, site_survey_responsible_id=?,
                accompanied_by_name=?, accompanied_by_phone=?,
                power_to_install=?, certified_power=?,
                injection_point_type=?, circuit_type=?, inverter_location=?, pv_protection_board_location=?, pv_board_to_injection_distance_m=?, injection_has_space_for_switch=?, injection_has_busbar_space=?,
                panel_cable_exterior_to_main_gauge=?, panel_brand_model=?, breaker_brand_model=?, breaker_rated_current_a=?, breaker_short_circuit_current_ka=?, residual_current_ma=?, earth_measurement_ohms=?, is_bidirectional_meter=?,
                generator_exists=?, generator_mode=?, generator_scope=?,
                comm_wifi_near_pv=?, comm_ethernet_near_pv=?, comm_utp_requirement=?, comm_utp_length_m=?, comm_router_port_open_available=?, comm_router_port_number=?, comm_mobile_coverage_level=?,
                parapet_height_m=?, mount_location_type=?, roof_type=?, support_structure=?,
                survey_notes=?, challenges=?, installation_site_notes=?, roof_access_available=?, permanent_ladder_feasible=?, is_draft = 0,
                user_id = COALESCE(user_id, ?),
                updated_at = NOW()
            WHERE id=?";

            if (!$hasIsDraft) {
                // remove the is_draft assignment if column absent
                $updateSql = str_replace(', is_draft = 0', '', $updateSql);
            }

            $stmt = $pdo->prepare($updateSql);
            $params = [
                $projectName,
                $date,
                $location,
                $gps,
                $mapAreaM2,
                $mapAzimuthDeg,
                $mapPolygonCoords,
                $responsibleName,
                $siteSurveyResponsibleId,
                $accompaniedByName,
                $accompaniedByPhone,
                $powerToInstall,
                $certifiedPower,
                $injectionPointType,
                $circuitType,
                $inverterLocation,
                $pvProtectionBoardLocation,
                $pvBoardToInjectionDistance,
                $injectionHasSpaceForSwitch,
                $injectionHasBusbarSpace,
                $panelCableExteriorToMainGauge,
                $panelBrandModel,
                $breakerBrandModel,
                $breakerRatedCurrentA,
                $breakerShortCircuitCurrentKa,
                $residualCurrentMa,
                $earthMeasurementOhms,
                $isBidirectionalMeter,
                $generatorExists,
                $generatorMode,
                $generatorScope,
                $commWifiNearPv,
                $commEthernetNearPv,
                $commUtpRequirement,
                $commUtpLengthM,
                $commRouterPortOpenAvailable,
                $commRouterPortNumber,
                $commMobileCoverageLevel,
                $parapetHeight,
                $mountLocationType,
                $roofType,
                $supportStructure,
                $notes,
                $challenges,
                $installationSiteNotes,
                $roofAccessAvailable,
                $permanentLadderFeasible,
                $userId,
            ];
            // append survey id for WHERE
            $params[] = $surveyId;

            $stmt->execute($params);

            $currentId = $surveyId;
        } else {
            $insertData = [
                $projectName,
                $date,
                $location,
                $gps,
                $mapAreaM2,
                $mapAzimuthDeg,
                $mapPolygonCoords,
                $responsibleName,
                $siteSurveyResponsibleId,
                $accompaniedByName,
                $accompaniedByPhone,
                $powerToInstall,
                $certifiedPower,
                $injectionPointType,
                $circuitType,
                $inverterLocation,
                $pvProtectionBoardLocation,
                $pvBoardToInjectionDistance,
                $injectionHasSpaceForSwitch,
                $injectionHasBusbarSpace,
                $panelCableExteriorToMainGauge,
                $panelBrandModel,
                $breakerBrandModel,
                $breakerRatedCurrentA,
                $breakerShortCircuitCurrentKa,
                $residualCurrentMa,
                $earthMeasurementOhms,
                $isBidirectionalMeter,
                $generatorExists,
                $generatorMode,
                $generatorScope,
                $commWifiNearPv,
                $commEthernetNearPv,
                $commUtpRequirement,
                $commUtpLengthM,
                $commRouterPortOpenAvailable,
                $commRouterPortNumber,
                $commMobileCoverageLevel,
                $parapetHeight,
                $mountLocationType,
                $roofType,
                $supportStructure,
                $notes,
                $challenges,
                $installationSiteNotes,
                $roofAccessAvailable,
                $permanentLadderFeasible,
                $userId
            ];
            $placeholders = implode(',', array_fill(0, count($insertData), '?'));
            $sql = "INSERT INTO site_survey_reports (project_name,date,location,gps,map_area_m2,map_azimuth_deg,map_polygon_coords,responsible,site_survey_responsible_id,accompanied_by_name, accompanied_by_phone,power_to_install, certified_power,injection_point_type, circuit_type, inverter_location, pv_protection_board_location, pv_board_to_injection_distance_m, injection_has_space_for_switch, injection_has_busbar_space,panel_cable_exterior_to_main_gauge, panel_brand_model, breaker_brand_model, breaker_rated_current_a, breaker_short_circuit_current_ka, residual_current_ma, earth_measurement_ohms, is_bidirectional_meter,generator_exists, generator_mode, generator_scope,comm_wifi_near_pv, comm_ethernet_near_pv, comm_utp_requirement, comm_utp_length_m, comm_router_port_open_available, comm_router_port_number, comm_mobile_coverage_level,parapet_height_m, mount_location_type, roof_type, support_structure,survey_notes, challenges, installation_site_notes, roof_access_available, permanent_ladder_feasible,user_id) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            // sanity check: ensure placeholders match data count
            $placeCount = substr_count($sql, '?');
            if ($placeCount !== count($insertData)) {
                error_log('[SAVE_SITE_SURVEY] Placeholder count mismatch: placeholders=' . $placeCount . ' data=' . count($insertData));
                error_log('[SAVE_SITE_SURVEY] SQL: ' . $sql);
                error_log('[SAVE_SITE_SURVEY] Data: ' . json_encode($insertData));
            }
            try {
                $stmt->execute($insertData);
                $currentId = (int)$pdo->lastInsertId();
            } catch (Throwable $e) {
                error_log('[SAVE_SITE_SURVEY] Insert site_survey_reports failed: ' . $e->getMessage());
                error_log('[SAVE_SITE_SURVEY] SQL: ' . $sql);
                error_log('[SAVE_SITE_SURVEY] Data: ' . json_encode($insertData));
                throw $e;
            }
        }

        try {
            $pdo->prepare('DELETE FROM site_survey_buildings WHERE report_id=?')->execute([$currentId]);
            if (!empty($buildingDetails)) {
                $insB = $pdo->prepare('INSERT INTO site_survey_buildings (report_id, name, parapet_height_m, mount_location_type, roof_type, support_structure) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($buildingDetails as $b) {
                    $bname = trim((string)($b['name'] ?? ''));
                    if ($bname === '') continue;
                    $insB->execute([
                        $currentId,
                        $bname,
                        isset($b['parapet_height_m']) && $b['parapet_height_m'] !== '' ? (float)$b['parapet_height_m'] : null,
                        isset($b['mount_location_type']) ? (string)$b['mount_location_type'] : null,
                        isset($b['roof_type']) ? (string)$b['roof_type'] : null,
                        isset($b['support_structure']) ? (string)$b['support_structure'] : null
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log('[SAVE_SITE_SURVEY] Error while saving buildings: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            throw $e;
        }

        // Checklist: replace Survey - Checklist
        $pdo->prepare("DELETE FROM site_survey_items WHERE report_id=? AND item_type='Survey - Checklist'")->execute([$currentId]);
        if (!empty($checklist)) {
            $insC = $pdo->prepare('INSERT INTO site_survey_items (report_id, item_type, item_key, label, status, note, created_at) VALUES (?,?,?,?,?,?,NOW())');
            foreach ($checklist as $it) {
                $insC->execute([$currentId, 'Survey - Checklist', $it['key'] ?? null, $it['label'] ?? null, $it['status'] ?? null, $it['note'] ?? null]);
            }
        }

        // Photo Checklist: replace Survey - Photo Checklist (store only checked items)
        try {
            $pdo->prepare("DELETE FROM site_survey_items WHERE report_id=? AND item_type='Survey - Photo Checklist'")->execute([$currentId]);
            if (!empty($photoChecklist)) {
                $insP = $pdo->prepare('INSERT INTO site_survey_items (report_id, item_type, item_key, label, status, created_at) VALUES (?,?,?,?,?,NOW())');
                foreach ($photoChecklist as $ph) {
                    $insP->execute([$currentId, 'Survey - Photo Checklist', $ph['key'] ?? null, $ph['label'] ?? null, '1']);
                }
            }
        } catch (Throwable $e) {
            error_log('[SAVE_SITE_SURVEY] Error while saving photo checklist: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            throw $e;
        }


        // Roofs: replace per report
        try {
            $pdo->prepare('DELETE FROM site_survey_roofs WHERE report_id=?')->execute([$currentId]);
            if (!empty($roofDetails)) {
                $insR = $pdo->prepare('INSERT INTO site_survey_roofs (report_id, building_name, identification, angle_pitch, orientation, roof_condition, structure_visual, structure_weight_load, structure_wind_coverage, requires_expert_assessment) VALUES (?,?,?,?,?,?,?,?,?,?)');
                foreach ($roofDetails as $bname => $rows) {
                    if (!is_array($rows)) continue;
                    $bname = trim((string)$bname);
                    if ($bname === '') continue;
                    foreach ($rows as $r) {
                        $insR->execute([
                            $currentId,
                            $bname,
                            isset($r['identification']) ? (string)$r['identification'] : null,
                            (isset($r['angle_pitch']) && $r['angle_pitch'] !== '') ? (float)$r['angle_pitch'] : null,
                            isset($r['orientation']) ? (string)$r['orientation'] : null,
                            isset($r['roof_condition']) && $r['roof_condition'] !== '' ? (int)$r['roof_condition'] : null,
                            isset($r['structure_visual']) ? (string)$r['structure_visual'] : null,
                            isset($r['structure_weight_load']) ? (string)$r['structure_weight_load'] : null,
                            isset($r['structure_wind_coverage']) ? (string)$r['structure_wind_coverage'] : null,
                            isset($r['requires_expert_assessment']) ? (string)$r['requires_expert_assessment'] : null
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[SAVE_SITE_SURVEY] Error while saving roofs: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            throw $e;
        }

        // Shading: replace per report
        try {
            $pdo->prepare('DELETE FROM site_survey_shading_objects WHERE report_id=?')->execute([$currentId]);
            $pdo->prepare('DELETE FROM site_survey_shading WHERE report_id=?')->execute([$currentId]);
            if (!empty($shadingDetails)) {
                // store shading viability in installation_viable (DB uses installation_viable column)
                $insS = $pdo->prepare('INSERT INTO site_survey_shading (report_id, building_name, shading_status, installation_viable, notes) VALUES (?,?,?,?,?)');
                $insO = $pdo->prepare('INSERT INTO site_survey_shading_objects (report_id, building_name, object_type, cause, height_m, quantity, notes) VALUES (?,?,?,?,?,?,?)');
                foreach ($shadingDetails as $bname => $entry) {
                    $bname = trim((string)$bname);
                    if ($bname === '') continue;
                    $status = isset($entry['status']) ? (string)$entry['status'] : null;
                    // Map incoming boolean 'viable' to the 'installation_viable' column
                    $installationViable = isset($entry['viable']) && $entry['viable'] !== '' ? (int)$entry['viable'] : null;
                    $snotes = isset($entry['notes']) ? (string)$entry['notes'] : null;
                    $insS->execute([$currentId, $bname, $status, $installationViable, $snotes]);
                    $objects = is_array($entry['objects'] ?? null) ? $entry['objects'] : [];
                    foreach ($objects as $o) {
                        $insO->execute([
                            $currentId,
                            $bname,
                            isset($o['object_type']) ? (string)$o['object_type'] : null,
                            isset($o['cause']) ? (string)$o['cause'] : null,
                            (isset($o['height_m']) && $o['height_m'] !== '') ? (float)$o['height_m'] : null,
                            (isset($o['quantity']) && $o['quantity'] !== '') ? (int)$o['quantity'] : null,
                            isset($o['notes']) ? (string)$o['notes'] : null,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[SAVE_SITE_SURVEY] Error while saving shading: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            throw $e;
        }

        // Photos Repository Link: save as site_survey_items entry (keep legacy storage)
        $photosLink = isset($data['photos_link']) ? trim($data['photos_link']) : '';
        try {
            $pdo->prepare("DELETE FROM site_survey_items WHERE report_id=? AND item_type='Survey - Photos Link'")->execute([$currentId]);
            if ($photosLink !== '') {
                $insPhoto = $pdo->prepare("INSERT INTO site_survey_items (report_id, item_type, item_key, label, status, note, value, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                $insPhoto->execute([$currentId, 'Survey - Photos Link', 'photos_link', 'Photos Repository', null, null, $photosLink]);
            }
        } catch (Throwable $e) {
            // non-fatal: continue
        }

        logAction(($action === 'update' ? 'survey_edited' : 'survey_created'), 'site_survey_reports', $currentId, 'Save Site Survey', $projectName);

        $pdo->commit();
        error_log('[SAVE_SITE_SURVEY] Transaction committed successfully for survey_id: ' . $currentId);

        // Cleanup report drafts for this session and report (similar logic to save_report.php)
        try {
            $phpSessionId = session_id();
            $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE report_id = ? AND session_id = ?");
            $stmt->execute([$currentId, $phpSessionId]);
        } catch (Throwable $e) {
        }
        try {
            $phpSessionId = session_id();
            $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE session_id = ? AND (report_id IS NULL OR report_id = 0)");
            $stmt->execute([$phpSessionId]);
        } catch (Throwable $e) {
        }
        json_success(['success' => true, 'survey_id' => $currentId, 'action' => $action]);
    }

    // Legacy form fallback (redirects)
    header('Content-Type: text/html; charset=utf-8');
    $user = getCurrentUser();
    $surveyId = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : null;
    $projectName = isset($_POST['project_name']) ? trim($_POST['project_name']) : null;
    if ($projectName === '') $projectName = null;
    $date = $_POST['date'] ?? null;
    // Legacy fallback: project name and date are optional now

    $location = trim($_POST['location'] ?? '');
    $gps = trim($_POST['gps'] ?? '');
    $responsible = trim($_POST['responsible'] ?? '');
    $photosLink = trim($_POST['photos_link'] ?? '');
    $checklistJson = $_POST['survey_checklist_data'] ?? '';

    $pdo->beginTransaction();
    if ($surveyId) {
        // Only include is_draft if column exists
        $hasIsDraftLegacy = columnExists($pdo, 'site_survey_reports', 'is_draft');
        $sql = "UPDATE site_survey_reports SET project_name=?, date=?, responsible=?, location=?, gps=?";
        if ($hasIsDraftLegacy) $sql .= ", is_draft=0";
        $sql .= ", updated_at=NOW() WHERE id=?";
        $stmt = $pdo->prepare($sql);
        if ($hasIsDraftLegacy) {
            $stmt->execute([$projectName, $date, $responsible, $location, $gps, $surveyId]);
        } else {
            $stmt->execute([$projectName, $date, $responsible, $location, $gps, $surveyId]);
        }
        $action = 'survey_edited';
    } else {
        $stmt = $pdo->prepare("INSERT INTO site_survey_reports (project_name, date, responsible, location, gps, user_id, created_at) VALUES (?,?,?,?,?,?,NOW())");
        $stmt->execute([$projectName, $date, $responsible, $location, $gps, $user['id'] ?? null]);
        $surveyId = (int)$pdo->lastInsertId();
        $action = 'survey_created';
    }

    if ($photosLink !== '') {
        $pdo->prepare("DELETE FROM site_survey_items WHERE report_id=? AND item_type='Survey - Photos Link'")->execute([$surveyId]);
        $ins = $pdo->prepare("INSERT INTO site_survey_items (report_id, item_type, item_key, label, status, note, value, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $ins->execute([$surveyId, 'Survey - Photos Link', 'photos_link', 'Photos Repository', null, null, $photosLink]);
    }

    $items = [];
    if ($checklistJson) {
        $decoded = json_decode($checklistJson, true);
        if (is_array($decoded)) $items = $decoded;
    }
    $pdo->prepare("DELETE FROM site_survey_items WHERE report_id=? AND item_type='Survey - Checklist'")->execute([$surveyId]);
    if (!empty($items)) {
        $insItem = $pdo->prepare("INSERT INTO site_survey_items (report_id, item_type, item_key, label, status, note, created_at) VALUES (?,?,?,?,?,?,NOW())");
        foreach ($items as $it) {
            $insItem->execute([$surveyId, 'Survey - Checklist', $it['key'] ?? '', $it['label'] ?? '', $it['status'] ?? '', $it['note'] ?? '']);
        }
    }

    logAction($action, 'site_survey_reports', $surveyId, 'Site Survey saved', $projectName);
    $pdo->commit();
    header('Location: site_survey.php?survey_id=' . $surveyId . '&success=' . urlencode('Survey saved'));
    exit;
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $e2) {
        }
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        // Log full exception and POST payload for debugging
        error_log('[SAVE_SITE_SURVEY] Exception: ' . $e->getMessage());
        error_log('[SAVE_SITE_SURVEY] Trace: ' . $e->getTraceAsString());
        try {
            $raw = file_get_contents('php://input');
            error_log('[SAVE_SITE_SURVEY] Payload: ' . $raw);
        } catch (Throwable $e3) {
        }
        json_error($e->getMessage(), 500);
    }
    header('Location: ' . ($_POST['survey_id'] ? ('site_survey.php?survey_id=' . (int)$_POST['survey_id']) : 'site_survey.php') . '&error=' . urlencode($e->getMessage()));
    exit;
}
