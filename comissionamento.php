<?php

/**
 * Main Index File - PV Commissioning System
 * 
 * This file serves as the entry point for the commissioning application
 * 
 * Cache Buster: 2025-10-23-13:20
 */

// 🔒 Require login to access commissioning form
require_once 'includes/auth.php';
requireLogin();

// Include database connection
require_once 'config/database.php';

// Check if we need to clear drafts for a generic new report
if (isset($_GET['new']) && $_GET['new'] == '1') {
    $sessId = session_id();
    try {
        // Clear drafts for this session without a specific report_id
        $stmt = $pdo->prepare("DELETE FROM report_drafts WHERE session_id = ? AND (report_id IS NULL OR report_id = 0)");
        $stmt->execute([$sessId]);
        error_log("[COMISSIONAMENTO] Cleared draft for new report (session: $sessId)");

        // Redirect to same page without ?new=1 to prevent subsequent clearings on refresh
        header("Location: comissionamento.php");
        exit;
    } catch (Exception $e) {
        error_log("[COMISSIONAMENTO] Error clearing draft: " . $e->getMessage());
    }
}

// Function to get equipment brands from database
function getEquipmentBrands($type)
{
    global $pdo;

    // Mapping of equipment type to table name
    $tableMap = [
        'pv_module' => 'pv_module_brands',
        'inverter' => 'inverter_brands',
        'cable' => 'cable_brands',
        'circuit_breaker' => 'circuit_breaker_brands',
        'differential' => 'differential_brands',
        'energy_meter' => 'energy_meter_brands'
    ];

    // Check if type is valid
    if (!array_key_exists($type, $tableMap)) {
        return [];
    }

    // Get table name
    $tableName = $tableMap[$type];

    try {
        // Query database for brands
        $query = "SELECT id, brand_name FROM {$tableName} ORDER BY brand_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching brands: " . $e->getMessage());
        return [];
    }
}

// Load PV module brands from database
$pvModuleBrands = getEquipmentBrands('pv_module');

// Include header
include 'includes/header.php';

// Define form submission handler early (before form is rendered)
echo '<script>
    // Handle form submission from both floating button and "Update Report" button
    function handleFormSubmit(event) {
        event.preventDefault();
        console.log("[FORM SUBMIT] ✅ Form submission initiated");
        
        const form = document.getElementById("commissioningForm");
        if (form) {
            // Validate Finish/Photos checklist (required) before collecting/saving
            try {
                if (typeof validateFinishPhotos === "function") {
                    const ok = validateFinishPhotos();
                    if (!ok) {
                        console.warn("[FORM SUBMIT] Finish Photos validation failed - aborting submit");
                        return;
                    }
                }
            } catch (e) {
                console.warn("[FORM SUBMIT] Finish Photos validator not available or failed:", e);
            }

            // Collect Finish/Photos checklist data if available
            try {
                if (typeof collectFinishPhotosData === "function") {
                    collectFinishPhotosData();
                }
            } catch (e) {
                console.warn("[FORM SUBMIT] Finish Photos collector not available or failed:", e);
            }
            console.log("[FORM SUBMIT] Form action:", form.action);
            console.log("[FORM SUBMIT] Form method:", form.method);
            console.log("[FORM SUBMIT] About to submit form to", form.action);
            form.submit();
        } else {
            console.error("[FORM SUBMIT] ❌ Form not found!");
        }
    }
</script>';

// Check if we should create a new report or load an existing one
$reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : null;
$reportData = null;
$protectionData = [];
$earthFromDraft = null; // if draft provides earth_resistance, use it to restore and skip DB override
$isNewReport = !$reportId;

// If report ID is provided, load data from database
if ($reportId) {
    // 🔒 SECURITY: Validate that current user can access this report
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;

    $stmt = $pdo->prepare("SELECT * FROM commissioning_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $reportData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reportData) {
        echo '<div class="alert alert-danger" style="margin: 20px;">
                <h4>❌ Report Not Found</h4>
                <p>This report does not exist.</p>
                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
              </div>';
        include 'includes/footer.php';
        exit;
    }

    // 🔒 Check if user can edit this report:
    // - Creator (user_id matches) can edit
    // - Admin can edit
    // - Others can only view
    $canEdit = ($reportData['user_id'] == $userId) || ($userRole === 'admin');

    if (!$canEdit) {
        // User can view but not edit
        $_SESSION['report_view_only'] = true;
        error_log("[REPORT ACCESS] User {$userId} viewing (not editing) report {$reportId} created by user {$reportData['user_id']}");
    } else {
        // User can edit
        $_SESSION['report_view_only'] = false;
        error_log("[REPORT ACCESS] User {$userId} editing report {$reportId}");
    }

    // Get session ID for logging
    $sessionId = session_id();

    // Signal JS to load protection data from SQL, not localStorage
    echo '<script>
        window.reportId = ' . intval($reportId) . ';
        window.LOAD_PROTECTION_FROM_SQL = true;
        window.EDIT_MODE_SKIP_LOCALSTORAGE = true;
        console.log("[Edit Mode] ✅ reportId set to: " + window.reportId);
        console.log("[Edit Mode] ✅ Using SQL data for protection (ignoring localStorage)");
        console.log("[Edit Mode] ✅ Skipping localStorage restoration - using SQL data only");
        
        // 🔥 MPPT Manager: Inicializar carregamento de medições do SQL
        // IMPORTANTE: initMPPTManager deve ser chamado APÓS as tabelas serem geradas
        // (não no DOMContentLoaded, pois as tabelas ainda não existem)
        console.log("[Edit Mode] 🔥 MPPT Manager: aguardando geração das tabelas...");
        
        try {
            // Clear potentially stale autosave objects to prevent cross-report contamination
            localStorage.removeItem("commissioning_main-form");
            localStorage.removeItem("commissioning_epc_id");
            localStorage.removeItem("commissioning_representative_id");
            console.log("[Edit Mode] 🧹 Cleared localStorage keys for main form and EPC/Rep selections");
        } catch (e) { /* ignore */ }
    </script>';

    // EDIT MODE: Load protection data from report_drafts (which has the full form data)
    $protectionData = [];
    $draftHasProtectionKey = false; // specifically the presence of 'protection_data' key in draft JSON
    $hasAnyDraftForReport = false;   // any draft row exists for this report_id
    try {
        error_log("[PROTECTION] 📥 Loading from report_drafts for report_id={$reportId}");
        // Escopo de segurança: considerar apenas rascunhos desta sessão de rascunho
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;

        // Try to get protection_data from the latest draft (this is where it's actually stored during edit)
        $stmt = $pdo->prepare("
            SELECT form_data FROM report_drafts
            WHERE report_id = ? AND session_id = ?
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$reportId, $draftSessionId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            error_log("[PROTECTION] ⚠️ No draft found in report_drafts for report_id={$reportId}");
            error_log("[PROTECTION] 📥 Falling back to report_equipment table...");

            // FALLBACK: Load from report_equipment if no draft exists
            $stmt = $pdo->prepare("
                SELECT brand, model, quantity, rated_current, characteristics FROM report_equipment
                WHERE report_id = ? AND equipment_type = 'Protection - Circuit Breaker'
                ORDER BY id ASC
            ");
            $stmt->execute([$reportId]);
            $equipmentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($equipmentItems) {
                error_log("[PROTECTION] ✓ Found " . count($equipmentItems) . " items in report_equipment");

                // Convert report_equipment format to protection_data JSON format
                foreach ($equipmentItems as $eq) {
                    $item = [
                        'scope' => '',
                        'scope_text' => '',
                        'brand_id' => '',
                        'brand_name' => $eq['brand'] ?? '',
                        'model_id' => '',
                        'model_name' => $eq['model'] ?? '',
                        'quantity' => (int)($eq['quantity'] ?? 1),
                        'rated_current' => isset($eq['rated_current']) && $eq['rated_current'] !== '' ? (float)$eq['rated_current'] : null,
                        'raw_characteristics' => $eq['characteristics'] ?? ''
                    ];

                    // Parse characteristics string to extract fields
                    // Format: "Scope: Point of Injection | Brand | Model | Rated Current: 555"
                    if (!empty($eq['characteristics'])) {
                        $chars = $eq['characteristics'];

                        // Extract Scope
                        if (preg_match('/Scope:\s*([^|]+)/i', $chars, $m)) {
                            $item['scope_text'] = trim($m[1]);
                        }

                        // Extract Rated Current (support multiple formats)
                        $rated = null;
                        $patterns = [
                            '/Rated\s*Current\s*\(A\)?:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i', // "Rated Current: 630" or "Rated Current (A): 630A"
                            '/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i',                 // "Rated: 630A" or "Rated: 630"
                            '/\bIn\b\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i'                // "In: 630A"
                        ];
                        foreach ($patterns as $rx) {
                            if (preg_match($rx, $chars, $m)) {
                                $rated = str_replace(',', '.', $m[1]);
                                break;
                            }
                        }
                        if ($rated !== null && $rated !== '') {
                            $item['rated_current'] = floatval($rated);
                        }

                        // If brand/model missing from columns, try to parse from characteristics
                        if (empty($item['brand_name']) || empty($item['model_name'])) {
                            $parts = array_map('trim', explode('|', $chars));
                            // Remove any parts that are clearly labels
                            $parts = array_values(array_filter($parts, function ($p) {
                                return stripos($p, 'Scope:') === false && stripos($p, 'Rated') === false && stripos($p, 'In:') === false;
                            }));
                            if (empty($item['brand_name']) && isset($parts[0])) $item['brand_name'] = $parts[0];
                            if (empty($item['model_name']) && isset($parts[1])) $item['model_name'] = $parts[1];
                        }
                    }

                    $protectionData[] = $item;
                    error_log("[PROTECTION] Loaded from equipment: scope={$item['scope_text']}, brand={$item['brand_name']}, model={$item['model_name']}, rated={$item['rated_current']}");
                }
            } else {
                error_log("[PROTECTION] ⚠️ No items found in report_equipment either");
            }
        } else {
            error_log("[PROTECTION] ✓ Draft found, form_data size: " . strlen($draft['form_data']));
            $hasAnyDraftForReport = true; // mark that a draft exists for this report

            $formData = json_decode($draft['form_data'], true);
            if (!$formData) {
                error_log("[PROTECTION] ❌ Failed to decode JSON: " . json_last_error_msg());
            } else {
                error_log("[PROTECTION] ✓ JSON decoded, contains keys: " . implode(', ', array_keys($formData)));
                if (array_key_exists('protection_data', $formData)) {
                    $draftHasProtectionKey = true; // even if empty array, honor user's draft intent
                }

                if (isset($formData['protection_data']) && is_array($formData['protection_data'])) {
                    $protectionData = $formData['protection_data'];
                    error_log("[PROTECTION] ✓ Loaded " . count($protectionData) . " items from protection_data field");

                    // If draft explicitly has zero items, signal the frontend with an explicit empty list
                    if (count($protectionData) === 0) {
                        echo '<script>' . "\n" .
                            'console.log("[PROTECTION] Draft has explicit empty protection_data; enforcing empty UI state");' . "\n" .
                            'window.existingProtection = [];' . "\n" .
                            'document.addEventListener("DOMContentLoaded", function(){' . "\n" .
                            '  try {' . "\n" .
                            '    localStorage.removeItem("commissioning_protection_data");' . "\n" .
                            '    var f = document.getElementById("protection_data");' . "\n" .
                            '    if(f) f.value = "[]";' . "\n" .
                            '  } catch(e){}' . "\n" .
                            '});' . "\n" .
                            '</script>' . "\n";
                    }

                    // If any draft items are missing rated_current, try to enrich from SQL (report_equipment)
                    $needsEnrichment = false;
                    foreach ($protectionData as $it) {
                        if (!isset($it['rated_current']) || $it['rated_current'] === '' || $it['rated_current'] === null) {
                            $needsEnrichment = true;
                            break;
                        }
                    }
                    if ($needsEnrichment) {
                        error_log("[PROTECTION] 🔄 Enriching draft items with rated_current from report_equipment where possible...");
                        $stmt2 = $pdo->prepare("SELECT brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Protection - Circuit Breaker'");
                        $stmt2->execute([$reportId]);
                        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($protectionData as &$it) {
                            if (isset($it['rated_current']) && $it['rated_current'] !== '' && $it['rated_current'] !== null) continue;
                            foreach ($rows as $r) {
                                // Match by brand & model first; scope is optional
                                $brandMatch = empty($it['brand_name']) || strcasecmp(trim($it['brand_name']), trim($r['brand'])) === 0;
                                $modelMatch = empty($it['model_name']) || strcasecmp(trim($it['model_name']), trim($r['model'])) === 0;
                                if ($brandMatch && $modelMatch && !empty($r['characteristics'])) {
                                    $chars = $r['characteristics'];
                                    $rated = null;
                                    $patterns = [
                                        '/Rated\s*Current\s*\(A\)?:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i',
                                        '/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i',
                                        '/\bIn\b\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i'
                                    ];
                                    foreach ($patterns as $rx) {
                                        if (preg_match($rx, $chars, $m)) {
                                            $rated = str_replace(',', '.', $m[1]);
                                            break;
                                        }
                                    }
                                    if ($rated !== null && $rated !== '') {
                                        $it['rated_current'] = (float)$rated;
                                        error_log("[PROTECTION]   ✓ Enriched rated_current for {$it['brand_name']} {$it['model_name']} => {$it['rated_current']}");
                                        break;
                                    }
                                }
                            }
                        }
                        unset($it);
                    }

                    if ($protectionData) {
                        foreach ($protectionData as $item) {
                            error_log("[PROTECTION] Item: scope={$item['scope']}, brand={$item['brand_name']}, model={$item['model_name']}, rated={$item['rated_current']}");
                        }
                    }
                } else {
                    error_log("[PROTECTION] ❌ 'protection_data' not found or not array in form_data");
                    error_log("[PROTECTION] Available keys: " . implode(', ', array_keys($formData ?? [])));
                }

                // Capture simple earth resistance from draft, if present
                if (isset($formData['earth_resistance'])) {
                    $earthFromDraft = $formData['earth_resistance'];
                    error_log("[EARTH] ✓ Draft contains earth_resistance: " . $earthFromDraft);
                }

                // ===== Also load Protection Cables and Clamp Measurements from DRAFT (SQL) =====
                $protectionCableDataFromDraft = [];
                $clampMeasurementsDataFromDraft = [];
                if (isset($formData['protection_cable_data']) && is_array($formData['protection_cable_data'])) {
                    $protectionCableDataFromDraft = $formData['protection_cable_data'];
                    error_log("[PROTECTION CABLE] ✓ Loaded " . count($protectionCableDataFromDraft) . " items from protection_cable_data");
                }
                if (isset($formData['clamp_measurements_data']) && is_array($formData['clamp_measurements_data'])) {
                    $clampMeasurementsDataFromDraft = $formData['clamp_measurements_data'];
                    error_log("[CLAMP] ✓ Loaded " . count($clampMeasurementsDataFromDraft) . " items from clamp_measurements_data");
                }

                // ===== Load String Measurements from DB (mppt + equipment), ignore draft =====
                $stringMeasurementsFromDraft = null; // Always load from DB

                // Build equipment fallback array if draft is empty
                $stringMeasurementsFromEquipment = [];
                // Build arrays from report_equipment first (legacy storage)
                try {
                    $stmtSE = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' ORDER BY id");
                    $stmtSE->execute([$reportId]);
                    $rowsSE = $stmtSE->fetchAll(PDO::FETCH_ASSOC);
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
                            'rlo' => ''
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
                        }
                        $stringMeasurementsFromEquipment[] = $item;
                    }
                    if (!empty($stringMeasurementsFromEquipment)) {
                        error_log("[STRINGS] ✓ Fallback loaded " . count($stringMeasurementsFromEquipment) . " string measurement items from equipment");
                    }
                } catch (PDOException $se) {
                    error_log('[STRINGS] ❌ Error loading string measurements from equipment: ' . $se->getMessage());
                }

                // Then, append any mppt_string_measurements (authoritative) so they override same keys client-side
                try {
                    $mpptStmt = $pdo->prepare("SELECT * FROM mppt_string_measurements WHERE report_id = ? ORDER BY inverter_index, mppt, string_num");
                    $mpptStmt->execute([$reportId]);
                    $mpptRows = $mpptStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($mpptRows as $mr) {
                        $item = [
                            'inverter_id' => '',
                            'inverter_index' => isset($mr['inverter_index']) ? intval($mr['inverter_index']) : null,
                            'mppt' => isset($mr['mppt']) ? intval($mr['mppt']) : '',
                            'string_num' => isset($mr['string_num']) ? intval($mr['string_num']) : '',
                            'voc' => isset($mr['voc']) ? trim($mr['voc']) : '',
                            'isc' => isset($mr['isc']) ? trim($mr['isc']) : '',
                            'vmp' => isset($mr['vmp']) ? trim($mr['vmp']) : '',
                            'imp' => isset($mr['imp']) ? trim($mr['imp']) : '',
                            'rins' => isset($mr['rins']) ? trim($mr['rins']) : '',
                            'irr' => isset($mr['irr']) ? trim($mr['irr']) : '',
                            'temp' => isset($mr['temp']) ? trim($mr['temp']) : '',
                            'rlo' => isset($mr['rlo']) ? trim($mr['rlo']) : '',
                            'notes' => isset($mr['notes']) ? trim($mr['notes']) : ''
                        ];
                        if (!empty($existingInverters) && isset($existingInverters[$item['inverter_index']])) {
                            $item['inverter_id'] = $existingInverters[$item['inverter_index']]['model_id'] ?? '';
                        }
                        $stringMeasurementsFromEquipment[] = $item;
                    }
                    if (!empty($mpptRows)) error_log("[STRINGS] ✓ Appended " . count($mpptRows) . " string measurements from mppt_string_measurements table");
                } catch (PDOException $pe) {
                    error_log('[STRINGS] ❌ Could not load mppt_string_measurements: ' . $pe->getMessage());
                }
                if (empty($stringMeasurementsFromDraft)) {
                    try {
                        $stmtSE = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'String Measurement' ORDER BY id");
                        $stmtSE->execute([$reportId]);
                        $rowsSE = $stmtSE->fetchAll(PDO::FETCH_ASSOC);
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
                                'rlo' => ''
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
                            }
                            $stringMeasurementsFromEquipment[] = $item;
                        }
                        if (!empty($stringMeasurementsFromEquipment)) {
                            error_log("[STRINGS] ✓ Fallback loaded " . count($stringMeasurementsFromEquipment) . " string measurement items from equipment");
                            // Debug: build server-side toApply mapping to verify keys before sending to client
                            try {
                                $serverToApply = [];
                                foreach ($stringMeasurementsFromEquipment as $sm) {
                                    $invIdx = 0;
                                    // Use explicit inverter_index from DB if available, otherwise fallback to ID matching
                                    if (isset($sm['inverter_index']) && $sm['inverter_index'] !== null && $sm['inverter_index'] !== '') {
                                        $invIdx = intval($sm['inverter_index']);
                                    } elseif (!empty($existingInverters) && is_array($existingInverters)) {
                                        $matchFound = false;
                                        foreach ($existingInverters as $ii => $inv) {
                                            if (isset($inv['model_id']) && (string)$inv['model_id'] === (string)($sm['inverter_id'] ?? '')) {
                                                $invIdx = $ii;
                                                $matchFound = true;
                                                break;
                                            }
                                        }
                                        // Fallback: Check if inverter_id is in format INVxxx (e.g. INV001 = index 0)
                                        if (!$matchFound && isset($sm['inverter_id']) && preg_match('/^INV(\d+)$/', $sm['inverter_id'], $matches)) {
                                            $derivedIndex = intval($matches[1]) - 1;
                                            if ($derivedIndex >= 0 && $derivedIndex < count($existingInverters)) {
                                                $invIdx = $derivedIndex;
                                            }
                                        }
                                    }
                                    $mppt = isset($sm['mppt']) ? intval($sm['mppt']) : 1;
                                    $s = isset($sm['string_num']) ? intval($sm['string_num']) : 1;

                                    // Helper: should we apply override? If existing mapped value is textual (non-numeric)
                                    // and the new value is numeric zero (likely a DB coercion), then don't override.
                                    $setIfMeaningful = function (&$serverToApply, $key, $newVal) {
                                        $new = trim((string)($newVal ?? ''));
                                        if ($new === '') return; // nothing to set
                                        $existing = $serverToApply[$key] ?? '';
                                        $existingTrim = trim((string)$existing);
                                        // Determine numericness
                                        $newNum = is_numeric(str_replace(',', '.', $new));
                                        $existNum = $existingTrim !== '' ? is_numeric(str_replace(',', '.', $existingTrim)) : false;
                                        $newIsZeroNumeric = $newNum && floatval(str_replace(',', '.', $new)) == 0.0;
                                        // If existing is non-numeric and new is numeric zero, skip override
                                        if ($existingTrim !== '' && !$existNum && $newIsZeroNumeric) {
                                            return;
                                        }
                                        // Otherwise apply update
                                        $serverToApply[$key] = $new;
                                    };

                                    $setIfMeaningful($serverToApply, "string_voc_{$invIdx}_{$mppt}_{$s}", $sm['voc'] ?? '');
                                    $setIfMeaningful($serverToApply, "string_current_{$invIdx}_{$mppt}_{$s}", $sm['isc'] ?? '');
                                    $setIfMeaningful($serverToApply, "string_rins_{$invIdx}_{$mppt}_{$s}", $sm['rins'] ?? '');
                                    $setIfMeaningful($serverToApply, "string_irr_{$invIdx}_{$mppt}_{$s}", $sm['irr'] ?? '');
                                    $setIfMeaningful($serverToApply, "string_temp_{$invIdx}_{$mppt}_{$s}", $sm['temp'] ?? '');
                                    $setIfMeaningful($serverToApply, "string_rlo_{$invIdx}_{$mppt}_{$s}", $sm['rlo'] ?? '');
                                }
                                error_log("[STRINGS_PHP] toApply count: " . count($serverToApply) . " keys -> " . json_encode(array_keys($serverToApply)));
                            } catch (Throwable $dbe) {
                                error_log('[STRINGS_PHP] debug failed: ' . $dbe->getMessage());
                            }
                        }
                    } catch (PDOException $se) {
                        error_log('[STRINGS] ❌ Error loading string measurements from equipment: ' . $se->getMessage());
                    }
                }

                // Inject into window and initialize hidden inputs in EDIT MODE (UI replay handled later)
                echo '<script>' . "\n" .
                    'document.addEventListener("DOMContentLoaded", function(){' . "\n" .
                    '  try {' . "\n" .
                    '    // Make SQL draft the source of truth in EDIT MODE' . "\n" .
                    '    window.existingProtectionCables = ' . json_encode($protectionCableDataFromDraft ?: []) . ';' . "\n" .
                    '    window.existingClampMeasurements = ' . json_encode($clampMeasurementsDataFromDraft ?: []) . ';' . "\n" .
                    '    console.log("[RESTORE] existingProtectionCables from SQL:", Array.isArray(window.existingProtectionCables) ? window.existingProtectionCables.length : 0);' . "\n" .
                    '    console.log("[RESTORE] existingClampMeasurements from SQL:", Array.isArray(window.existingClampMeasurements) ? window.existingClampMeasurements.length : 0);' . "\n" .
                    '    // Initialize hidden inputs so autosave picks them up immediately' . "\n" .
                    '    var pcField = document.getElementById("protection_cable_data");' . "\n" .
                    '    if (pcField) pcField.value = JSON.stringify(window.existingProtectionCables || []);' . "\n" .
                    '    var clampField = document.getElementById("clamp_measurements_data");' . "\n" .
                    '    if (clampField) clampField.value = JSON.stringify(window.existingClampMeasurements || []);' . "\n" .
                    '    // Define invertersList early for string measurements mapping' . "\n" .
                    '    var invertersDataField = document.getElementById("inverters_data");' . "\n" .
                    '    if (invertersDataField && invertersDataField.value) {' . "\n" .
                    '      try { window.invertersList = JSON.parse(invertersDataField.value); } catch(e) { console.warn("[STRINGS] Could not parse inverters_data", e); }' . "\n" .
                    '    }' . "\n" .
                    '    // Prepare and initialize String Measurements hidden field if available from draft' . "\n" .
                    '    try {' . "\n" .
                    '      var stringsField = document.getElementById("string_measurements_data");' . "\n" .
                    '      if (!stringsField) {' . "\n" .
                    '        var formEl = document.getElementById("commissioningForm") || document.forms[0];' . "\n" .
                    '        if (formEl) {' . "\n" .
                    '          stringsField = document.createElement("input");' . "\n" .
                    '          stringsField.type = "hidden";' . "\n" .
                    '          stringsField.id = "string_measurements_data";' . "\n" .
                    '          stringsField.name = "string_measurements_data";' . "\n" .
                    '          stringsField.value = "{}";' . "\n" .
                    '          formEl.appendChild(stringsField);' . "\n" .
                    '        }' . "\n" .
                    '      }' . "\n" .
                    '      var strRaw = ' . json_encode($stringMeasurementsFromDraft) . ';' . "\n" .
                    '      var strEquip = ' . json_encode($stringMeasurementsFromEquipment) . ';' . "\n" .
                    '      var strParsed = null;' . "\n" .
                    '      console.log("[STRINGS] Draft raw payload present?", (typeof strRaw !== "undefined" && strRaw !== null), "type:", (strRaw && (typeof strRaw === "string" ? "string" : (Array.isArray(strRaw) ? "array" : typeof strRaw))));' . "\n" .
                    '      if (typeof strRaw !== "undefined" && strRaw !== null) {' . "\n" .
                    '        try { strParsed = (typeof strRaw === "string") ? JSON.parse(strRaw) : strRaw; } catch(e) { console.warn("[STRINGS] Could not parse draft string_measurements_data", e); }' . "\n" .
                    '      }' . "\n" .
                    '      // Se o draft for vazio (array ou objeto vazio), usa sempre o fallback do banco de dados' . "\n" .
                    '      var isDraftEmpty = false;' . "\n" .
                    '      if (strParsed === null || typeof strParsed === "undefined") isDraftEmpty = true;' . "\n" .
                    '      else if (Array.isArray(strParsed) && strParsed.length === 0) isDraftEmpty = true;' . "\n" .
                    '      else if (typeof strParsed === "object" && Object.keys(strParsed).length === 0) isDraftEmpty = true;' . "\n" .
                    '      if (isDraftEmpty && Array.isArray(strEquip) && strEquip.length > 0) {' . "\n" .
                    '        strParsed = strEquip;' . "\n" .
                    '        console.log("[STRINGS] Usar fallback do banco de dados (" + strEquip.length + ")");' . "\n" .
                    '      }' . "\n" .
                    '      if (stringsField && strParsed) {' . "\n" .
                    '        // If data is an array of entries, convert to input-id keyed map expected by restoreStringMeasurementsFromData' . "\n" .
                    '        var toApply = strParsed;' . "\n" .
                    '        if (Array.isArray(strParsed)) {' . "\n" .
                    '          toApply = {};' . "\n" .
                    '          try {' . "\n" .
                    '            // Map inverter_id -> inverter_index if needed' . "\n" .
                    '            var invIndexFromId = function(inverterId){' . "\n" .
                    '              try {' . "\n" .
                    '                var invs = (window.invertersList || []);' . "\n" .
                    '                for (var i=0;i<invs.length;i++){ if (String(invs[i].model_id) === String(inverterId)) return i; }' . "\n" .
                    '              } catch(e) {}' . "\n" .
                    '              return 0; // default to first inverter if unknown' . "\n" .
                    '            };' . "\n" .
                    '            strParsed.forEach(function(item){' . "\n" .
                    '              var invIdx = (item.inverter_index === null || typeof item.inverter_index === "undefined") ? (item.inverter_id ? invIndexFromId(item.inverter_id) : 0) : item.inverter_index;' . "\n" .
                    '              invIdx = parseInt(invIdx) || 0;' . "\n" .

                    '              var mppt = parseInt(item.mppt) || 1;' . "\n" .
                    '              var s = parseInt(item.string_num) || 1;' . "\n" .
                    '              if (item.voc !== undefined && item.voc !== "") toApply["string_voc_"+invIdx+"_"+mppt+"_"+s] = item.voc;' . "\n" .
                    '              if (item.isc !== undefined && item.isc !== "") toApply["string_current_"+invIdx+"_"+mppt+"_"+s] = item.isc;' . "\n" .
                    '              if (item.rins !== undefined && item.rins !== "") toApply["string_rins_"+invIdx+"_"+mppt+"_"+s] = item.rins;' . "\n" .
                    '              if (item.irr !== undefined && item.irr !== "") toApply["string_irr_"+invIdx+"_"+mppt+"_"+s] = item.irr;' . "\n" .
                    '              if (item.temp !== undefined && item.temp !== "") toApply["string_temp_"+invIdx+"_"+mppt+"_"+s] = item.temp;' . "\n" .
                    '              if (item.rlo !== undefined && item.rlo !== "") toApply["string_rlo_"+invIdx+"_"+mppt+"_"+s] = item.rlo;' . "\n" .
                    '              // notes not rendered in current table variant; skip safely' . "\n" .
                    '            });' . "\n" .
                    '          } catch(convErr) { console.warn("[STRINGS] Conversion from array to id-map failed", convErr); }' . "\n" .
                    '        }' . "\n" .
                    '        console.log("[STRINGS] strParsed length:", (Array.isArray(strParsed) ? strParsed.length : (strParsed ? Object.keys(strParsed).length : 0)));' . "\n" .
                    '        console.log("[STRINGS] toApply before assign:", toApply);' . "\n" .
                    '        stringsField.value = JSON.stringify(toApply);' . "\n" .
                    '        console.log("[STRINGS] toApply keys:", Object.keys(toApply||{}).length);' . "\n" .
                    '        // Clear legacy localStorage to avoid conflicts in EDIT MODE' . "\n" .
                    '        try { localStorage.removeItem("commissioning_string_inputs"); } catch(e){}' . "\n" .
                    '        // Ensure tables are rendered, then apply values using a readiness loop' . "\n" .
                    '        var tryRestoreStrings = function(maxTries){' . "\n" .
                    '          maxTries = (typeof maxTries === "number" ? maxTries : 80); // ~8s total' . "\n" .
                    '          try {' . "\n" .
                    '            // Pick one key from the payload to verify presence of inputs' . "\n" .
                    '            var keys = Object.keys(toApply || {});' . "\n" .
                    '            var probeId = keys.length ? keys[0] : null;' . "\n" .
                    '            var ready = probeId ? document.getElementById(probeId) : null;' . "\n" .
                    '            if (ready && typeof window.restoreStringMeasurementsFromData === "function") {' . "\n" .
                    '              window.restoreStringMeasurementsFromData(toApply);' . "\n" .
                    '              console.log("[STRINGS] ✓ Restored string measurements from DRAFT (readiness loop)");' . "\n" .
                    '              return;' . "\n" .
                    '            }' . "\n" .
                    '          } catch(_e) {}' . "\n" .
                    '          if (maxTries > 0) {' . "\n" .
                    '            setTimeout(function(){ tryRestoreStrings(maxTries - 1); }, 100);' . "\n" .
                    '          } else {' . "\n" .
                    '            console.warn("[STRINGS] Gave up waiting for string inputs to render");' . "\n" .
                    '          }' . "\n" .
                    '        };' . "\n" .
                    '        if (typeof window.loadAllInverterStringTables === "function") {' . "\n" .
                    '          window.loadAllInverterStringTables();' . "\n" .
                    '          tryRestoreStrings();' . "\n" .
                    '          // Defensive reapply passes' . "\n" .
                    '          setTimeout(function(){ try { window.restoreStringMeasurementsFromData(toApply); console.log("[STRINGS] 🔁 Reapplied after 2s"); } catch(e){} }, 2000);' . "\n" .
                    '          setTimeout(function(){ try { window.restoreStringMeasurementsFromData(toApply); console.log("[STRINGS] 🔁 Reapplied after 5s"); } catch(e){} }, 5000);' . "\n" .
                    '        } else {' . "\n" .
                    '          console.warn("[STRINGS] loadAllInverterStringTables not available at restore time");' . "\n" .
                    '          tryRestoreStrings();' . "\n" .
                    '        }' . "\n" .
                    '      }' . "\n" .
                    '    } catch (sErr) { console.warn("[STRINGS] Skip restore from draft", sErr); }' . "\n" .
                    '    // Restore simple Homopolar fields and Earth Resistance from DRAFT JSON if present' . "\n" .
                    '    try {' . "\n" .
                    '      var homInst = ' . json_encode($formData['homopolar_installer'] ?? '') . ';' . "\n" .
                    '      var homBrand = ' . json_encode($formData['homopolar_brand'] ?? '') . ';' . "\n" .
                    '      var homModel = ' . json_encode($formData['homopolar_model'] ?? '') . ';' . "\n" .
                    '      var earthVal = ' . json_encode($formData['earth_resistance'] ?? '') . ';' . "\n" .
                    '      if (typeof homInst === "string" || typeof homBrand === "string" || typeof homModel === "string") {' . "\n" .
                    '        var hi = document.getElementById("homopolar_installer"); if (hi) hi.value = homInst || "";' . "\n" .
                    '        var hb = document.getElementById("homopolar_brand"); if (hb) hb.value = homBrand || "";' . "\n" .
                    '        var hm = document.getElementById("homopolar_model"); if (hm) hm.value = homModel || "";' . "\n" .
                    '        console.log("[HOMOPOLAR] Restored from DRAFT:", {installer: homInst, brand: homBrand, model: homModel});' . "\n" .
                    '      }' . "\n" .
                    '      if (typeof earthVal === "string" && earthVal !== "") {' . "\n" .
                    '        var er = document.getElementById("earth_resistance"); if (er) { er.value = earthVal; console.log("[EARTH] Restored from DRAFT:", earthVal); }' . "\n" .
                    '      }' . "\n" .
                    '    } catch(hErr) { console.warn("[HOMOPOLAR] Skip restore from draft", hErr); }' . "\n" .
                    '  } catch(initErr) { console.warn("[RESTORE] Skipped restoration due to error", initErr); }' . "\n" .
                    '});' . "\n" .
                    '</script>';
            }
        }
    } catch (PDOException $e) {
        error_log("[PROTECTION] ❌ Database error: " . $e->getMessage());
    }

    // ============================================
    // LOAD EARTH RESISTANCE
    // ============================================
    // Prefer DRAFT value if present; otherwise load from DB table
    if ($earthFromDraft !== null && $earthFromDraft !== '') {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const earthInput = document.getElementById("earth_resistance");
                if (earthInput) {
                    earthInput.value = ' . json_encode($earthFromDraft) . ';
                    console.log("[EARTH] ✓ Loaded earth resistance from DRAFT: ' . addslashes((string)$earthFromDraft) . '");
                }
            });
        </script>';
        error_log("[EARTH] ✓ Using draft earth_resistance: " . $earthFromDraft);
    } else {
        try {
            error_log("[EARTH] 📥 Loading earth resistance for report_id={$reportId}");
            $stmt = $pdo->prepare("SELECT resistance_ohm FROM report_earth_protection WHERE report_id = ? LIMIT 1");
            $stmt->execute([$reportId]);
            $earth = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($earth && isset($earth['resistance_ohm'])) {
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const earthInput = document.getElementById("earth_resistance");
                        if (earthInput) {
                            earthInput.value = ' . json_encode($earth['resistance_ohm']) . ';
                            console.log("[EARTH] ✓ Loaded earth resistance: ' . $earth['resistance_ohm'] . '");
                        }
                    });
                </script>';
                error_log("[EARTH] ✓ Loaded resistance: " . $earth['resistance_ohm']);
            } else {
                // Fallback: try to read from report_equipment where Earth Protection Circuit may have been stored
                try {
                    $stmtAlt = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Earth Protection Circuit' ORDER BY id DESC LIMIT 1");
                    $stmtAlt->execute([$reportId]);
                    $rowAlt = $stmtAlt->fetch(PDO::FETCH_ASSOC);
                    if ($rowAlt && !empty($rowAlt['characteristics'])) {
                        $chars = $rowAlt['characteristics'];
                        $res = null;
                        if (preg_match('/Resistance:\s*([0-9]+(?:\.[0-9]+)?)/i', $chars, $m)) {
                            $res = $m[1];
                        } elseif (preg_match('/Resistance:\s*([0-9]+(?:,[0-9]+)?)/i', $chars, $m2)) {
                            $res = str_replace(',', '.', $m2[1]);
                        }
                        if ($res !== null) {
                            echo '<script>document.addEventListener("DOMContentLoaded", function(){ var earthInput = document.getElementById("earth_resistance"); if (earthInput) { earthInput.value = ' . json_encode($res) . '; console.log("[EARTH] ✓ Loaded earth resistance from report_equipment: ' . addslashes((string)$res) . '"); } });</script>';
                            error_log("[EARTH] ✓ Fallback loaded resistance from report_equipment: " . $res . " for report_id={$reportId}");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("[EARTH] ❌ Fallback DB error: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            error_log("[EARTH] ❌ Database error: " . $e->getMessage());
        }
    }

    // ============================================
    // LOAD TELEMETRY CREDENTIALS (SQL-only in edit mode)
    // Prefer report_drafts.form_data -> fallback to report_equipment
    // ============================================
    $telemetryCreds = [];
    $telemetrySource = 'none';
    try {
        // 1) Try latest draft (autosave) first
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;
        $stmtDraft = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtDraft->execute([$reportId, $draftSessionId]);
        $draftRow = $stmtDraft->fetch(PDO::FETCH_ASSOC);
        if ($draftRow && !empty($draftRow['form_data'])) {
            $form = json_decode($draftRow['form_data'], true);
            if (isset($form['telemetry_credential_data']) && is_array($form['telemetry_credential_data']) && count($form['telemetry_credential_data']) > 0) {
                foreach ($form['telemetry_credential_data'] as $c) {
                    if (!is_array($c)) continue;
                    $telemetryCreds[] = [
                        'inverter_index' => $c['inverter_index'] ?? '',
                        'inverter_text'  => $c['inverter_text'] ?? '',
                        'username'       => $c['username'] ?? '',
                        'password'       => $c['password'] ?? '',
                        'ip'             => $c['ip'] ?? ''
                    ];
                }
                if (!empty($telemetryCreds)) {
                    $telemetrySource = 'draft';
                }
            }
        }

        // 2) Fallback to persisted equipment rows if no draft content
        if ($telemetrySource === 'none') {
            $stmt = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Telemetry - Credential'");
            $stmt->execute([$reportId]);
            $telemetryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($telemetryItems)) {
                foreach ($telemetryItems as $item) {
                    $cred = [];
                    $parts = explode('|', $item['characteristics']);
                    foreach ($parts as $part) {
                        $pair = explode(':', $part, 2);
                        if (count($pair) === 2) {
                            $key = trim($pair[0]);
                            $value = trim($pair[1]);
                            if (stripos($key, 'Inverter') !== false) $cred['inverter_text'] = $value;
                            if (stripos($key, 'Username') !== false) $cred['username'] = $value;
                            if (stripos($key, 'Password') !== false) $cred['password'] = $value;
                            if (stripos($key, 'IP') !== false) $cred['ip'] = $value;
                        }
                    }
                    if (!empty($cred)) $telemetryCreds[] = $cred;
                }
                if (!empty($telemetryCreds)) {
                    $telemetrySource = 'equipment';
                }
            }
        }

        // If we have credentials from either source, log and trigger client render
        if (!empty($telemetryCreds)) {
            error_log("[TELEMETRY] ✓ Loaded " . count($telemetryCreds) . " credentials from " . $telemetrySource);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        const hidden = document.getElementById("telemetry_credential_data");
                        if (hidden) hidden.value = ' . json_encode(json_encode($telemetryCreds)) . ';
                        // If the JS initializer already ran, force a re-render
                        if (typeof renderTelemetryCredentialsTable === "function") {
                            // Update global list if available, otherwise rely on hidden input path
                            try { telemetryCredentialsList = ' . json_encode($telemetryCreds) . '; } catch (e) {}
                            renderTelemetryCredentialsTable();
                        }
                    } catch (e) { console.warn("[TELEMETRY] Could not inject credentials into hidden field", e); }
                });
            </script>';
        } else {
            error_log("[TELEMETRY] ℹ️ No telemetry credentials found in draft or equipment for report_id={$reportId}");
        }
    } catch (PDOException $e) {
        error_log("[TELEMETRY] ❌ Database error: " . $e->getMessage());
    }

    // ============================================
    // LOAD COMMUNICATIONS (SQL-only in edit mode)
    // Prefer report_drafts.form_data -> fallback to report_equipment
    // ============================================
    $communicationsFromSql = [];
    $commSource = 'none';
    try {
        // 1) Try latest draft first (autosave data)
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;
        $stmtDraftComm = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtDraftComm->execute([$reportId, $draftSessionId]);
        $draftRowComm = $stmtDraftComm->fetch(PDO::FETCH_ASSOC);
        if ($draftRowComm && !empty($draftRowComm['form_data'])) {
            $formC = json_decode($draftRowComm['form_data'], true);
            if (isset($formC['communications_data']) && is_array($formC['communications_data']) && count($formC['communications_data']) > 0) {
                foreach ($formC['communications_data'] as $d) {
                    if (!is_array($d)) continue;
                    $communicationsFromSql[] = [
                        'equipment'     => $d['equipment'] ?? '',
                        'model'         => $d['model'] ?? '',
                        'serial'        => $d['serial'] ?? '',
                        'mac'           => $d['mac'] ?? '',
                        'ip'            => $d['ip'] ?? '',
                        'sim'           => $d['sim'] ?? '',
                        'location'      => $d['location'] ?? '',
                        'ftp_server'    => $d['ftp_server'] ?? '',
                        'ftp_username'  => $d['ftp_username'] ?? '',
                        'ftp_password'  => $d['ftp_password'] ?? '',
                        'file_format'   => $d['file_format'] ?? ''
                    ];
                }
                if (!empty($communicationsFromSql)) $commSource = 'draft';
            }
        }

        // 2) Fallback to persisted equipment rows
        if ($commSource === 'none') {
            $stmtComm = $pdo->prepare("SELECT brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Communications' ORDER BY id");
            $stmtComm->execute([$reportId]);
            $rows = $stmtComm->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $device = [
                    'equipment' => $row['brand'] ?? '',
                    'model'     => $row['model'] ?? '',
                    'serial'    => '',
                    'mac' => '',
                    'ip' => '',
                    'sim' => '',
                    'location' => '',
                    'ftp_server' => '',
                    'ftp_username' => '',
                    'ftp_password' => '',
                    'file_format' => ''
                ];
                $parts = array_map('trim', explode('|', $row['characteristics'] ?? ''));
                foreach ($parts as $p) {
                    if (stripos($p, 'ID/Serial:') === 0) $device['serial'] = trim(substr($p, strlen('ID/Serial:')));
                    elseif (stripos($p, 'MAC:') === 0) $device['mac'] = trim(substr($p, strlen('MAC:')));
                    elseif (stripos($p, 'IP:') === 0) $device['ip'] = trim(substr($p, strlen('IP:')));
                    elseif (stripos($p, 'SIM Card:') === 0) $device['sim'] = trim(substr($p, strlen('SIM Card:')));
                    elseif (stripos($p, 'Location:') === 0) $device['location'] = trim(substr($p, strlen('Location:')));
                    elseif (stripos($p, 'FTP Server:') === 0) $device['ftp_server'] = trim(substr($p, strlen('FTP Server:')));
                    elseif (stripos($p, 'FTP Username:') === 0) $device['ftp_username'] = trim(substr($p, strlen('FTP Username:')));
                    elseif (stripos($p, 'FTP Password:') === 0) $device['ftp_password'] = trim(substr($p, strlen('FTP Password:')));
                    elseif (stripos($p, 'File Format:') === 0) $device['file_format'] = trim(substr($p, strlen('File Format:')));
                }
                $communicationsFromSql[] = $device;
            }
            if (!empty($communicationsFromSql)) $commSource = 'equipment';
        }

        if (!empty($communicationsFromSql)) {
            error_log("[COMM] ✓ Loaded " . count($communicationsFromSql) . " devices from " . $commSource);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        const hidden = document.getElementById("communications_data");
                        if (hidden) hidden.value = ' . json_encode(json_encode($communicationsFromSql)) . ';
                        if (typeof renderCommunicationsTable === "function") {
                            try { communicationsDevices = ' . json_encode($communicationsFromSql) . '; } catch (e) {}
                            renderCommunicationsTable();
                        }
                    } catch (e) { console.warn("[COMM] Could not inject communications into hidden field", e); }
                });
            </script>';
        } else {
            error_log("[COMM] ℹ️ No communications devices found in draft or equipment for report_id={$reportId}");
        }
    } catch (PDOException $e) {
        error_log("[COMM] ❌ Database error: " . $e->getMessage());
    }

    // ============================================
    // LOAD SMART METERS (Telemetry - Meter) - SQL-only in edit mode
    // Prefer report_drafts.form_data -> fallback to report_equipment
    // ============================================
    $telemetryMetersFromSql = [];
    $tmSource = 'none';
    try {
        // 1) Try latest draft first
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;
        $stmtDraftTm = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtDraftTm->execute([$reportId, $draftSessionId]);
        $draftRowTm = $stmtDraftTm->fetch(PDO::FETCH_ASSOC);
        if ($draftRowTm && !empty($draftRowTm['form_data'])) {
            $formTm = json_decode($draftRowTm['form_data'], true);
            if (isset($formTm['telemetry_meter_data']) && is_array($formTm['telemetry_meter_data']) && count($formTm['telemetry_meter_data']) > 0) {
                foreach ($formTm['telemetry_meter_data'] as $m) {
                    if (!is_array($m)) continue;
                    $telemetryMetersFromSql[] = [
                        'mode'        => $m['mode'] ?? '',
                        'brand_id'    => $m['brand_id'] ?? '',
                        'brand_name'  => $m['brand_name'] ?? '',
                        'model_id'    => $m['model_id'] ?? '',
                        'model_name'  => $m['model_name'] ?? '',
                        'serial'      => $m['serial'] ?? '',
                        'ct_ratio'    => $m['ct_ratio'] ?? '',
                        'sim_number'  => $m['sim_number'] ?? '',
                        'location'    => $m['location'] ?? '',
                        'led1'        => $m['led1'] ?? '',
                        'led2'        => $m['led2'] ?? '',
                        'led6'        => $m['led6'] ?? '',
                        'gsm_signal'  => $m['gsm_signal'] ?? ''
                    ];
                }
                if (!empty($telemetryMetersFromSql)) $tmSource = 'draft';
            }
        }

        // 2) Fallback to persisted equipment rows
        if ($tmSource === 'none') {
            $stmtTm = $pdo->prepare("SELECT brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Telemetry - Meter' ORDER BY id");
            $stmtTm->execute([$reportId]);
            $rowsTm = $stmtTm->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsTm as $row) {
                $m = [
                    'mode'        => '',
                    'brand_id'    => '',
                    'brand_name'  => $row['brand'] ?? '',
                    'model_id'    => '',
                    'model_name'  => $row['model'] ?? '',
                    'serial'      => '',
                    'ct_ratio'    => '',
                    'sim_number'  => '',
                    'location'    => '',
                    'led1'        => '',
                    'led2'        => '',
                    'led6'        => '',
                    'gsm_signal'  => ''
                ];
                $parts = array_map('trim', explode('|', $row['characteristics'] ?? ''));
                foreach ($parts as $p) {
                    if (stripos($p, 'Mode:') === 0) $m['mode'] = trim(substr($p, strlen('Mode:')));
                    elseif (stripos($p, 'Serial:') === 0) $m['serial'] = trim(substr($p, strlen('Serial:')));
                    elseif (stripos($p, 'CT Ratio:') === 0) $m['ct_ratio'] = trim(substr($p, strlen('CT Ratio:')));
                    elseif (stripos($p, 'SIM:') === 0) $m['sim_number'] = trim(substr($p, strlen('SIM:')));
                    elseif (stripos($p, 'Location:') === 0) $m['location'] = trim(substr($p, strlen('Location:')));
                    elseif (stripos($p, 'LED1:') === 0) $m['led1'] = trim(substr($p, strlen('LED1:')));
                    elseif (stripos($p, 'LED2:') === 0) $m['led2'] = trim(substr($p, strlen('LED2:')));
                    elseif (stripos($p, 'LED6:') === 0) $m['led6'] = trim(substr($p, strlen('LED6:')));
                    elseif (stripos($p, 'GSM:') === 0) $m['gsm_signal'] = trim(substr($p, strlen('GSM:')));
                }
                $telemetryMetersFromSql[] = $m;
            }
            if (!empty($telemetryMetersFromSql)) $tmSource = 'equipment';
        }

        if (!empty($telemetryMetersFromSql)) {
            error_log("[SMART METER] ✓ Loaded " . count($telemetryMetersFromSql) . " meters from " . $tmSource);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        const hidden = document.getElementById("telemetry_meter_data");
                        if (hidden) hidden.value = ' . json_encode(json_encode($telemetryMetersFromSql)) . ';
                        if (typeof renderTelemetryMetersTable === "function") {
                            try { telemetryMeters = ' . json_encode($telemetryMetersFromSql) . '; } catch (e) {}
                            renderTelemetryMetersTable();
                        }
                    } catch (e) { console.warn("[SMART METER] Could not inject meters into hidden field", e); }
                });
            </script>';
        } else {
            error_log("[SMART METER] ℹ️ No meters found in draft or equipment for report_id={$reportId}");
        }
    } catch (PDOException $e) {
        error_log("[SMART METER] ❌ Database error: " . $e->getMessage());
    }

    // ============================================
    // LOAD ENERGY METERS - SQL-only in edit mode
    // Prefer report_drafts.form_data -> fallback to report_equipment
    // ============================================
    $energyMetersFromSql = [];
    $emSource = 'none';
    try {
        // 1) Try latest draft first
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;
        $stmtDraftEm = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtDraftEm->execute([$reportId, $draftSessionId]);
        $draftRowEm = $stmtDraftEm->fetch(PDO::FETCH_ASSOC);
        if ($draftRowEm && !empty($draftRowEm['form_data'])) {
            $formEm = json_decode($draftRowEm['form_data'], true);
            if (isset($formEm['energy_meter_data']) && is_array($formEm['energy_meter_data']) && count($formEm['energy_meter_data']) > 0) {
                foreach ($formEm['energy_meter_data'] as $m) {
                    if (!is_array($m)) continue;
                    $energyMetersFromSql[] = [
                        'scope'          => $m['scope'] ?? ($m['scope_text'] ?? ''),
                        'scope_text'     => $m['scope_text'] ?? ($m['scope'] ?? ''),
                        'brand_id'       => $m['brand_id'] ?? '',
                        'brand_name'     => $m['brand_name'] ?? '',
                        'model_id'       => $m['model_id'] ?? '',
                        'model_name'     => $m['model_name'] ?? '',
                        'rs485_address'  => $m['rs485_address'] ?? '',
                        'ct_ratio'       => $m['ct_ratio'] ?? ''
                    ];
                }
                if (!empty($energyMetersFromSql)) $emSource = 'draft';
            }
        }

        // 2) Fallback to persisted equipment rows
        if ($emSource === 'none') {
            $stmtEm = $pdo->prepare("SELECT deployment_status, brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Energy Meter' ORDER BY id");
            $stmtEm->execute([$reportId]);
            $rowsEm = $stmtEm->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsEm as $row) {
                $m = [
                    'scope'          => $row['deployment_status'] ?? '',
                    'scope_text'     => $row['deployment_status'] ?? '',
                    'brand_id'       => '',
                    'brand_name'     => $row['brand'] ?? '',
                    'model_id'       => '',
                    'model_name'     => $row['model'] ?? '',
                    'rs485_address'  => '',
                    'ct_ratio'       => ''
                ];
                $parts = array_map('trim', explode('|', $row['characteristics'] ?? ''));
                foreach ($parts as $p) {
                    if (stripos($p, 'RS485 Address:') === 0) $m['rs485_address'] = trim(substr($p, strlen('RS485 Address:')));
                    elseif (stripos($p, 'CT Ratio:') === 0) $m['ct_ratio'] = trim(substr($p, strlen('CT Ratio:')));
                }
                $energyMetersFromSql[] = $m;
            }
            if (!empty($energyMetersFromSql)) $emSource = 'equipment';
        }

        if (!empty($energyMetersFromSql)) {
            error_log("[ENERGY METER] ✓ Loaded " . count($energyMetersFromSql) . " meters from " . $emSource);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        const hidden = document.getElementById("energy_meter_data");
                        if (hidden) hidden.value = ' . json_encode(json_encode($energyMetersFromSql)) . ';
                        if (typeof updateEnergyMetersTable === "function") {
                            try { energyMetersList = ' . json_encode($energyMetersFromSql) . '; } catch (e) {}
                            updateEnergyMetersTable();
                        }
                    } catch (e) { console.warn("[ENERGY METER] Could not inject energy meters into hidden field", e); }
                });
            </script>';
        } else {
            error_log("[ENERGY METER] ℹ️ No energy meters found in draft or equipment for report_id={$reportId}");
        }
    } catch (PDOException $e) {
        error_log("[ENERGY METER] ❌ Database error: " . $e->getMessage());
    }
    // ============================================
    // LOAD PUNCH LIST (SQL-only in edit mode)
    // Prefer report_drafts.form_data -> fallback to report_equipment ('Punch List Item')
    // ============================================
    $punchListFromSql = [];
    $plSource = 'none';
    try {
        // 1) Try latest draft first
        error_log("[PUNCH] 📥 Loading punch list for report_id={$reportId}");
        $draftSessionId = $_SESSION['draft_session_id'] ?? null;
        $stmtDraftPl = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmtDraftPl->execute([$reportId, $draftSessionId]);
        $draftRowPl = $stmtDraftPl->fetch(PDO::FETCH_ASSOC);
        if ($draftRowPl && !empty($draftRowPl['form_data'])) {
            $formPl = json_decode($draftRowPl['form_data'], true);
            if (isset($formPl['punch_list_data']) && is_array($formPl['punch_list_data']) && count($formPl['punch_list_data']) > 0) {
                foreach ($formPl['punch_list_data'] as $it) {
                    if (!is_array($it)) continue;
                    $punchListFromSql[] = [
                        'id'              => $it['id'] ?? '',
                        'severity'        => $it['severity'] ?? '',
                        'description'     => $it['description'] ?? '',
                        'opening_date'    => $it['opening_date'] ?? '',
                        'responsible'     => $it['responsible'] ?? '',
                        'resolution_date' => $it['resolution_date'] ?? ''
                    ];
                }
                if (!empty($punchListFromSql)) $plSource = 'draft';
            }
        }

        // 2) Fallback to persisted equipment rows
        if ($plSource === 'none') {
            $stmtPl = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Punch List Item' ORDER BY id");
            $stmtPl->execute([$reportId]);
            $rowsPl = $stmtPl->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsPl as $row) {
                $item = [
                    'id'              => '',
                    'severity'        => '',
                    'description'     => '',
                    'opening_date'    => '',
                    'responsible'     => '',
                    'resolution_date' => ''
                ];
                $parts = array_map('trim', explode('|', $row['characteristics'] ?? ''));
                foreach ($parts as $p) {
                    if (stripos($p, 'ID:') === 0) $item['id'] = trim(substr($p, strlen('ID:')));
                    elseif (stripos($p, 'Severity:') === 0) $item['severity'] = trim(substr($p, strlen('Severity:')));
                    elseif (stripos($p, 'Description:') === 0) $item['description'] = trim(substr($p, strlen('Description:')));
                    elseif (stripos($p, 'Opening Date:') === 0) $item['opening_date'] = trim(substr($p, strlen('Opening Date:')));
                    elseif (stripos($p, 'Responsible:') === 0) $item['responsible'] = trim(substr($p, strlen('Responsible:')));
                    elseif (stripos($p, 'Resolution Date:') === 0) $item['resolution_date'] = trim(substr($p, strlen('Resolution Date:')));
                }
                $punchListFromSql[] = $item;
            }
            if (!empty($punchListFromSql)) $plSource = 'equipment';
        }

        if (!empty($punchListFromSql)) {
            error_log("[PUNCH] ✓ Loaded " . count($punchListFromSql) . " items from " . $plSource);
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        window.existingPunchList = ' . json_encode($punchListFromSql) . ';
                        const hidden = document.getElementById("punch_list_data");
                        if (hidden) hidden.value = ' . json_encode(json_encode($punchListFromSql)) . ';
                        if (typeof renderPunchListTable === "function") {
                            try { punchListItems = window.existingPunchList; } catch (e) {}
                            renderPunchListTable();
                        }
                    } catch (e) { console.warn("[PUNCH] Could not inject items into hidden field", e); }
                });
            </script>';
        } else {
            error_log("[PUNCH] ℹ️ No punch list items found in draft or equipment for report_id={$reportId}");
        }
    } catch (PDOException $e) {
        error_log("[PUNCH] ❌ Database error: " . $e->getMessage());
    }
} else {
    // NEW REPORT - Signal to JavaScript
    echo '<script>
        // Flag to indicate this is a new report (not loading from database)
        window.IS_NEW_REPORT = true;
        window.LOAD_PROTECTION_FROM_SQL = false;
        window.EDIT_MODE_SKIP_LOCALSTORAGE = false; // Allow autosave and loading from draft
        console.log("[New Report] ✅ New report mode - draft autosave enabled");
    </script>';
}
?>
<?php if ($reportId && $reportData): ?>
    <!-- Global Loading Overlay: only shown in Edit Mode -->
    <div id="global-loading-overlay" class="show">
        <div class="loading-spinner" aria-hidden="true"></div>
        <div class="loading-title">Loading report data…</div>
        <div class="loading-subtitle">Please wait. We are preparing the form with data from SQL.</div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['tab']) && $_GET['tab'] === 'punch-list'): ?>
    <script>
        // Focus Punch List tab when requested via query param
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const btn = document.getElementById('punch-list-tab');
                if (btn) {
                    try {
                        btn.click();
                    } catch (_) {}
                } else if (typeof window.switchTab === 'function') {
                    const el = document.getElementById('punch-list-tab');
                    if (el) window.switchTab(el);
                }
            }, 200);
        });
    </script>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <?php if ($reportId && $reportData): ?>
            <!-- Edit Mode Banner -->
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-edit fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-1">
                            <i class="fas fa-pen-to-square me-2"></i>Editing Report
                        </h5>
                        <p class="mb-0">
                            <strong>Report ID:</strong> COM-<?php echo str_pad($reportId, 5, '0', STR_PAD_LEFT); ?> |
                            <strong>Project:</strong> <?php echo htmlspecialchars($reportData['project_name'] ?? 'Untitled'); ?> |
                            <strong>Date:</strong> <?php echo date('Y-m-d', strtotime($reportData['date'] ?? 'now')); ?>
                        </p>
                    </div>
                    <div>
                        <a href="generate_report.php?id=<?php echo $reportId; ?>" class="btn btn-sm btn-primary me-2" target="_blank">
                            <i class="fas fa-file-alt me-1"></i>View Report
                        </a>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">
                <i class="fas fa-solar-panel me-2"></i>
                <?php echo $reportId ? 'Edit Commissioning Report' : 'PV System Commissioning'; ?>
            </h1>

            <div>
                <a href="commissioning_dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>Commissioning Form
                </h2>
                <span class="badge bg-primary">Auto-saving</span>
            </div>

            <?php if (isset($_SESSION['report_view_only']) && $_SESSION['report_view_only']): ?>
                <div class="alert alert-warning mb-0" style="border-radius: 0;">
                    <i class="fas fa-eye me-2"></i>
                    <strong>View Only Mode:</strong> You can view this report but cannot edit it. Only the creator or an administrator can edit this report.
                </div>
            <?php endif; ?>

            <div class="card-body">
                <!-- Main Form -->
                <form id="commissioningForm" data-autosave="main-form" method="post" action="save_report.php" onsubmit="handleFormSubmit(event)">
                    <?php if ($reportId): ?>
                        <input type="hidden" name="report_id" value="<?php echo $reportId; ?>">
                    <?php endif; ?>

                    <?php
                    // If EDIT MODE: Inject protection data directly into page as JavaScript variable
                    if ($reportId && !empty($protectionData)) {
                        $protectionJson = json_encode($protectionData);
                        echo '<script>
                            // DEBUG
                            console.log("[COMISSIONAMENTO] Setting window.existingProtection with ' . count($protectionData) . ' items");
                            window.existingProtection = ' . $protectionJson . ';
                            console.log("[COMISSIONAMENTO] window.existingProtection:", window.existingProtection);
                        </script>';
                    }
                    ?>

                    <?php
                    // If in view-only mode, disable form fields
                    if (isset($_SESSION['report_view_only']) && $_SESSION['report_view_only']) {
                        echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                // Disable all form inputs
                                const form = document.getElementById("commissioningForm");
                                if (form) {
                                    const inputs = form.querySelectorAll("input, select, textarea, button[type=submit]");
                                    inputs.forEach(input => {
                                        if (input.type === "submit") {
                                            input.style.display = "none"; // Hide submit button
                                        } else {
                                            input.disabled = true;
                                            input.style.opacity = "0.6";
                                        }
                                    });
                                    
                                    // Disable all tab buttons
                                    const tabButtons = form.querySelectorAll(".nav-link");
                                    tabButtons.forEach(btn => btn.style.pointerEvents = "none");
                                }
                            });
                        </script>';
                    }
                    ?> <!-- GLOBAL TAB SWITCH FUNCTION - NO BOOTSTRAP DEPENDENCY -->
                    <script>
                        // Global function to switch tabs WITHOUT Bootstrap dependency
                        window.switchTab = function(buttonElement) {
                            console.log('🔥 switchTab called for:', buttonElement.id);

                            try {
                                // Get target pane ID from data-bs-target
                                const targetId = buttonElement.getAttribute('data-bs-target');
                                console.log('🎯 Target pane:', targetId);

                                // Remove active class from all tab buttons
                                document.querySelectorAll('.nav-link').forEach(btn => {
                                    btn.classList.remove('active');
                                    btn.setAttribute('aria-selected', 'false');
                                });

                                // Add active class to clicked button
                                buttonElement.classList.add('active');
                                buttonElement.setAttribute('aria-selected', 'true');

                                // Hide all tab panes
                                document.querySelectorAll('.tab-pane').forEach(pane => {
                                    pane.classList.remove('show', 'active');
                                });

                                // Show target pane
                                const targetPane = document.querySelector(targetId);
                                if (targetPane) {
                                    targetPane.classList.add('show', 'active');
                                    console.log('✅ Tab switched successfully:', buttonElement.id);
                                } else {
                                    console.error('❌ Target pane not found:', targetId);
                                }

                                return false;
                            } catch (error) {
                                console.error('❌ Error switching tab:', error);
                                return false;
                            }
                        };
                        console.log('✅ switchTab function loaded (NO BOOTSTRAP DEPENDENCY)');
                    </script>

                    <!-- Form Navigation Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-4" id="formTabs" role="tablist" style="display: flex !important;">
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true"
                                onclick="return switchTab(this);">
                                <i class="fas fa-info-circle me-1"></i> General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="equipment-tab" data-bs-toggle="tab" data-bs-target="#equipment" type="button" role="tab" aria-controls="equipment" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-tools me-1"></i> Equipment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="protection-tab" data-bs-toggle="tab" data-bs-target="#protection" type="button" role="tab" aria-controls="protection" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-shield-alt me-1"></i> Protection
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="strings-tab" data-bs-toggle="tab" data-bs-target="#strings" type="button" role="tab" aria-controls="strings" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-plug me-1"></i> Strings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="telemetry-tab" data-bs-toggle="tab" data-bs-target="#telemetry" type="button" role="tab" aria-controls="telemetry" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-satellite-dish me-1"></i> Telemetry
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="punch-list-tab" data-bs-toggle="tab" data-bs-target="#punch-list" type="button" role="tab" aria-controls="punch-list" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-clipboard-list me-1"></i> Punch List
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="display: inline-block !important;">
                            <button class="nav-link" id="finish-tab" data-bs-toggle="tab" data-bs-target="#finish" type="button" role="tab" aria-controls="finish" aria-selected="false"
                                onclick="return switchTab(this);">
                                <i class="fas fa-check-circle me-1"></i> Finish
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="formTabContent">
                        <!-- General Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <h3 class="section-heading">Project Information</h3>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="project_name" class="form-label">Project Name</label>
                                    <input type="text" class="form-control" id="project_name" name="project_name"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['project_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['date']) : date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="plant_location" class="form-label">Plant Location</label>
                                    <input type="text" class="form-control" id="plant_location" name="plant_location"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['plant_location']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="commissioning_responsible_id" class="form-label">Commissioning Responsible</label>
                                    <div class="input-group">
                                        <select class="form-select" id="commissioning_responsible_id" name="commissioning_responsible_id"
                                            <?php if ($reportData && !empty($reportData['commissioning_responsible_id'])): ?>
                                            data-selected="<?php echo htmlspecialchars($reportData['commissioning_responsible_id']); ?>"
                                            <?php endif; ?>>
                                            <option value="">Select Responsible...</option>
                                            <!-- Options will be loaded from database via AJAX -->
                                        </select>
                                        <button class="btn btn-outline-success" type="button" id="add-commissioning-responsible-btn" title="Add New Commissioning Responsible">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="gps" class="form-label">GPS Coordinates</label>
                                    <input type="text" class="form-control" id="gps" name="gps" placeholder="Lat, Long"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['gps']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="cpe" class="form-label">CPE</label>
                                    <input type="text" class="form-control" id="cpe" name="cpe"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['cpe']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Interactive Map for Location Reference -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end mb-2">
                                        <button type="button" id="mppt-global-toggle" class="btn btn-outline-secondary btn-sm mppt-global-toggle" aria-pressed="false" aria-label="Toggle MPPT card view" title="Toggle MPPT Card View" role="switch">
                                            <i class="fas fa-th-large"></i>
                                            <span class="ms-1 d-none d-sm-inline">Table</span>
                                        </button>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><i class="fas fa-map-marker-alt me-1"></i> Location Map</strong>
                                            <small class="text-muted">Click to set GPS or type coordinates above</small>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" id="comm_map_toggle_sat">Satellite</button>
                                            <button class="btn btn-sm btn-outline-danger" type="button" id="comm_map_clear">Clear</button>
                                        </div>
                                    </div>
                                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                                    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
                                    <div id="comm_map" style="width:100%;height:300px;border:1px solid #ddd;border-radius:6px"></div>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Selected GPS</label>
                                            <input type="text" id="comm_map_gps" class="form-control" placeholder="Lat, Lon" value="<?php echo $reportData ? htmlspecialchars($reportData['gps'] ?? '') : ''; ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Area (m²)</label>
                                            <input type="text" id="comm_map_area_m2" name="map_area_m2" class="form-control" value="<?php echo $reportData['map_area_m2'] ?? ''; ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Azimuth (°)</label>
                                            <input type="text" id="comm_map_azimuth_deg" name="map_azimuth_deg" class="form-control" value="<?php echo $reportData['map_azimuth_deg'] ?? ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <input type="hidden" id="comm_existing_polygon_coords" name="map_polygon_coords" value="<?php echo htmlspecialchars($reportData['map_polygon_coords'] ?? ''); ?>">
                                </div>
                            </div>

                            <h3 class="section-heading mt-4">Power Information</h3>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="installed_power" class="form-label">Installed Power (kWp)</label>
                                    <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" class="form-control" id="installed_power" name="installed_power"
                                        value="<?php echo $reportData && isset($reportData['installed_power']) ? $reportData['installed_power'] : ''; ?>" readonly>
                                    <small class="text-muted">Auto-filled when adding panels in Equipment tab</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="total_power" class="form-label">Total Power (kWp)</label>
                                    <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" class="form-control" id="total_power" name="total_power"
                                        value="<?php echo $reportData && isset($reportData['total_power']) ? $reportData['total_power'] : ''; ?>" readonly>
                                    <small class="text-muted">Auto-filled when adding panels in Equipment tab</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="certified_power" class="form-label">Certified Power (kWp)</label>
                                    <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" class="form-control" id="certified_power" name="certified_power"
                                        value="<?php echo $reportData && isset($reportData['certified_power']) ? $reportData['certified_power'] : ''; ?>">
                                </div>
                            </div>

                            <h3 class="section-heading mt-4">Responsible Parties</h3>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="epc_id" class="form-label">EPC Company</label>
                                    <div class="input-group">
                                        <select class="form-select" id="epc_id" name="epc_id"
                                            <?php if ($reportData && !empty($reportData['epc_id'])): ?>
                                            data-selected="<?php echo htmlspecialchars($reportData['epc_id']); ?>"
                                            <?php endif; ?>>
                                            <option value="">Select EPC...</option>
                                            <!-- Options will be loaded from database via AJAX -->
                                        </select>
                                        <button class="btn btn-outline-success" type="button" id="add-epc-btn" title="Add New EPC Company">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="representative_id" class="form-label">Representative</label>
                                    <div class="input-group">
                                        <select class="form-select" id="representative_id" name="representative_id"
                                            <?php if ($reportData && !empty($reportData['representative_id'])): ?>
                                            data-selected="<?php echo htmlspecialchars($reportData['representative_id']); ?>"
                                            <?php endif; ?>>
                                            <option value="">Select Representative...</option>
                                            <!-- Options will be loaded based on selected EPC -->
                                        </select>
                                        <button class="btn btn-outline-success" type="button" id="add-representative-btn" title="Add New Representative">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="responsible" class="form-label">Responsible Person</label>
                                    <input type="text" class="form-control" id="responsible" name="responsible"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['responsible']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="technician" class="form-label">Technician</label>
                                    <input type="text" class="form-control" id="technician" name="technician"
                                        value="<?php echo $reportData ? htmlspecialchars($reportData['technician']) : ''; ?>">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <div></div>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('equipment-tab').click()">
                                    Next: Equipment <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Protection Tab -->
                        <div class="tab-pane fade" id="protection" role="tabpanel" aria-labelledby="protection-tab">
                            <h3 class="section-heading">Protection</h3>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-bolt me-1 text-primary"></i> Circuit Protection</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label for="protection_scope" class="form-label">Scope</label>
                                            <select id="protection_scope" class="form-select">
                                                <option value="injection">Main Switchboard</option>
                                                <option value="sub_switchboard">Sub Switchboard</option>
                                                <option value="pv_board">PV Board</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="protection_circuit_brand" class="form-label">Circuit Breaker Brand</label>
                                            <div class="input-group">
                                                <select id="protection_circuit_brand" class="form-select">
                                                    <option value="">Select Brand...</option>
                                                </select>
                                                <button class="btn btn-outline-success" type="button" id="add-protection-cb-brand-btn" title="Add New Brand">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="protection_circuit_model" class="form-label">Circuit Breaker Model</label>
                                            <div class="input-group">
                                                <select id="protection_circuit_model" class="form-select">
                                                    <option value="">Select Model...</option>
                                                </select>
                                                <button class="btn btn-outline-success" type="button" id="add-protection-cb-model-btn" title="Add New Model">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="protection_circuit_rated_current" class="form-label">Rated Current (A)</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" id="protection_circuit_rated_current" class="form-control" placeholder="e.g., 16" data-skip-autosave="true">
                                        </div>
                                        <div class="col-md-1 d-grid">
                                            <button type="button" id="add-protection-row-btn" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Add
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-bordered table-hover" id="protection-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Scope</th>
                                                    <th>Brand</th>
                                                    <th>Model</th>
                                                    <th>Rated Current (A)</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="protection-table-body">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted"><em>No protection items added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="protection_data" name="protection_data" value="[]">
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-magnet me-1 text-success"></i> Homopolar Protection</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="homopolar_installer" class="form-label">Installer</label>
                                            <input type="text" id="homopolar_installer" name="homopolar_installer" class="form-control" placeholder="Company/Technician">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="homopolar_brand" class="form-label">Brand</label>
                                            <input type="text" id="homopolar_brand" name="homopolar_brand" class="form-control" placeholder="Brand">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="homopolar_model" class="form-label">Model</label>
                                            <input type="text" id="homopolar_model" name="homopolar_model" class="form-control" placeholder="Model">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-network-wired me-1 text-warning"></i> Cable (PV Board / Point of Injection)</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-12">
                                            <div class="text-muted small"><i class="fas fa-info-circle me-1"></i> Cable from PV Board to Point of Injection</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="protection_cable_brand" class="form-label">Cable Brand</label>
                                            <select id="protection_cable_brand" class="form-select">
                                                <option value="">Select Brand...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="protection_cable_model" class="form-label">Cable Model</label>
                                            <input type="text" id="protection_cable_model" class="form-control" placeholder="e.g., 4x10mm² Cu/XLPE">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="protection_cable_size" class="form-label">Size (mm²)</label>
                                            <input type="text" id="protection_cable_size" class="form-control" placeholder="e.g., 6">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="protection_cable_insulation" class="form-label">Cable Insulation</label>
                                            <input type="text" id="protection_cable_insulation" class="form-control" placeholder="e.g., XLPE">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success" id="add-protection-cable-btn">
                                                <i class="fas fa-plus me-1"></i> Add Cable
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dynamic protection cables list -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered table-hover" id="protection-cables-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Brand</th>
                                                    <th>Model</th>
                                                    <th>Size (mm²)</th>
                                                    <th>Insulation</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="protection-cables-tbody">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted"><em>No cables added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="protection_cable_data" name="protection_cable_data" value="[]">
                                </div>
                            </div>

                            <!-- Amperometric Clamp Measurements Section -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-tachometer-alt me-1 text-info"></i> Amperometric Clamp Measurements</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label for="clamp_equipment" class="form-label">Equipment</label>
                                            <select id="clamp_equipment" class="form-select">
                                                <option value="">Select...</option>
                                                <option value="Grid meter">Grid meter</option>
                                                <option value="PV meter">PV meter</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="clamp_l1" class="form-label">L1 (A)</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" id="clamp_l1" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="clamp_l2" class="form-label">L2 (A)</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" id="clamp_l2" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="clamp_l3" class="form-label">L3 (A)</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" id="clamp_l3" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="clamp_match_meter" class="form-label">Match with meter?</label>
                                            <select id="clamp_match_meter" class="form-select">
                                                <option value="yes">Yes</option>
                                                <option value="no">No</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-success w-100" id="add-clamp-measurement-btn" title="Add Clamp Measurement">
                                                <i class="fas fa-plus me-1"></i> Add Measurement
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dynamic clamp measurements list -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered table-hover" id="clamp-measurements-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Equipment</th>
                                                    <th>L1 (A)</th>
                                                    <th>L2 (A)</th>
                                                    <th>L3 (A)</th>
                                                    <th>Match with meter?</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="clamp-measurements-tbody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted"><em>No measurements added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="clamp_measurements_data" name="clamp_measurements_data" value="[]">
                                </div>
                            </div>

                            <!-- Earth Protection Circuit Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Earth Protection Circuit</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="earth_resistance" class="form-label">Resistance (Ω)</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" id="earth_resistance" name="earth_resistance" class="form-control" placeholder="0.00">
                                            <div id="earth-resistance-warning" class="text-danger mt-1" style="display: none;">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <small>Resistance above 10Ω - Earth reinforcement is recommended</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Regulatory Information -->
                                    <div class="alert alert-info mt-3" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Regulatory Information:</strong><br>
                                        In Portugal, the acceptable earth values in an electrical installation are regulated by the Safety Regulations for Low Voltage Electrical Installations (RSIEBT).<br>
                                        According to this regulation, the maximum permissible value for earth resistance is <strong>100 Ω</strong> for installations with a nominal current of less than 32 A and <strong>10 Ω</strong> for installations with a nominal current of more than 32 A.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('equipment-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: Equipment
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('strings-tab').click()">
                                    Next: Strings <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Equipment Tab -->
                        <div class="tab-pane fade" id="equipment" role="tabpanel" aria-labelledby="equipment-tab">
                            <h3 class="section-heading">PV Modules</h3>

                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Module Details</h5>
                                    <button type="button" id="add-module-btn" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> Add Module
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <label for="new_module_brand" class="form-label">Brand</label>
                                            <div class="input-group">
                                                <select class="form-select" id="new_module_brand">
                                                    <option value="">Select Brand...</option>
                                                </select>
                                                <button class="btn btn-outline-success" type="button" id="add-module-brand-btn" title="Add New Brand">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="new_module_model" class="form-label">Model</label>
                                            <div class="input-group">
                                                <select class="form-select" id="new_module_model">
                                                    <option value="">Select Model...</option>
                                                    <!-- Options will be loaded based on selected brand -->
                                                </select>
                                                <button class="btn btn-outline-success" type="button" id="add-module-model-btn" title="Add New Model">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="new_module_power" class="form-label">Power (W)</label>
                                            <select class="form-select" id="new_module_power">
                                                <option value="">Select Power...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="new_module_quantity" class="form-label">Quantity</label>
                                            <input type="number" inputmode="decimal" pattern="[0-9]*" autocomplete="off" class="form-control" id="new_module_quantity" value="1" min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="new_module_status" class="form-label">Status</label>
                                            <select class="form-select" id="new_module_status">
                                                <option value="new">New</option>
                                                <option value="existing">Existing</option>
                                                <option value="replacement">Replacement</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="modules-table" class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Brand</th>
                                                    <th>Model</th>
                                                    <th>Power (W)</th>
                                                    <th>Quantity</th>
                                                    <th>Status</th>
                                                    <th>Datasheet</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modules-table-body">
                                                <!-- Table rows will be dynamically added here -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Hidden input to store modules data for form submission -->
                                    <input type="hidden" id="modules_data" name="modules_data" value="">

                                    <?php
                                    // Load existing modules if editing a report
                                    if (isset($reportData['id'])) {
                                        $reportId = $reportData['id'];
                                        echo '<script>console.log("[PHP DEBUG] Report ID: ' . $reportId . '");</script>';

                                        $modulesStmt = $pdo->prepare("
                                            SELECT 
                                                re.brand, 
                                                re.model, 
                                                re.quantity, 
                                                re.deployment_status as status, 
                                                re.characteristics,
                                                re.power_rating,
                                                pmm.power_options,
                                                pmm.id as model_id,
                                                pmb.id as brand_id
                                            FROM report_equipment re
                                            LEFT JOIN pv_module_models pmm ON re.model = pmm.model_name
                                            LEFT JOIN pv_module_brands pmb ON re.brand = pmb.brand_name
                                            WHERE re.report_id = ? AND re.equipment_type = 'PV Module'
                                        ");
                                        $modulesStmt->execute([$reportId]);
                                        $existingModules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

                                        echo '<script>console.log("[PHP DEBUG] Found ' . count($existingModules) . ' PV modules");</script>';

                                        if (!empty($existingModules)) {
                                            // Extract serial numbers and set power rating from power_options
                                            foreach ($existingModules as &$module) {
                                                // DEBUG: Show what's in characteristics and power_options
                                                echo '<script>console.log("[PHP DEBUG] characteristics field:", ' . json_encode($module['characteristics']) . ');</script>';
                                                echo '<script>console.log("[PHP DEBUG] power_options field:", ' . json_encode($module['power_options']) . ');</script>';

                                                // Extract serial number from characteristics
                                                preg_match('/Serial: ([^|]+)/', $module['characteristics'], $matches);
                                                $module['serial_number'] = isset($matches[1]) ? trim($matches[1]) : '';

                                                // Set power rating: use saved value from report_equipment if available
                                                // Otherwise fallback to first value from power_options
                                                if (!empty($module['power_rating']) && intval($module['power_rating']) > 0) {
                                                    $module['power_rating'] = intval($module['power_rating']);
                                                } else {
                                                    $powerOptions = explode(',', $module['power_options']);
                                                    $module['power_rating'] = isset($powerOptions[0]) ? intval(trim($powerOptions[0])) : 0;
                                                }

                                                echo '<script>console.log("[PHP DEBUG] Final power_rating:", ' . $module['power_rating'] . ');</script>';
                                            }

                                            echo '<script>';
                                            echo 'console.log("[PHP] Loading modules data IMMEDIATELY");';
                                            echo 'window.existingModules = ' . json_encode($existingModules) . ';';
                                            echo 'console.log("[PHP] existingModules:", window.existingModules);';
                                            // Attempt to restore dropdown selections after DOM is ready
                                            echo 'document.addEventListener("DOMContentLoaded", function() {';
                                            echo '    if (typeof window.restoreAllPendingSelections === "function") { setTimeout(window.restoreAllPendingSelections, 150); }';
                                            echo '});';
                                            echo '</script>';
                                        } else {
                                            echo '<script>console.warn("[PHP DEBUG] No PV modules found for report ID ' . $reportId . '");</script>';
                                        }
                                    } else {
                                        echo '<script>console.warn("[PHP DEBUG] reportData[\'id\'] not set - probably creating new report");</script>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                            // Load existing protection data if editing a report
                            // If ANY draft exists for this report (even if missing 'protection_data'), do NOT override with SQL
                            if (isset($reportData['id']) && empty($hasAnyDraftForReport)) {
                                $reportId = $reportData['id'];
                                $protStmt = $pdo->prepare("SELECT brand, model, quantity, rated_current, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Protection - Circuit Breaker'");
                                $protStmt->execute([$reportId]);
                                $existingProtection = $protStmt->fetchAll(PDO::FETCH_ASSOC);

                                $homStmt = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Homopolar Protection' LIMIT 1");
                                $homStmt->execute([$reportId]);
                                $homRow = $homStmt->fetch(PDO::FETCH_ASSOC);

                                // Load protection cables
                                $protCableStmt = $pdo->prepare("SELECT brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Protection - Cable'");
                                $protCableStmt->execute([$reportId]);
                                $existingProtectionCables = $protCableStmt->fetchAll(PDO::FETCH_ASSOC);

                                // Load clamp measurements
                                $clampStmt = $pdo->prepare("SELECT brand, model, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Amperometric Clamp'");
                                $clampStmt->execute([$reportId]);
                                $existingClampMeasurements = $clampStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($existingProtection) || $homRow || !empty($existingProtectionCables) || !empty($existingClampMeasurements)) {
                                    echo '<script>';
                                    echo 'console.log("[PHP] Loading protection data IMMEDIATELY (no DOMContentLoaded)");';

                                    // 🔥 CARREGAR protection_cable_data DO RASCUNHO
                                    $protectionCableDataFromDraft = [];
                                    $clampDataFromDraft = [];
                                    $stmt_draft = $pdo->prepare("SELECT form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1");
                                    $stmt_draft->execute([$reportId]);
                                    $draft_row = $stmt_draft->fetch();
                                    if ($draft_row) {
                                        $draftFormData = json_decode($draft_row['form_data'], true);
                                        if (is_array($draftFormData)) {
                                            $protectionCableDataFromDraft = $draftFormData['protection_cable_data'] ?? [];
                                            $clampDataFromDraft = $draftFormData['clamp_measurements_data'] ?? [];
                                            error_log("[PROTECTION CABLE] 📥 Loaded from draft: " . count($protectionCableDataFromDraft) . " cables");
                                            error_log("[CLAMP] 📥 Loaded from draft: " . count($clampDataFromDraft) . " clamps");
                                        }
                                    }

                                    // Se tem dados do rascunho, usar; senão, usar dados salvos
                                    if (empty($existingProtectionCables) && !empty($protectionCableDataFromDraft)) {
                                        $existingProtectionCables = $protectionCableDataFromDraft;
                                        error_log("[PROTECTION CABLE] ✅ Using draft data instead of report_equipment");
                                    }
                                    if (empty($existingClampMeasurements) && !empty($clampDataFromDraft)) {
                                        $existingClampMeasurements = $clampDataFromDraft;
                                        error_log("[CLAMP] ✅ Using draft data instead of report_equipment");
                                    }

                                    // Map protection items into a slimmer structure; try to extract Rated Current
                                    $protMapped = [];
                                    foreach ($existingProtection as $row) {
                                        $rated = (isset($row['rated_current']) && $row['rated_current'] !== '') ? (float)$row['rated_current'] : null;
                                        if (!empty($row['characteristics'])) {
                                            if ($rated === null) {
                                                $chars = $row['characteristics'];
                                                // Robust patterns for Rated Current (fallback only if column is null)
                                                $patterns = [
                                                    '/Rated\s*Current\s*\(A\)?:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i',
                                                    '/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i',
                                                    '/\bIn\b\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i'
                                                ];
                                                foreach ($patterns as $rx) {
                                                    if (preg_match($rx, $chars, $m)) {
                                                        $rated = (float)str_replace(',', '.', $m[1]);
                                                        break;
                                                    }
                                                }
                                            }
                                            $scopeText = '';
                                            if (preg_match('/Scope:\s*([^|]+)/i', $row['characteristics'], $s)) {
                                                $scopeText = trim($s[1]);
                                            }
                                        } else {
                                            $scopeText = '';
                                        }
                                        $protMapped[] = [
                                            'scope' => '',
                                            'scope_text' => $scopeText,
                                            'brand_name' => $row['brand'],
                                            'model_name' => $row['model'],
                                            'quantity' => (int)$row['quantity'],
                                            'rated_current' => $rated,
                                            'characteristics' => $row['characteristics']
                                        ];
                                    }
                                    echo 'window.existingProtection = ' . json_encode($protMapped) . ';';
                                    // Map protection cables
                                    $cableMapped = [];
                                    foreach ($existingProtectionCables as $row) {
                                        $scopeText = '';
                                        $size = '';
                                        $ins = '';
                                        if (!empty($row['characteristics'])) {
                                            if (preg_match('/Scope:\\s*([^|]+)/i', $row['characteristics'], $s)) {
                                                $scopeText = trim($s[1]);
                                            }
                                            if (preg_match('/Size:\\s*([^|]+)/i', $row['characteristics'], $m)) {
                                                $size = trim($m[1]);
                                            }
                                            if (preg_match('/Insulation:\\s*([^|]+)/i', $row['characteristics'], $n)) {
                                                $ins = trim($n[1]);
                                            }
                                        }
                                        $cableMapped[] = [
                                            'scope' => '',
                                            'scope_text' => $scopeText,
                                            'brand_name' => $row['brand'],
                                            'model_name' => $row['model'],
                                            'size' => $size,
                                            'insulation' => $ins
                                        ];
                                    }
                                    echo 'window.existingProtectionCables = ' . json_encode($cableMapped) . ';';
                                    // Map clamp measurements
                                    $clampMapped = [];
                                    foreach ($existingClampMeasurements as $row) {
                                        $equipment = '';
                                        $l1 = '';
                                        $l2 = '';
                                        $l3 = '';
                                        $matchMeter = '';
                                        if (!empty($row['characteristics'])) {
                                            if (preg_match('/Equipment:\\s*([^|]+)/i', $row['characteristics'], $e)) {
                                                $equipment = trim($e[1]);
                                            }
                                            if (preg_match('/L1 Current:\\s*([0-9]+(?:\\.[0-9]+)?)A/i', $row['characteristics'], $l)) {
                                                $l1 = trim($l[1]);
                                            }
                                            if (preg_match('/L2 Current:\\s*([0-9]+(?:\\.[0-9]+)?)A/i', $row['characteristics'], $l)) {
                                                $l2 = trim($l[1]);
                                            }
                                            if (preg_match('/L3 Current:\\s*([0-9]+(?:\\.[0-9]+)?)A/i', $row['characteristics'], $l)) {
                                                $l3 = trim($l[1]);
                                            }
                                            if (preg_match('/Matches with meter:\\s*([^|]+)/i', $row['characteristics'], $m)) {
                                                $matchMeter = trim($m[1]);
                                            }
                                        }
                                        $clampMapped[] = [
                                            'equipment' => $equipment,
                                            'l1' => $l1,
                                            'l2' => $l2,
                                            'l3' => $l3,
                                            'match_meter' => $matchMeter
                                        ];
                                    }
                                    echo 'window.existingClampMeasurements = ' . json_encode($clampMapped) . ';';

                                    // 🔥 RESTAURAR TABELAS DO RASCUNHO APÓS DELAY (somente CABLES; CLAMP já é carregado pelo main.js)
                                    echo 'console.log("[RESTORE] Agendando restauração...");';
                                    echo 'setTimeout(function() {';
                                    echo '  console.log("[RESTORE] 🚀 Iniciando restauração...");';
                                    echo '  if (window.existingProtectionCables && Array.isArray(window.existingProtectionCables) && window.existingProtectionCables.length > 0) {';
                                    echo '    console.log("[RESTORE] 📦 Restaurando " + window.existingProtectionCables.length + " protection cables");';
                                    echo '    for (let cable of window.existingProtectionCables) {';
                                    echo '      try {';
                                    echo '        document.getElementById("protection_cable_brand").value = cable.brand_id || "";';
                                    echo '        document.getElementById("protection_cable_model").value = cable.model_name || cable.model || "";';
                                    echo '        document.getElementById("protection_cable_size").value = cable.size || "";';
                                    echo '        document.getElementById("protection_cable_insulation").value = cable.insulation || "";';
                                    echo '        const btn = document.getElementById("add-protection-cable-btn");';
                                    echo '        if (btn) { btn.click(); console.log("[RESTORE] ✅ Cable restaurado"); }';
                                    echo '      } catch (e) { console.error("[RESTORE] ❌ Cable error:", e); }';
                                    echo '    }';
                                    echo '  }';
                                    // Clamp measurements are rendered by main.js from window.existingClampMeasurements; avoid double-adding here
                                    echo '  console.log("[RESTORE] ✅ Restauração finalizada!");';
                                    echo '}, 1000);';

                                    // Initialize protection_data hidden field with existing protection items
                                    echo 'document.addEventListener("DOMContentLoaded", function() {';
                                    echo '  try {';
                                    echo '    const protectionField = document.getElementById("protection_data");';
                                    echo '    if (protectionField) {';
                                    echo '      protectionField.value = ' . json_encode(json_encode($protMapped)) . ';';
                                    echo '      console.log("[JS] Initialized protection_data with ' . count($protMapped) . ' items");';
                                    echo '    }';
                                    echo '  } catch (e) { console.warn("Failed to initialize protection_data", e); }';
                                    echo '});';

                                    if ($homRow && !empty($homRow['characteristics'])) {
                                        echo 'document.addEventListener("DOMContentLoaded", function() {';
                                        echo '  try {';
                                        echo '    const ch = ' . json_encode($homRow['characteristics']) . ';';
                                        echo '    const inst = (ch.match(/Installer:\\s*([^|]+)/i) || [null, ""])[1].trim();';
                                        echo '    const br = (ch.match(/Brand:\\s*([^|]+)/i) || [null, ""])[1].trim();';
                                        echo '    const md = (ch.match(/Model:\\s*([^|]+)/i) || [null, ""])[1].trim();';
                                        echo '    document.getElementById("homopolar_installer").value = inst;';
                                        echo '    document.getElementById("homopolar_brand").value = br;';
                                        echo '    document.getElementById("homopolar_model").value = md;';
                                        echo '  } catch (e) { console.warn("Failed to parse homopolar characteristics", e); }';
                                        echo '});';
                                    }
                                    echo '</script>';
                                }
                            }
                            ?>

                            <h3 class="section-heading">System Layout</h3>

                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Array Configuration</h5>
                                    <!-- Botão de adicionar layout agora está no formulário da tabela -->
                                </div>
                                <div class="card-body">
                                    <!-- Validation Summary -->
                                    <div id="layout-validation-summary" class="alert alert-info mb-3" style="display: none;">
                                        <div><strong>📊 Quantity Summary:</strong></div>
                                        <div style="margin-top: 8px;">
                                            Total PV Modules: <span id="total-modules-qty" class="badge bg-primary">0</span>
                                            &nbsp;&nbsp;&nbsp;
                                            Total Layouts: <span id="total-layouts-qty" class="badge bg-success">0</span>
                                        </div>
                                        <div style="margin-top: 8px; font-size: 0.9em; color: #666;">
                                            ℹ️ System Layouts cannot exceed PV Modules quantity
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="layouts-table" class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Roof/Area ID</th>
                                                    <th>Module Qty</th>
                                                    <th>Azimuth (°)</th>
                                                    <th>Tilt (°)</th>
                                                    <th>Mounting</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr id="layout-form-row"></tr>
                                            </tbody>
                                            <tbody id="layouts-table-body">
                                                <!-- Table rows will be dynamically added here -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Hidden input to store layouts data for form submission -->
                                    <input type="hidden" id="layouts_data" name="layouts_data" value="">

                                    <?php
                                    // Load existing layouts if editing a report
                                    if (isset($reportData['id'])) {
                                        $reportId = $reportData['id'];

                                        // Try to load from new report_system_layout table first
                                        $layoutsStmt = $pdo->prepare("
                                            SELECT id, roof_id, quantity, azimuth, tilt, mounting, sort_order
                                            FROM report_system_layout 
                                            WHERE report_id = ?
                                            ORDER BY sort_order ASC
                                        ");
                                        $layoutsStmt->execute([$reportId]);
                                        $existingLayouts = $layoutsStmt->fetchAll(PDO::FETCH_ASSOC);

                                        // If no layouts found and old table has data, migrate it
                                        if (empty($existingLayouts)) {
                                            // Try loading from old report_equipment table for backward compatibility
                                            $oldLayoutsStmt = $pdo->prepare("
                                                SELECT characteristics
                                                FROM report_equipment 
                                                WHERE report_id = ? AND equipment_type = 'System Layout'
                                            ");
                                            $oldLayoutsStmt->execute([$reportId]);
                                            $oldLayouts = $oldLayoutsStmt->fetchAll(PDO::FETCH_ASSOC);

                                            if (!empty($oldLayouts)) {
                                                $existingLayouts = [];
                                                $sortOrder = 0;

                                                // Parse old format and migrate to new table
                                                foreach ($oldLayouts as $oldLayout) {
                                                    if (!empty($oldLayout['characteristics'])) {
                                                        $layoutData = [];
                                                        $parts = explode('|', $oldLayout['characteristics']);
                                                        foreach ($parts as $part) {
                                                            $pair = explode(':', $part, 2);
                                                            if (count($pair) === 2) {
                                                                $key = trim($pair[0]);
                                                                $value = trim($pair[1]);

                                                                switch ($key) {
                                                                    case 'Roof/Area ID':
                                                                        $layoutData['roof_id'] = $value;
                                                                        break;
                                                                    case 'Module Qty':
                                                                        $layoutData['quantity'] = (int)$value;
                                                                        break;
                                                                    case 'Azimuth':
                                                                        $layoutData['azimuth'] = (float)$value;
                                                                        break;
                                                                    case 'Tilt':
                                                                        $layoutData['tilt'] = (float)$value;
                                                                        break;
                                                                    case 'Mounting':
                                                                        $layoutData['mounting'] = $value;
                                                                        break;
                                                                }
                                                            }
                                                        }

                                                        if (!empty($layoutData)) {
                                                            // Insert into new table
                                                            $migrateStmt = $pdo->prepare("
                                                                INSERT INTO report_system_layout 
                                                                (report_id, roof_id, quantity, azimuth, tilt, mounting, sort_order)
                                                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                                            ");
                                                            $migrateStmt->execute([
                                                                $reportId,
                                                                $layoutData['roof_id'] ?? '',
                                                                $layoutData['quantity'] ?? 1,
                                                                $layoutData['azimuth'] ?? 0,
                                                                $layoutData['tilt'] ?? 0,
                                                                $layoutData['mounting'] ?? '',
                                                                $sortOrder++
                                                            ]);

                                                            $layoutData['sort_order'] = $sortOrder - 1;
                                                            $existingLayouts[] = $layoutData;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        if (!empty($existingLayouts)) {
                                            echo '<script>';
                                            echo 'console.log("[PHP] Loading layouts from report_system_layout - ' . count($existingLayouts) . ' layouts");';
                                            echo 'window.existingLayouts = ' . json_encode($existingLayouts) . ';';
                                            echo '</script>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>

                            <h3 class="section-heading">Inverters</h3>

                            <!-- Instrução de sequência de inversores -->
                            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Important:</strong> Add inverters in sequential order following the site nomenclature (1st inverter = Inverter 1, 2nd inverter = Inverter 2, etc.)
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Inverter Details</h5>
                                    <div>
                                        <button type="button" id="clear-inverters-btn" class="btn btn-sm btn-danger me-2">
                                            <i class="fas fa-trash"></i> Clear All
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Nav tabs para uma interface mais limpa -->
                                    <ul class="nav nav-tabs mb-3" id="inverterDetailsTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab" aria-controls="basic-info" aria-selected="true">
                                                <i class="fas fa-info-circle me-1"></i> Basic Info
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="accessories-tab" data-bs-toggle="tab" data-bs-target="#accessories-info" type="button" role="tab" aria-controls="accessories-info" aria-selected="false">
                                                <i class="fas fa-plug me-1"></i> Associated Equipment
                                            </button>
                                        </li>
                                    </ul>

                                    <!-- Tab panes -->
                                    <div class="tab-content">
                                        <!-- Tab de informação básica do inversor -->
                                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="basic-tab">
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <label for="new_inverter_brand" class="form-label">Brand</label>
                                                    <div class="input-group">
                                                        <select class="form-select" id="new_inverter_brand">
                                                            <option value="">Select Brand...</option>
                                                            <!-- Options will be loaded from database via AJAX -->
                                                        </select>
                                                        <button class="btn btn-outline-success" type="button" id="add-inverter-brand-btn" title="Add New Brand">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="new_inverter_model" class="form-label">Model</label>
                                                    <div class="input-group">
                                                        <select class="form-select" id="new_inverter_model">
                                                            <option value="">Select Model...</option>
                                                            <!-- Options will be loaded based on selected brand -->
                                                        </select>
                                                        <button class="btn btn-outline-success" type="button" id="add-inverter-model-btn" title="Add New Model">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label for="new_inverter_max_current" class="form-label">Max Output Current (A)</label>
                                                    <input type="text" class="form-control" id="new_inverter_max_current" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="new_inverter_status" class="form-label">Status</label>
                                                    <select class="form-select" id="new_inverter_status">
                                                        <option value="new">New</option>
                                                        <option value="existing">Existing</option>
                                                        <option value="replacement">Replacement</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="new_inverter_serial" class="form-label">Serial Number</label>
                                                    <input type="text" class="form-control" id="new_inverter_serial" placeholder="Optional">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="new_inverter_location" class="form-label">Location</label>
                                                    <input type="text" class="form-control" id="new_inverter_location" placeholder="Optional">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tab de equipamentos associados -->
                                        <div class="tab-pane fade" id="accessories-info" role="tabpanel" aria-labelledby="accessories-tab">
                                            <!-- Card de grupo para organizar visualmente os equipamentos -->
                                            <div class="row">
                                                <!-- Circuit Breaker -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-header bg-light d-flex align-items-center">
                                                            <div class="icon-container me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); border-radius: 8px;">
                                                                <i class="fas fa-bolt text-white"></i>
                                                            </div>
                                                            <strong>Circuit Breaker</strong>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label for="new_circuit_breaker_brand" class="form-label">Brand</label>
                                                                <div class="input-group">
                                                                    <select class="form-select" id="new_circuit_breaker_brand">
                                                                        <option value="">Select Brand...</option>
                                                                        <!-- Options will be loaded from database via AJAX -->
                                                                    </select>
                                                                    <button class="btn btn-outline-success" type="button" id="add-circuit-breaker-brand-btn" title="Add New Brand">
                                                                        <i class="fas fa-plus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label for="new_circuit_breaker_model" class="form-label">Model</label>
                                                                <div class="input-group">
                                                                    <select class="form-select" id="new_circuit_breaker_model">
                                                                        <option value="">Select Model...</option>
                                                                        <!-- Options will be loaded based on selected brand -->
                                                                    </select>
                                                                    <button class="btn btn-outline-success" type="button" id="add-circuit-breaker-model-btn" title="Add New Model">
                                                                        <i class="fas fa-plus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="mt-3">
                                                                <label for="new_circuit_breaker_rated_current" class="form-label">Rated Current (A) <span class="text-danger">*</span></label>
                                                                <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" class="form-control" id="new_circuit_breaker_rated_current" placeholder="e.g., 16">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Differential -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-header bg-light d-flex align-items-center">
                                                            <div class="icon-container me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); border-radius: 8px;">
                                                                <i class="fas fa-shield-alt text-white"></i>
                                                            </div>
                                                            <strong>Differential</strong>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label for="new_differential_brand" class="form-label">Brand</label>
                                                                <div class="input-group">
                                                                    <select class="form-select" id="new_differential_brand">
                                                                        <option value="">Select Brand...</option>
                                                                        <!-- Options will be loaded from database via AJAX -->
                                                                    </select>
                                                                    <button class="btn btn-outline-success" type="button" id="add-differential-brand-btn" title="Add New Brand">
                                                                        <i class="fas fa-plus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label for="new_differential_model" class="form-label">Model</label>
                                                                <div class="input-group">
                                                                    <select class="form-select" id="new_differential_model">
                                                                        <option value="">Select Model...</option>
                                                                        <!-- Options will be loaded based on selected brand -->
                                                                    </select>
                                                                    <button class="btn btn-outline-success" type="button" id="add-differential-model-btn" title="Add New Model">
                                                                        <i class="fas fa-plus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="mt-3">
                                                                <label for="new_differential_rated_current" class="form-label">Rated Current (A) <span class="text-danger">*</span></label>
                                                                <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.01" class="form-control" id="new_differential_rated_current" placeholder="e.g., 25">
                                                            </div>
                                                            <div class="mt-3">
                                                                <label for="new_differential_current" class="form-label">Differential Current (mA)</label>
                                                                <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" class="form-control" id="new_differential_current" placeholder="e.g., 30">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Cable -->
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-header bg-light d-flex align-items-center">
                                                            <div class="icon-container me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); border-radius: 8px;">
                                                                <i class="fas fa-network-wired text-white"></i>
                                                            </div>
                                                            <strong>Cable</strong>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label for="new_cable_brand" class="form-label">Brand</label>
                                                                <select class="form-select" id="new_cable_brand">
                                                                    <option value="">Select Brand...</option>
                                                                    <!-- Options will be loaded from database via AJAX -->
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="new_cable_model" class="form-label">Model</label>
                                                                <input type="text" class="form-control" id="new_cable_model" placeholder="e.g., H1Z2Z2-K">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="new_cable_size" class="form-label">Size (mm²)</label>
                                                                <input type="text" class="form-control" id="new_cable_size" placeholder="e.g., 4.0">
                                                            </div>
                                                            <div>
                                                                <label for="new_cable_insulation" class="form-label">Cable Insulation</label>
                                                                <input type="text" class="form-control" id="new_cable_insulation" placeholder="e.g., PVC, XLPE">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submit Button Section -->
                                    <div class="text-center mt-4">
                                        <button type="button" id="submit-inverter-btn" class="btn btn-lg btn-primary">
                                            <i class="fas fa-plus-circle me-2"></i> Add This Inverter to Project
                                        </button>
                                    </div>

                                    <!-- Added Inverters Section -->
                                    <div class="mt-4 pt-2">
                                        <h5 class="mb-3 border-bottom pb-2">
                                            <i class="fas fa-list-alt me-2"></i> Added Inverters
                                            <small class="text-muted ms-2" id="inverter-count-badge">(0)</small>
                                        </h5>

                                        <!-- Inverter Cards Container -->
                                        <div id="inverters-container" class="row row-cols-1 row-cols-md-2 g-4">
                                            <!-- Inverter cards will be added here dynamically -->
                                            <div class="col text-center py-5 text-muted" id="no-inverters-message">
                                                <i class="fas fa-solar-panel fa-3x mb-3"></i>
                                                <h5>No inverters added yet</h5>
                                                <p>Click "Add Inverter" button to add a new inverter</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden table for compatibility with existing code -->
                                    <div class="d-none">
                                        <table id="inverters-table">
                                            <tbody id="inverters-table-body">
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Hidden input to store inverters data for form submission -->
                                    <input type="hidden" id="inverters_data" name="inverters_data" value="">

                                    <?php
                                    // Load existing inverters if editing a report
                                    if (isset($reportData['id'])) {
                                        $reportId = $reportData['id'];
                                        $invertersStmt = $pdo->prepare("
                                            SELECT 
                                                id,
                                                brand, 
                                                model, 
                                                quantity, 
                                                deployment_status as status, 
                                                characteristics,
                                                model_id
                                            FROM report_equipment
                                            WHERE report_id = ? AND equipment_type = 'Inverter'
                                        ");
                                        $invertersStmt->execute([$reportId]);
                                        $existingInverters = $invertersStmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (!empty($existingInverters)) {
                                            // Extract all fields from characteristics
                                            foreach ($existingInverters as &$inverter) {
                                                $chars = $inverter['characteristics'];

                                                // Extract serial number
                                                preg_match('/Serial: ([^|]+)/', $chars, $matches);
                                                $inverter['serial_number'] = isset($matches[1]) ? trim($matches[1]) : '';

                                                // Extract location
                                                preg_match('/Location: ([^|]+)/', $chars, $locationMatches);
                                                $inverter['location'] = isset($locationMatches[1]) ? trim($locationMatches[1]) : '';

                                                // Extract max output current
                                                preg_match('/Max Output Current: ([^|]+)/', $chars, $maxCurrentMatches);
                                                $inverter['max_output_current'] = isset($maxCurrentMatches[1]) ? trim($maxCurrentMatches[1]) : '';

                                                // Extract Circuit Breaker info
                                                if (preg_match('/Circuit Breaker: ([^|]+)/', $chars, $cbMatches)) {
                                                    $cbText = trim($cbMatches[1]);
                                                    $inverter['circuit_breaker_text'] = $cbText;
                                                }

                                                // Extract Differential info
                                                if (preg_match('/Differential: ([^|]+)/', $chars, $diffMatches)) {
                                                    $diffText = trim($diffMatches[1]);
                                                    $inverter['differential_text'] = $diffText;
                                                }

                                                // Extract Cable info
                                                if (preg_match('/Cable: ([^|]+)/', $chars, $cableMatches)) {
                                                    $cableText = trim($cableMatches[1]);
                                                    $inverter['cable_text'] = $cableText;
                                                }

                                                // Extract Notes
                                                preg_match('/Notes: ([^|]+)/', $chars, $notesMatches);
                                                $inverter['notes'] = isset($notesMatches[1]) ? trim($notesMatches[1]) : '';

                                                // Extract datasheet URL
                                                preg_match('/Datasheet: ([^|]+)/', $chars, $datasheetMatches);
                                                $inverter['datasheet_url'] = isset($datasheetMatches[1]) ? trim($datasheetMatches[1]) : '';
                                            }

                                            echo '<script>';
                                            echo 'console.log("[PHP] Loading inverters data IMMEDIATELY - ' . count($existingInverters) . ' inverters");';
                                            echo 'console.log("[PHP] Full inverters data:", ' . json_encode($existingInverters) . ');';
                                            // Log model_ids specifically
                                            $modelIds = array_column($existingInverters, 'model_id');
                                            echo 'console.log("[PHP] Model IDs from DB:", ' . json_encode($modelIds) . ');';
                                            echo 'window.existingInverters = ' . json_encode($existingInverters) . ';';
                                            echo 'window.invertersList = window.existingInverters || [];';
                                            echo 'try { var invField = document.getElementById("inverters_data"); if (invField) invField.value = JSON.stringify(window.invertersList); } catch(e) { console.warn("[STRINGS] Failed to set inverters_data hidden input", e);}';
                                            echo 'console.log("[PHP] window.invertersList set from existingInverters:", window.invertersList);';
                                            // Attempt to restore dropdown selections after DOM is ready
                                            echo 'document.addEventListener("DOMContentLoaded", function() {';
                                            echo '    if (typeof window.restoreAllPendingSelections === "function") { setTimeout(window.restoreAllPendingSelections, 150); }';
                                            echo '});';
                                            echo '</script>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('general-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: General
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('protection-tab').click()">
                                    Next: Protection <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Strings Tab -->
                        <div class="tab-pane fade" id="strings" role="tabpanel" aria-labelledby="strings-tab">
                            <h3 class="section-heading">String Measurements</h3>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                String measurement tables are automatically loaded for all added inverters.
                            </div>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0">String Measurement Tables</h4>
                                </div>
                                <div class="card-body">
                                    <!-- Hidden payload for SQL autosave + submission -->
                                    <input type="hidden" id="string_measurements_data" name="string_measurements_data" value="{}">
                                    <div id="string-tables-container">
                                        <!-- String measurement tables will be generated here automatically -->
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('protection-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: Protection
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('telemetry-tab').click()">
                                    Next: Telemetry <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Telemetry Tab -->
                        <div class="tab-pane fade" id="telemetry" role="tabpanel" aria-labelledby="telemetry-tab">
                            <h3 class="section-heading">Telemetry <small class="text-muted">meters, counters, routers, loggers, ftp, etc.</small></h3>

                            <!-- Credential Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-key me-1 text-primary"></i> Credential</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label for="telemetry_inverter_ref" class="form-label">Inverter (reference)</label>
                                            <select id="telemetry_inverter_ref" class="form-select">
                                                <option value="">Select Inverter...</option>
                                            </select>
                                            <div class="form-text">Choose one of the inverters already added in Equipment</div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="telemetry_username" class="form-label">Username</label>
                                            <input type="text" id="telemetry_username" class="form-control" placeholder="User">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="telemetry_password" class="form-label">Password</label>
                                            <input type="text" id="telemetry_password" class="form-control" placeholder="Password">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="telemetry_ip" class="form-label">IP</label>
                                            <input type="text" id="telemetry_ip" class="form-control" placeholder="e.g., 192.168.1.10">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-success w-100" id="add-telemetry-cred-btn" title="Add Telemetry Credential">
                                                <i class="fas fa-plus me-1"></i> Add Credential
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="telemetry_credential_data" name="telemetry_credential_data" value='<?php echo isset($telemetryCreds) && is_array($telemetryCreds) ? htmlspecialchars(json_encode($telemetryCreds), ENT_QUOTES, "UTF-8") : "[]"; ?>'>

                                    <!-- Dynamic Telemetry Credentials table -->
                                    <div class="table-responsive mt-3">
                                        <table class="table table-bordered table-hover" id="telemetry-credentials-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Inverter Ref</th>
                                                    <th>Username</th>
                                                    <th>Password</th>
                                                    <th>IP</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="telemetry-credentials-tbody">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted"><em>No credentials added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Communications Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-network-wired me-1 text-info"></i> Communications</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label for="comm_equipment" class="form-label">Equipment</label>
                                            <select id="comm_equipment" class="form-select">
                                                <option value="">Select...</option>
                                                <option value="HUB">HUB</option>
                                                <option value="RUT">RUT</option>
                                                <option value="Logger">Logger</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="comm_model" class="form-label">Model</label>
                                            <div class="input-group">
                                                <select id="comm_model" class="form-select">
                                                    <option value="">Select Model...</option>
                                                </select>
                                                <button class="btn btn-outline-success btn-sm" type="button" id="add-comm-model-btn" title="Add New Model">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="comm_serial" class="form-label">ID/Serial</label>
                                            <input type="text" id="comm_serial" class="form-control" placeholder="e.g., ABC123">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="comm_mac" class="form-label">MAC</label>
                                            <input type="text" id="comm_mac" class="form-control" placeholder="e.g., 00:1B:44:11:3A:B7">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="comm_ip" class="form-label">IP</label>
                                            <input type="text" id="comm_ip" class="form-control" placeholder="e.g., 192.168.1.100">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="comm_sim" class="form-label">SIM Card</label>
                                            <input type="text" id="comm_sim" class="form-control" placeholder="e.g., 8935...">
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-1 align-items-end">
                                        <div class="col-md-12">
                                            <label for="comm_location" class="form-label">Location</label>
                                            <input type="text" id="comm_location" class="form-control" placeholder="e.g., Control room, Rack position">
                                        </div>
                                    </div>

                                    <!-- FTP Communication Data Sub-section -->
                                    <div class="mt-4">
                                        <h6 class="text-primary mb-3"><i class="fas fa-server me-1"></i> FTP Communication Data</h6>
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-3">
                                                <label for="comm_ftp_server" class="form-label">FTP Server</label>
                                                <input type="text" id="comm_ftp_server" class="form-control" placeholder="e.g., ftp.example.com">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="comm_ftp_username" class="form-label">Username</label>
                                                <input type="text" id="comm_ftp_username" class="form-control" placeholder="FTP username">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="comm_ftp_password" class="form-label">Password</label>
                                                <input type="text" id="comm_ftp_password" class="form-control" placeholder="FTP password">
                                            </div>
                                            <div class="col-md-2">
                                                <label for="comm_file_format" class="form-label">File Format</label>
                                                <input type="text" id="comm_file_format" class="form-control" placeholder="e.g., CSV, XML, JSON">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="button" class="btn btn-sm btn-success w-100" id="add-communication-btn" title="Add Communication Device">
                                                    <i class="fas fa-plus me-1"></i> Add Device
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="communications_data" name="communications_data" value='<?php echo isset($communicationsFromSql) && is_array($communicationsFromSql) && count($communicationsFromSql) ? htmlspecialchars(json_encode($communicationsFromSql), ENT_QUOTES, "UTF-8") : "[]"; ?>'>

                                    <!-- Dynamic communications list -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered table-hover" id="communications-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Equipment</th>
                                                    <th>Model</th>
                                                    <th>ID/Serial</th>
                                                    <th>MAC</th>
                                                    <th>IP</th>
                                                    <th>SIM Card</th>
                                                    <th>Location</th>
                                                    <th>FTP Server</th>
                                                    <th>Username</th>
                                                    <th>Password</th>
                                                    <th>File Format</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="communications-tbody">
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted"><em>No devices added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Smart Meter / Smartlog / RESP Energy Meter -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-tachometer-alt me-1 text-success"></i> Smart Meter</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label for="meter_mode" class="form-label">Mode</label>
                                            <select id="meter_mode" class="form-select">
                                                <option value="master">Master</option>
                                                <option value="slave">Slave</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_brand" class="form-label">Manufacturer</label>
                                            <div class="input-group">
                                                <select id="meter_brand" class="form-select">
                                                    <option value="">Select Brand...</option>
                                                </select>
                                                <button class="btn btn-outline-success btn-sm" type="button" id="add-meter-brand-btn" title="Add New Brand">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_model" class="form-label">Model</label>
                                            <div class="input-group">
                                                <select id="meter_model" class="form-select">
                                                    <option value="">Select Model...</option>
                                                </select>
                                                <button class="btn btn-outline-success btn-sm" type="button" id="add-meter-model-btn" title="Add New Model">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_serial" class="form-label">Serial Number</label>
                                            <input type="text" id="meter_serial" class="form-control" placeholder="e.g., AS3500-XYZ...">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_ct_ratio" class="form-label">CT Ratio</label>
                                            <input type="text" id="meter_ct_ratio" class="form-control" placeholder="e.g., 200/5">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_sim_number" class="form-label">SIM Card Number</label>
                                            <input type="text" id="meter_sim_number" class="form-control" placeholder="e.g., 8935...">
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-1 align-items-end">
                                        <div class="col-md-2">
                                            <label for="meter_location" class="form-label">Location</label>
                                            <input type="text" id="meter_location" class="form-control" placeholder="e.g., Main board room">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_led1" class="form-label">LED 1</label>
                                            <select id="meter_led1" class="form-select">
                                                <option value="fixed">Fixed</option>
                                                <option value="intermittent">Intermittent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_led2" class="form-label">LED 2</label>
                                            <select id="meter_led2" class="form-select">
                                                <option value="fixed">Fixed</option>
                                                <option value="intermittent">Intermittent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_led6" class="form-label">LED 6</label>
                                            <select id="meter_led6" class="form-select">
                                                <option value="fixed">Fixed</option>
                                                <option value="intermittent">Intermittent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="meter_gsm_signal" class="form-label">GSM Signal</label>
                                            <select id="meter_gsm_signal" class="form-select">
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-success w-100" id="add-meter-btn" title="Add Smart Meter">
                                                <i class="fas fa-plus me-1"></i> Add Meter
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dynamic meters list -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered table-hover" id="telemetry-meters-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Mode</th>
                                                    <th>Manufacturer</th>
                                                    <th>Model</th>
                                                    <th>Serial</th>
                                                    <th>CT Ratio</th>
                                                    <th>SIM</th>
                                                    <th>Location</th>
                                                    <th>LED1</th>
                                                    <th>LED2</th>
                                                    <th>LED6</th>
                                                    <th>GSM</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="telemetry-meters-tbody">
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted"><em>No meters added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="telemetry_meter_data" name="telemetry_meter_data" value='<?php echo isset($telemetryMetersFromSql) && is_array($telemetryMetersFromSql) && count($telemetryMetersFromSql) ? htmlspecialchars(json_encode($telemetryMetersFromSql), ENT_QUOTES, "UTF-8") : "[]"; ?>'>
                                </div>
                            </div>

                            <!-- Energy Meters Section -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h4 class="h6 mb-0"><i class="fas fa-bolt me-1 text-warning"></i> Energy Meters</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label for="energy_meter_scope" class="form-label">Scope</label>
                                            <select id="energy_meter_scope" class="form-select">
                                                <option value="">Select Scope...</option>
                                                <option value="grid_meter">Grid Meter</option>
                                                <option value="pv_meter">PV Meter</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="energy_meter_brand" class="form-label">Brand</label>
                                            <div class="input-group">
                                                <select id="energy_meter_brand" class="form-select">
                                                    <?php include 'includes/inject_energy_meter_dropdown.php'; ?>
                                                </select>
                                                <script>
                                                    document.addEventListener('DOMContentLoaded', function() {
                                                        const el = document.getElementById('energy_meter_brand');
                                                        console.log('[PHP INJECT] energy_meter_brand options count:', el ? el.options.length : 'not found');
                                                    });
                                                </script>
                                                <button class="btn btn-outline-success btn-sm" type="button" id="add-energy-meter-brand-btn" title="Add New Brand">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="energy_meter_model" class="form-label">Model</label>
                                            <div class="input-group">
                                                <select id="energy_meter_model" class="form-select">
                                                    <option value="">Select Model...</option>
                                                </select>
                                                <button class="btn btn-outline-success btn-sm" type="button" id="add-energy-meter-model-btn" title="Add New Model">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="energy_meter_rs485_address" class="form-label">RS485 Address</label>
                                            <input type="number" id="energy_meter_rs485_address" class="form-control" placeholder="1-247 (optional)">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="energy_meter_ct_ratio" class="form-label">CT Ratio</label>
                                            <input type="text" id="energy_meter_ct_ratio" class="form-control" placeholder="e.g., 200/5">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-success w-100" id="add-energy-meter-btn" title="Add Energy Meter">
                                                <i class="fas fa-plus me-1"></i> Add Energy Meter
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dynamic energy meters list -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered table-hover" id="energy-meters-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Scope</th>
                                                    <th>Brand</th>
                                                    <th>Model</th>
                                                    <th>RS485 Address</th>
                                                    <th>CT Ratio</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="energy-meters-tbody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted"><em>No energy meters added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="energy_meter_data" name="energy_meter_data" value='<?php echo isset($energyMetersFromSql) && is_array($energyMetersFromSql) && count($energyMetersFromSql) ? htmlspecialchars(json_encode($energyMetersFromSql), ENT_QUOTES, "UTF-8") : "[]"; ?>'>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('strings-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: Strings
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('punch-list-tab').click()">
                                    Next: Punch List <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Punch List Tab -->
                        <div class="tab-pane fade" id="punch-list" role="tabpanel" aria-labelledby="punch-list-tab">
                            <h3 class="section-heading">Punch List</h3>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Add Punch List Item</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 mb-4 align-items-end">
                                        <!-- ID is auto-generated and hidden to avoid manual user entry -->
                                        <input type="hidden" id="punch_id" class="form-control" value="">
                                        <div class="col-md-2">
                                            <label for="punch_severity" class="form-label">Severity Level</label>
                                            <select id="punch_severity" class="form-select">
                                                <option value="">Select...</option>
                                                <option value="High">High — Immediate action required</option>
                                                <option value="Medium">Medium — Prompt action required</option>
                                                <option value="Low">Low — Action can be delayed</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="punch_description" class="form-label">Description</label>
                                            <input type="text" id="punch_description" class="form-control" placeholder="Issue description">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="punch_opening_date" class="form-label">Opening Date</label>
                                            <input type="date" id="punch_opening_date" class="form-control">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="punch_responsible" class="form-label">Responsible</label>
                                            <select id="punch_responsible" class="form-select">
                                                <option value="">Select...</option>
                                                <option value="Installer">Installer</option>
                                                <option value="Cleanwatts">Cleanwatts</option>
                                                <option value="Client">Client</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="punch_resolution_date" class="form-label">Resolution Date</label>
                                            <input type="date" id="punch_resolution_date" class="form-control">
                                        </div>
                                        <div class="col-12 d-flex align-items-end justify-content-end punch-actions mt-2">
                                            <button type="button" id="add-punch-list-btn" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus"></i> Add Item
                                            </button>
                                            <button type="button" id="cancel-punch-list-btn" class="btn btn-sm btn-secondary ms-2" style="display: none;">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dynamic punch list table -->
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="" id="punch_filter_open">
                                            <label class="form-check-label small" for="punch_filter_open">
                                                Show open only (no Resolution Date)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-bordered table-hover" id="punch-list-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Severity</th>
                                                    <th>Description</th>
                                                    <th>Opening Date</th>
                                                    <th>Responsible</th>
                                                    <th>Resolution Date</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="punch-list-tbody">
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted"><em>No items added</em></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="punch_list_data" name="punch_list_data" value='<?php echo isset($punchListFromSql) && is_array($punchListFromSql) && count($punchListFromSql) ? htmlspecialchars(json_encode($punchListFromSql), ENT_QUOTES, "UTF-8") : "[]"; ?>'>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('telemetry-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: Telemetry
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('finish-tab').click()">
                                    Next: Finish <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Finish Tab -->
                        <div class="tab-pane fade" id="finish" role="tabpanel" aria-labelledby="finish-tab">
                            <h3 class="section-heading">Complete Commissioning</h3>

                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Almost done!</strong> Please review your information and submit the commissioning report.
                            </div>

                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Additional Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php
                                                                                                        if ($reportId) {
                                                                                                            // Load existing notes from database
                                                                                                            $notesStmt = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Additional Notes' LIMIT 1");
                                                                                                            $notesStmt->execute([$reportId]);
                                                                                                            $notesRow = $notesStmt->fetch();
                                                                                                            if ($notesRow && !empty($notesRow['characteristics'])) {
                                                                                                                // Extract notes text from "Notes: {text}" format
                                                                                                                $notesText = $notesRow['characteristics'];
                                                                                                                if (stripos($notesText, 'Notes:') === 0) {
                                                                                                                    $notesText = trim(substr($notesText, strlen('Notes:')));
                                                                                                                }
                                                                                                                echo htmlspecialchars($notesText);
                                                                                                            }
                                                                                                        }
                                                                                                        ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <?php
                            // Prepare Finish Photos checklist defaults
                            $finishChecklistDefaults = [
                                ['key' => 'panorama', 'label' => 'General panoramic views of the plant'],
                                ['key' => 'pv_labels', 'label' => 'PV modules labels visible and correct'],
                                ['key' => 'inverters_install', 'label' => 'Inverter installations (each inverter)'],
                                ['key' => 'strings_labeling', 'label' => 'Strings labeling on combiner/inverters'],
                                ['key' => 'protections', 'label' => 'Protections identification (breakers/differentials)'],
                                ['key' => 'cables_id', 'label' => 'AC/DC cables identification and routing'],
                                ['key' => 'clamp_proof', 'label' => 'Clamp measurement pictures (L1/L2/L3)'],
                                ['key' => 'earth_meter', 'label' => 'Earth resistance meter and reading'],
                                ['key' => 'telemetry_labels', 'label' => 'Telemetry devices labels/screens'],
                                ['key' => 'energy_meter', 'label' => 'Energy meter display with currents'],
                                ['key' => 'punch_list', 'label' => 'Resolved punch list items evidence'],
                            ];

                            // Load saved Finish Photos data if editing
                            $finishSaved = [];
                            $finishLink = '';
                            if ($reportId) {
                                // Checklist
                                $stmt = $pdo->prepare("SELECT deployment_status, brand, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Finish - Photo Checklist'");
                                $stmt->execute([$reportId]);
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rows as $r) {
                                    $key = '';
                                    $note = '';
                                    if (!empty($r['characteristics'])) {
                                        if (preg_match('/Key:\s*([^|]+)/i', $r['characteristics'], $m)) {
                                            $key = trim($m[1]);
                                        }
                                        if (preg_match('/Note:\s*(.+)/i', $r['characteristics'], $m2)) {
                                            $note = trim($m2[1]);
                                        }
                                    }
                                    $label = $r['brand'] ?? '';
                                    if ($key === '' && $label !== '') {
                                        // Fallback reference by label
                                        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
                                    }
                                    if ($key !== '') {
                                        $finishSaved[$key] = [
                                            'label' => $label,
                                            'status' => $r['deployment_status'] ?? '',
                                            'note' => $note,
                                        ];
                                    }
                                }

                                // Link
                                $stmt2 = $pdo->prepare("SELECT characteristics FROM report_equipment WHERE report_id = ? AND equipment_type = 'Finish - Photos Link' LIMIT 1");
                                $stmt2->execute([$reportId]);
                                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                                if ($row2 && !empty($row2['characteristics'])) {
                                    if (preg_match('/URL:\s*(.+)/i', $row2['characteristics'], $mm)) {
                                        $finishLink = trim($mm[1]);
                                    } else {
                                        $finishLink = trim($row2['characteristics']);
                                    }
                                }
                            }
                            ?>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-camera me-2"></i>
                                    Photos & Evidence (Finish)
                                </div>
                                <div class="card-body">
                                    <div id="finishValidationError" class="alert alert-danger d-none" role="alert">
                                        Please select a status (Completed, Pending or N/A) for all items in the checklist.
                                    </div>
                                    <div class="mb-3">
                                        <label for="finish_photos_link" class="form-label">OneDrive/Photos Repository Link</label>
                                        <input type="url" class="form-control" id="finish_photos_link" name="finish_photos_link" placeholder="https://..." value="<?php echo htmlspecialchars($finishLink); ?>">
                                        <div class="form-text">Paste the shared folder link where all photos are stored. (Optional)</div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width: 55%">Checklist Item <span class="text-danger">*</span></th>
                                                    <th style="width: 20%">Status <span class="text-danger">*</span></th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($finishChecklistDefaults as $idx => $item): ?>
                                                    <?php
                                                    $k = $item['key'];
                                                    $saved = $finishSaved[$k] ?? null;
                                                    $statusVal = $saved['status'] ?? '';
                                                    $noteVal = $saved['note'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" class="finish-key" value="<?php echo htmlspecialchars($k); ?>">
                                                            <strong><?php echo htmlspecialchars($item['label']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input finish-status-choice" type="checkbox" value="Completed" id="chk_<?php echo $k; ?>_completed_<?php echo $idx; ?>" <?php echo ($statusVal === 'Completed') ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="chk_<?php echo $k; ?>_completed_<?php echo $idx; ?>">Completed</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input finish-status-choice" type="checkbox" value="Pending" id="chk_<?php echo $k; ?>_pending_<?php echo $idx; ?>" <?php echo ($statusVal === 'Pending') ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="chk_<?php echo $k; ?>_pending_<?php echo $idx; ?>">Pending</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input finish-status-choice" type="checkbox" value="N/A" id="chk_<?php echo $k; ?>_na_<?php echo $idx; ?>" <?php echo ($statusVal === 'N/A') ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="chk_<?php echo $k; ?>_na_<?php echo $idx; ?>">N/A</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm finish-note" placeholder="Optional note" value="<?php echo htmlspecialchars($noteVal); ?>">
                                                            <input type="hidden" class="finish-label" value="<?php echo htmlspecialchars($item['label']); ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <input type="hidden" id="finish_photos_data" name="finish_photos_data" value="">
                                </div>
                            </div>

                            <script>
                                // Validate Finish Photos checklist (required statuses)
                                function validateFinishPhotos() {
                                    const rows = document.querySelectorAll('#finish tbody tr');
                                    const errBox = document.getElementById('finishValidationError');
                                    let invalidCount = 0;
                                    rows.forEach((tr) => {
                                        const statusCell = tr.children[1];
                                        const checked = tr.querySelector('.finish-status-choice:checked');
                                        // clear previous state
                                        statusCell.classList.remove('border', 'border-danger');
                                        if (!checked) {
                                            invalidCount++;
                                            statusCell.classList.add('border', 'border-danger');
                                        }
                                    });
                                    if (invalidCount > 0) {
                                        if (errBox) errBox.classList.remove('d-none');
                                        document.getElementById('finish')?.scrollIntoView({
                                            behavior: 'smooth'
                                        });
                                        return false;
                                    } else {
                                        if (errBox) errBox.classList.add('d-none');
                                        return true;
                                    }
                                }

                                // Collect Finish Photos checklist into hidden JSON before submit
                                function collectFinishPhotosData() {
                                    const rows = document.querySelectorAll('#finish tbody tr');
                                    const arr = [];
                                    rows.forEach((tr) => {
                                        const key = tr.querySelector('.finish-key')?.value || '';
                                        const label = tr.querySelector('.finish-label')?.value || '';
                                        let status = '';
                                        const checked = tr.querySelector('.finish-status-choice:checked');
                                        if (checked) status = checked.value;
                                        const note = tr.querySelector('.finish-note')?.value || '';
                                        if (key || label || status || note) {
                                            arr.push({
                                                key,
                                                label,
                                                status,
                                                note
                                            });
                                        }
                                    });
                                    const hidden = document.getElementById('finish_photos_data');
                                    if (hidden) {
                                        hidden.value = JSON.stringify(arr);
                                    }
                                }

                                // Make finish status checkboxes behave like radio (single selection per row)
                                (function() {
                                    const rows = document.querySelectorAll('#finish tbody tr');
                                    rows.forEach((tr) => {
                                        const boxes = tr.querySelectorAll('.finish-status-choice');
                                        boxes.forEach((box) => {
                                            box.addEventListener('change', () => {
                                                if (box.checked) {
                                                    boxes.forEach((other) => {
                                                        if (other !== box) other.checked = false;
                                                    });
                                                }
                                                // Keep hidden JSON in sync for autosave and non-standard submits
                                                try {
                                                    collectFinishPhotosData();
                                                } catch (e) {
                                                    /* ignore */
                                                }
                                            });
                                        });
                                        // Also sync when notes change
                                        const note = tr.querySelector('.finish-note');
                                        if (note) {
                                            note.addEventListener('input', () => {
                                                try {
                                                    collectFinishPhotosData();
                                                } catch (e) {}
                                            });
                                        }
                                    });
                                    // Initialize hidden payload on load (covers server-prefilled state)
                                    try {
                                        collectFinishPhotosData();
                                    } catch (e) {
                                        /* ignore */
                                    }
                                })();
                            </script>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('strings-tab').click()">
                                    <i class="fas fa-arrow-left me-1"></i> Previous: Strings
                                </button>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-1"></i>
                                    <?php echo $reportId ? 'Update Report' : 'Save & Generate Report'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Layout Item Template (hidden) - REMOVED: Now using table-based system -->
<!-- <template id="layout-item-template">
    <div class="dynamic-item mt-3 pt-3 border-top">
        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Roof/Area ID</label>
                <input type="text" class="form-control" name="roof_id[]">
            </div>
            <div class="col-md-3">
                <label class="form-label">Module Quantity</label>
                <input type="number" class="form-control" name="layout_quantity[]">
            </div>
            <div class="col-md-2">
                <label class="form-label">Azimuth (°)</label>
                <input type="number" class="form-control" name="azimuth[]">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tilt (°)</label>
                <input type="number" class="form-control" name="tilt[]">
            </div>
            <div class="col-md-2">
                <div class="d-flex">
                    <div class="flex-grow-1 me-2">
                        <label class="form-label">Mounting</label>
                        <select class="form-select" name="mounting[]">
                            <option value="roof">Roof</option>
                            <option value="ground">Ground</option>
                            <option value="facade">Facade</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger delete-item mb-0" title="Remove Array">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template> -->

<script>
    // Load data when inverter model is selected
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize string measurements tab - automatically load all inverter tables when tab is shown
        const stringsTab = document.getElementById('strings-tab');
        if (stringsTab) {
            stringsTab.addEventListener('shown.bs.tab', function() {
                console.log('[Index.php] Strings tab shown - calling loadAllInverterStringTables');
                if (typeof loadAllInverterStringTables === 'function') {
                    loadAllInverterStringTables();

                    // 🔥 AFTER tables are generated, initialize MPPT Manager to load saved measurements
                    console.log('[MPPT] 📍 Tables geradas - inicializando MPPT Manager...');
                    setTimeout(function() {
                        if (typeof initMPPTManager === "function") {
                            console.log('[MPPT] ✅ Carregando medições do SQL...');
                            initMPPTManager(window.reportId);
                        } else {
                            console.error('[MPPT] ❌ initMPPTManager não disponível!');
                        }
                    }, 200); // Aguarda um pouco para garantir que o DOM foi atualizado
                } else {
                    console.error('[Index.php] loadAllInverterStringTables not available!');
                }
            });
        }

        // Inverter brands are loaded by dropdown-handler.js (loadBrands/loadEquipmentDropdowns).
        // We intentionally do NOT populate #new_inverter_brand here to avoid duplicates.

        // Load commissioning responsibles on page load
        fetch((window.BASE_URL || '') + 'ajax/get_commissioning_responsibles.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('commissioning_responsible_id');
                if (!select) return;

                // Ensure data is array
                if (!Array.isArray(data)) {
                    console.error('[Comm Resp] Unexpected data:', data);
                    return;
                }

                data.forEach(responsible => {
                    const option = document.createElement('option');
                    option.value = responsible.id;
                    option.textContent = responsible.name;
                    select.appendChild(option);
                });

                // Restore selection if in edit mode
                const selectedId = select.dataset.selected;
                if (selectedId) {
                    console.log('[Init] Restoring commissioning responsible selection:', selectedId);
                    select.value = selectedId;
                }
            });

        // Load representatives when EPC is selected
        const epcSelect = document.getElementById('epc_id');
        if (epcSelect) {
            epcSelect.addEventListener('change', function() {
                const addRepBtn = document.getElementById('add-representative-btn');
                if (this.value) {
                    // Enable representative button
                    addRepBtn.disabled = false;
                    addRepBtn.title = 'Add New Representative';

                    fetch((window.BASE_URL || '') + `ajax/get_representatives.php?epc_id=${this.value}`)
                        .then(response => response.json())
                        .then(data => {
                            const select = document.getElementById('representative_id');
                            if (!select) return;
                            select.innerHTML = '<option value="">Select Representative...</option>';

                            if (!Array.isArray(data)) {
                                console.error('[Rep] Unexpected data:', data);
                                return;
                            }

                            data.forEach(rep => {
                                const option = document.createElement('option');
                                option.value = rep.id;
                                option.textContent = `${rep.name} (${rep.phone})`;
                                select.appendChild(option);
                            });
                        });
                } else {
                    // Keep representative button enabled
                    addRepBtn.disabled = false;
                    addRepBtn.title = 'Add New Representative';

                    // Clear representatives
                    const select = document.getElementById('representative_id');
                    select.innerHTML = '<option value="">Select Representative...</option>';
                }
            });
        }

        // Initialize add EPC button
        const addEpcBtn = document.getElementById('add-epc-btn');
        if (addEpcBtn) {
            addEpcBtn.addEventListener('click', () => {
                showAddEpcModal();
            });
        }

        // Initialize add Representative button
        const addRepBtn = document.getElementById('add-representative-btn');
        if (addRepBtn) {
            addRepBtn.disabled = false;
            addRepBtn.title = 'Add New Representative';
            addRepBtn.addEventListener('click', () => {
                showAddRepresentativeModal();
            });
        }

        // Initialize add Commissioning Responsible button
        const addCommissioningResponsibleBtn = document.getElementById('add-commissioning-responsible-btn');
        if (addCommissioningResponsibleBtn) {
            addCommissioningResponsibleBtn.addEventListener('click', () => {
                showAddCommissioningResponsibleModal();
            });
        }

        // Circuit Breaker brand/model handled by global modal handlers in assets/js/main.js

        // Initialize add Meter Brand button


        // Initialize add Meter Model button
        const addMeterModelBtn = document.getElementById('add-meter-model-btn');
        if (addMeterModelBtn) {
            addMeterModelBtn.addEventListener('click', () => {
                showAddMeterModelModal();
            });
        }
    });

    // Function to show add EPC modal
    function showAddEpcModal() {
        // Create modal HTML
        const modalHtml = `
        <div class="modal fade" id="addEpcModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>Add New EPC Company
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addEpcForm">
                            <div class="mb-3">
                                <label for="epc_name" class="form-label">EPC Company Name</label>
                                <input type="text" class="form-control" id="epc_name" name="epc_name" placeholder="Enter EPC company name" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-success" id="addEpcSubmitBtn">
                            <i class="fas fa-plus me-1"></i>Add EPC
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('addEpcModal'));
        const form = document.getElementById('addEpcForm');
        const submitBtn = document.getElementById('addEpcSubmitBtn');

        // Show modal
        modal.show();

        // Handle form submission
        const handleSubmit = () => {
            const formData = new FormData(form);
            const epcName = formData.get('epc_name').trim();

            if (!epcName) {
                customModal.showWarning('Please enter an EPC company name.');
                return;
            }

            // Send request to add EPC
            fetch((window.BASE_URL || '') + 'ajax/add_epc.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add new option to select
                        const select = document.getElementById('epc_id');
                        const option = document.createElement('option');
                        option.value = data.epc.id;
                        option.textContent = data.epc.name;
                        select.appendChild(option);

                        // Select the new option
                        select.value = data.epc.id;

                        // Trigger change event to load representatives
                        select.dispatchEvent(new Event('change'));

                        // Show success message
                        customModal.showSuccess('EPC company added successfully!');

                        // Close modal
                        modal.hide();
                    } else {
                        customModal.showError(data.error || 'Failed to add EPC company.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    customModal.showError('An error occurred while adding the EPC company.');
                });
        };

        // Submit button click
        submitBtn.addEventListener('click', handleSubmit);

        // Form enter key
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        document.getElementById('addEpcModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addEpcModal').remove();
        });
    }

    // Function to show add Representative modal
    function showAddRepresentativeModal() {
        const epcSelect = document.getElementById('epc_id');
        const selectedEpcId = epcSelect.value;
        const selectedEpcText = epcSelect.options[epcSelect.selectedIndex]?.text || 'None';

        // Create modal HTML
        const modalHtml = `
<div class="modal fade" id="addRepresentativeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Representative
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Associated EPC:</strong> ${selectedEpcText}
                </div>
                <form id="addRepresentativeForm">
                    <div class="mb-3">
                        <label for="rep_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="rep_name" name="name" placeholder="Full name" required>
                    </div>
                    <div class="mb-3">
                        <label for="rep_phone" class="form-label">Phone *</label>
                        <input type="tel" class="form-control" id="rep_phone" name="phone" placeholder="Phone number" required>
                    </div>
                    <div class="mb-3">
                        <label for="rep_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="rep_email" name="email" placeholder="Email address">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="addRepresentativeSubmitBtn">
                    <i class="fas fa-plus me-1"></i>Add Representative
                </button>
            </div>
        </div>
    </div>
</div>
`;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('addRepresentativeModal'));
        const form = document.getElementById('addRepresentativeForm');
        const submitBtn = document.getElementById('addRepresentativeSubmitBtn');

        // Show modal
        modal.show();

        // Handle form submission
        const handleSubmit = () => {
            const formData = new FormData(form);
            formData.append('epc_id', selectedEpcId);

            const name = formData.get('name').trim();
            const phone = formData.get('phone').trim();
            const email = formData.get('email').trim();

            if (!name || !phone) {
                customModal.showWarning('Name and phone are required.');
                return;
            }

            // Send request to add representative
            fetch((window.BASE_URL || '') + 'ajax/add_representative.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const text = await response.text();
                    // Try to parse JSON if possible
                    let data = null;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('[ADD REP] Non-JSON response:', text);
                        customModal.showError('Server error while adding representative. Check console for details.');
                        return;
                    }

                    if (!response.ok) {
                        console.error('[ADD REP] Error response:', data);
                        customModal.showError(data.error || data.message || 'Could not add representative');
                        return;
                    }

                    if (data.success) {
                        // Add new option to select
                        const select = document.getElementById('representative_id');
                        const option = document.createElement('option');
                        option.value = data.representative.id;
                        option.textContent = `${data.representative.name} (${data.representative.phone})`;
                        select.appendChild(option);

                        // Select the new option
                        select.value = data.representative.id;

                        // Show success message
                        customModal.showSuccess('Representative added successfully!');

                        // Close modal
                        modal.hide();
                    } else {
                        customModal.showError(data.error || 'Failed to add representative.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    customModal.showError('An error occurred while adding the representative.');
                });
        };

        // Submit button click
        submitBtn.addEventListener('click', handleSubmit);

        // Form enter key
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        // Clean up modal when closed
        document.getElementById('addRepresentativeModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addRepresentativeModal').remove();
        });
    }

    // Function to show add Commissioning Responsible modal
    function showAddCommissioningResponsibleModal() {
        // Create modal HTML
        const modalHtml = `
<div class="modal fade" id="addCommissioningResponsibleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Commissioning Responsible
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCommissioningResponsibleForm">
                    <div class="mb-3">
                        <label for="responsible_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="responsible_name" name="name" placeholder="Full name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="addCommissioningResponsibleSubmitBtn">
                    <i class="fas fa-plus me-1"></i>Add Responsible
                </button>
            </div>
        </div>
    </div>
</div>
`;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('addCommissioningResponsibleModal'));
        const form = document.getElementById('addCommissioningResponsibleForm');
        const submitBtn = document.getElementById('addCommissioningResponsibleSubmitBtn');

        // Show modal
        modal.show();

        // Handle form submission
        const handleSubmit = () => {
            const formData = new FormData(form);
            const name = formData.get('name').trim();

            if (!name) {
                customModal.showWarning('Please enter a name for the commissioning responsible.');
                return;
            }

            // Send request to add responsible
            fetch((window.BASE_URL || '') + 'ajax/add_commissioning_responsible.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add new option to select
                        const select = document.getElementById('commissioning_responsible_id');
                        const option = document.createElement('option');
                        option.value = data.responsible.id;
                        option.textContent = data.responsible.name;
                        select.appendChild(option);

                        // Select the new option
                        select.value = data.responsible.id;

                        // Show success message
                        customModal.showSuccess('Commissioning responsible added successfully!');

                        // Close modal
                        modal.hide();
                    } else {
                        customModal.showError(data.error || 'Failed to add commissioning responsible.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    customModal.showError('An error occurred while adding the commissioning responsible.');
                });
        };

        // Submit button click
        submitBtn.addEventListener('click', handleSubmit);

        // Form enter key
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        // Clean up modal when closed
        document.getElementById('addCommissioningResponsibleModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addCommissioningResponsibleModal').remove();
        });
    }

    // Circuit Breaker model modal replaced by global handler in assets/js/main.js
</script>

<!-- Information/Alert Modal -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="infoModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="infoModalMessage">
                    <!-- Info message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-check me-1"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Warning/Alert Modal -->
<div class="modal fade" id="warningModal" tabindex="-1" aria-labelledby="warningModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark" id="warningModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="warningModalMessage">
                    <!-- Warning message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-check me-1"></i>Understood
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white" id="errorModalLabel">
                    <i class="fas fa-exclamation-circle me-2"></i>Error
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="errorModalMessage">
                    <!-- Error message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="confirmModalLabel">
                    <i class="fas fa-question-circle me-2"></i>Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="confirmModalMessage">
                    <!-- Confirm message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmModalYesBtn">
                    <i class="fas fa-check me-1"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Validation Modal -->
<div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark" id="validationModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Required Fields
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="validationMessage">
                    <!-- Validation message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-check me-1"></i>Understood
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Save Button (Edit Mode Only) -->
<?php if ($reportId): ?>
    <style>
        .floating-save-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1050;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: pulse-save 2s infinite;
        }

        .floating-save-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        @keyframes pulse-save {

            0%,
            100% {
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            }

            50% {
                box-shadow: 0 6px 16px rgba(40, 167, 69, 0.6);
            }
        }

        /* ========================================
           PROFESSIONAL PRINT STYLES
           Similar to Generated Report Layout
           ======================================== */
        @media print {
            @page {
                margin: 0.75in 0.5in;
                size: A4;
            }

            body {
                background: white !important;
                color: #000 !important;
                font-family: 'Times New Roman', serif !important;
                font-size: 11px !important;
                line-height: 1.4 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .container {
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
            }

            /* Hide all screen-only elements */
            .floating-save-btn,
            .btn,
            button,
            .navbar,
            .alert,
            .nav-tabs,
            nav,
            header,
            footer,
            .no-print,
            #add-inverter-btn,
            #add-module-btn,
            .form-text,
            input[type="file"],
            .modal,
            .dropdown,
            .badge-secondary,
            textarea:read-write,
            select,
            .action-buttons {
                display: none !important;
            }

            /* Professional Header */
            .card:first-of-type {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                padding: 20px !important;
                margin-bottom: 20px !important;
                border-radius: 0 !important;
                position: relative !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-after: avoid;
            }

            .card:first-of-type::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: #fff;
                opacity: 0.3;
            }

            /* Section Cards - Professional Layout */
            .card {
                background: white !important;
                border: 1px solid #ddd !important;
                border-radius: 0 !important;
                padding: 15px !important;
                margin-bottom: 15px !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
                position: relative !important;
            }

            .card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, #2CCCD3 0%, #254A5D 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .card-header {
                margin-bottom: 12px !important;
                padding-bottom: 8px !important;
                border-bottom: 2px solid #2CCCD3 !important;
                display: block !important;
                background: transparent !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #000 !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
            }

            .card-header i {
                width: 24px !important;
                height: 24px !important;
                border-radius: 50% !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin-right: 10px !important;
                font-size: 10px !important;
                color: white !important;
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Form Elements - Show as Read-Only Text */
            input[type="text"],
            input[type="date"],
            input[type="number"],
            input[type="email"],
            input[type="tel"],
            textarea:read-only,
            select {
                border: none !important;
                background: transparent !important;
                padding: 2px 0 !important;
                font-size: 10px !important;
                font-weight: 500 !important;
                color: #000 !important;
                box-shadow: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
            }

            input:disabled,
            select:disabled,
            textarea:disabled {
                opacity: 1 !important;
                color: #000 !important;
            }

            /* Labels - Professional Style */
            label {
                font-size: 8px !important;
                font-weight: 600 !important;
                color: #495057 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                margin-bottom: 2px !important;
                display: block !important;
            }

            /* Form Groups */
            .form-group,
            .mb-3 {
                margin-bottom: 8px !important;
                background: #f8f9fa !important;
                padding: 6px !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 0 !important;
            }

            .form-group:nth-child(odd),
            .mb-3:nth-child(odd) {
                background: #e9ecef !important;
            }

            /* Table Styling (for other sections, not General) */
            table {
                font-size: 9px !important;
                border-collapse: collapse !important;
                width: 100% !important;
                margin-bottom: 10px !important;
                page-break-inside: avoid;
            }

            table th,
            table td {
                padding: 6px !important;
                border: 1px solid #dee2e6 !important;
                text-align: left !important;
                background: white !important;
            }

            table th {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                font-size: 8px !important;
                letter-spacing: 0.5px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Inverter/Module Cards */
            .inverter-card,
            .module-card,
            .equipment-card {
                background: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 0 !important;
                padding: 10px !important;
                margin-bottom: 8px !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .card-header.inverter-header,
            .card-header.module-header {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                padding: 8px !important;
                margin: -10px -10px 10px -10px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Grid Layout for Print */
            .row {
                display: table !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .col,
            .col-md-3,
            .col-md-4,
            .col-md-6,
            .col-lg-3,
            .col-lg-4,
            .col-lg-6,
            .col-lg-8,
            .col-12 {
                display: table-cell !important;
                vertical-align: top !important;
                padding: 0 5px !important;
                float: none !important;
            }

            .col-md-3,
            .col-lg-3 {
                width: 25% !important;
            }

            .col-md-4,
            .col-lg-4 {
                width: 33.33% !important;
            }

            .col-md-6,
            .col-lg-6 {
                width: 50% !important;
            }

            .col-lg-8 {
                width: 66.66% !important;
            }

            .col-12 {
                width: 100% !important;
                display: block !important;
            }

            /* Hide empty values */
            input:placeholder-shown,
            select:invalid {
                opacity: 0.5 !important;
            }

            /* Page breaks */
            .tab-pane {
                page-break-after: always;
            }

            .tab-pane:last-child {
                page-break-after: auto;
            }

            /* String Measurements Table */
            .string-measurements-container table {
                width: 100% !important;
                font-size: 8px !important;
            }

            .string-measurements-container th {
                font-size: 7px !important;
                padding: 4px !important;
            }

            .string-measurements-container td {
                padding: 4px !important;
                font-size: 8px !important;
            }

            /* Professional Spacing */
            h1,
            h2,
            h3,
            h4,
            h5,
            h6 {
                page-break-after: avoid;
                color: #000 !important;
            }

            h2 {
                font-size: 16px !important;
                margin-top: 15px !important;
                margin-bottom: 10px !important;
                border-bottom: 2px solid #2CCCD3 !important;
                padding-bottom: 5px !important;
            }

            h3 {
                font-size: 14px !important;
                margin-top: 12px !important;
                margin-bottom: 8px !important;
            }

            h4 {
                font-size: 12px !important;
                margin-top: 10px !important;
                margin-bottom: 6px !important;
            }

            /* Print-specific watermark (optional) */
            body::before {
                content: 'COMMISSIONING REPORT - DRAFT';
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 72px;
                color: rgba(0, 0, 0, 0.03);
                z-index: -1;
                white-space: nowrap;
                pointer-events: none;
            }

            /* Ensure all sections are visible */
            .collapse,
            .tab-content,
            .tab-pane {
                display: block !important;
                height: auto !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            /* Bootstrap specific fixes */
            .d-none,
            .d-md-none,
            .d-lg-none {
                display: block !important;
            }

            /* Remove animations */
            * {
                transition: none !important;
                animation: none !important;
            }

            /* ========================================
               OVERRIDE: GENERAL TAB SPECIFIC STYLES
               Must come LAST to override global .row
               ======================================== */

            /* Force General tab rows to use GRID instead of TABLE */
            #general>.row,
            #general .row.mb-3,
            div#general .row {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px !important;
                page-break-inside: avoid !important;
            }

            /* Each column within General tab */
            #general .row>.col-md-3,
            #general .row>.col-md-4,
            #general .row>.col-md-6,
            #general .row>div[class*="col-"] {
                display: inline-block !important;
                width: 48% !important;
                vertical-align: top !important;
                margin-right: 2% !important;
                margin-bottom: 8px !important;
                padding: 0 !important;
                float: none !important;
            }

            /* Last column in row - no margin */
            #general .row>.col-md-3:last-child,
            #general .row>.col-md-4:last-child,
            #general .row>.col-md-6:last-child,
            #general .row>div[class*="col-"]:last-child {
                margin-right: 0 !important;
            }

            /* Power Information row - 3 columns */
            #general .row:has(#installed_power)>.col-md-4,
            #general .row:has(#installed_power)>div[class*="col-"] {
                width: 32% !important;
                margin-right: 2% !important;
            }

            #general .row:has(#installed_power)>.col-md-4:last-child {
                margin-right: 0 !important;
            }

            /* Clean box for each field */
            #general .col-md-3,
            #general .col-md-4,
            #general .col-md-6,
            #general div[class*="col-"] {
                background: white !important;
                padding: 8px 10px !important;
                border: 1px solid #dee2e6 !important;
                border-left: 3px solid #2CCCD3 !important;
                box-sizing: border-box !important;
            }

            /* Alternate background color */
            #general .row:nth-child(even) .col-md-3,
            #general .row:nth-child(even) .col-md-4,
            #general .row:nth-child(even) .col-md-6,
            #general .row:nth-child(even) div[class*="col-"] {
                background: #f8f9fa !important;
            }

            /* Labels in General */
            #general label.form-label {
                font-size: 8px !important;
                font-weight: 600 !important;
                color: #495057 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                margin-bottom: 3px !important;
                display: block !important;
            }

            /* Inputs in General */
            #general input.form-control,
            #general select.form-select {
                font-size: 10px !important;
                font-weight: 500 !important;
                color: #000 !important;
                border: none !important;
                background: transparent !important;
                padding: 2px 0 !important;
                width: 100% !important;
                box-shadow: none !important;
            }

            /* Section headings in General */
            #general h3.section-heading {
                font-size: 12px !important;
                font-weight: 600 !important;
                color: #000 !important;
                text-transform: uppercase !important;
                border-bottom: 2px solid #2CCCD3 !important;
                padding-bottom: 5px !important;
                margin: 15px 0 10px 0 !important;
                page-break-after: avoid !important;
                display: block !important;
            }

            /* Hide input groups buttons in General */
            #general .input-group .btn,
            #general .input-group button {
                display: none !important;
            }

            #general .input-group {
                display: block !important;
            }
        }
    </style>
    <button type="button" id="floatingSaveBtn" class="btn btn-success btn-lg floating-save-btn" title="Save Changes (Ctrl+S)">
        <i class="fas fa-save me-2"></i>Save Changes
    </button>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const floatingBtn = document.getElementById('floatingSaveBtn');
            const form = document.getElementById('commissioningForm');

            if (floatingBtn && form) {
                floatingBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('[BUTTON CLICK] ✅ Floating Save Button clicked');
                    console.log('[FORM] Action:', form.action);
                    console.log('[FORM] Method:', form.method);
                    console.log('[FORM] About to call form.submit()');
                    form.submit(); // Call submit directly
                });
            } else {
                console.error('[DEBUG] Form or button not found!', {
                    floatingBtn: !!floatingBtn,
                    form: !!form
                });
            }

            // Monitor form submit
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('[FORM SUBMIT EVENT] ✅ Form submit event fired');
                    console.log('[FORM SUBMIT EVENT] Form action:', form.action);
                    console.log('[FORM SUBMIT EVENT] Form method:', form.method);
                    // Don't prevent - let form submit normally
                });
            }

            // Keyboard shortcut: Ctrl+S
            if (form) {
                document.addEventListener('keydown', function(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        console.log('[KEYBOARD] Ctrl+S pressed, submitting form');
                        form.submit();
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<!-- REMOVED: Old tab fix script - now handled in footer.php with priority -->

<!-- DEBUG SCRIPT FOR NOTES FIELD -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== NOTES FIELD DEBUG ===');

        const form = document.getElementById('commissioningForm');
        const notesField = document.getElementById('notes');

        if (!form) {
            console.error('Form #commissioningForm NOT FOUND');
            return;
        }

        if (!notesField) {
            console.error('Notes field #notes NOT FOUND');
            return;
        }

        console.log('✓ Form found:', form.action);
        console.log('✓ Notes field found');
        console.log('  - name:', notesField.name);
        console.log('  - disabled:', notesField.disabled);
        console.log('  - readonly:', notesField.readOnly);
        console.log('  - form:', notesField.form ? 'linked' : 'NOT LINKED');

        // Monitor value changes
        notesField.addEventListener('input', function() {
            console.log('Notes changed:', this.value.substring(0, 50));
        });

        // Monitor form submit
        form.addEventListener('submit', function(e) {
            console.log('=== FORM SUBMITTING ===');
            console.log('Notes value:', notesField.value);
            console.log('Notes will be sent:', notesField.value !== '');

            // Log all form data
            const formData = new FormData(form);
            console.log('Form data entries:');
            let notesFound = false;
            for (let [key, value] of formData.entries()) {
                if (key === 'notes') {
                    console.log('  ✓ notes:', value);
                    notesFound = true;
                }
            }

            // Check if notes is in FormData
            if (!notesFound) {
                console.error('  ✗ notes NOT in FormData!');
            }
        });
    });
</script>

<!-- DEBUG: Dropdowns and Buttons Check -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== DROPDOWNS & BUTTONS DEBUG ===');

        // Check dropdowns
        setTimeout(() => {
            const epcSelect = document.getElementById('epc_id');
            const repSelect = document.getElementById('representative_id');
            const commRespSelect = document.getElementById('commissioning_responsible_id');

            console.log('EPC Dropdown:', {
                exists: !!epcSelect,
                optionsCount: epcSelect ? epcSelect.options.length : 0,
                selectedValue: epcSelect ? epcSelect.value : 'N/A',
                dataSelected: epcSelect ? epcSelect.dataset.selected : 'N/A'
            });

            console.log('Representative Dropdown:', {
                exists: !!repSelect,
                optionsCount: repSelect ? repSelect.options.length : 0,
                selectedValue: repSelect ? repSelect.value : 'N/A',
                dataSelected: repSelect ? repSelect.dataset.selected : 'N/A'
            });

            console.log('Commissioning Responsible Dropdown:', {
                exists: !!commRespSelect,
                optionsCount: commRespSelect ? commRespSelect.options.length : 0,
                selectedValue: commRespSelect ? commRespSelect.value : 'N/A',
                dataSelected: commRespSelect ? commRespSelect.dataset.selected : 'N/A'
            });

            // Check Add buttons
            const addEpcBtn = document.getElementById('add-epc-btn');
            const addRepBtn = document.getElementById('add-representative-btn');
            const addCommRespBtn = document.getElementById('add-commissioning-responsible-btn');

            console.log('Add Buttons Status:', {
                addEpc: addEpcBtn ? (addEpcBtn.disabled ? '✗ DISABLED' : '✓ ENABLED') : '✗ NOT FOUND',
                addRep: addRepBtn ? (addRepBtn.disabled ? '✗ DISABLED' : '✓ ENABLED') : '✗ NOT FOUND',
                addCommResp: addCommRespBtn ? (addCommRespBtn.disabled ? '✗ DISABLED' : '✓ ENABLED') : '✗ NOT FOUND'
            });

            // Force enable all Add buttons for testing
            if (addEpcBtn) {
                addEpcBtn.disabled = false;
                addEpcBtn.title = 'Add New EPC Company';
            }
            if (addRepBtn) {
                addRepBtn.disabled = false;
                addRepBtn.title = 'Add New Representative';
            }
            if (addCommRespBtn) {
                addCommRespBtn.disabled = false;
                addCommRespBtn.title = 'Add New Commissioning Responsible';
            }

            console.log('✓ All Add buttons have been force-enabled');

        }, 1000); // Wait 1 second for AJAX to complete
    });
</script>

<!-- Modal Functions for Adding Brands/Models -->
<script>
    // Modal functions for meter brands/models
    // function showAddMeterBrandModal() {
    //     // (DESATIVADO: usar apenas o modal de main.js para evitar conflitos)
    // }

    function showAddMeterModelModal() {
        const brandSelect = document.getElementById('meter_brand');
        const selectedBrandId = brandSelect ? brandSelect.value : null;
        const selectedBrandName = brandSelect && selectedBrandId ? brandSelect.options[brandSelect.selectedIndex].text : '';

        if (!selectedBrandId) {
            alert('Please select a brand first');
            return;
        }

        const modalHtml = `
            <div class="modal fade" id="addMeterModelModal" tabindex="-1" aria-labelledby="addMeterModelModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addMeterModelModalLabel">Add New Model for ${selectedBrandName}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new_meter_model_name" class="form-label">Model Name</label>
                                <input type="text" class="form-control" id="new_meter_model_name" placeholder="Enter model name">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveMeterModelBtn">Save Model</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('addMeterModelModal'));
        modal.show();

        document.getElementById('saveMeterModelBtn').addEventListener('click', function() {
            const modelName = document.getElementById('new_meter_model_name').value.trim();
            if (!modelName) {
                alert('Please enter a model name');
                return;
            }

            fetch((window.BASE_URL || '') + 'ajax/manage_smart_meters.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=create_model&brand_id=' + encodeURIComponent(selectedBrandId) + '&model_name=' + encodeURIComponent(modelName)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modelId = data.id || data.model_id || null;
                        localStorage.setItem('cw_models_updated', JSON.stringify({
                            type: 'meter',
                            brand_id: selectedBrandId,
                            model_id: modelId,
                            model_name: modelName
                        }));
                        // Hide modal and remove after hidden to ensure proper cleanup
                        modal.hide();
                        const modalEl = document.getElementById('addMeterModelModal');
                        if (modalEl) {
                            modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                                modalEl.removeEventListener('hidden.bs.modal', cleanup);
                                modalEl.remove();
                            });
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the model');
                });
        });
    }

    // Attach event listeners for meter buttons
    document.addEventListener('DOMContentLoaded', function() {
        const addMeterBrandBtn = document.getElementById('add-meter-brand-btn');
        const addMeterModelBtn = document.getElementById('add-meter-model-btn');

        if (addMeterBrandBtn) {
            addMeterBrandBtn.addEventListener('click', function() {
                showAddBrandModal('meter');
            });
        }
        if (addMeterModelBtn) {
            addMeterModelBtn.addEventListener('click', showAddMeterModelModal);
        }

        // Initialize equipment dropdowns
        if (typeof initEquipmentDropdowns === 'function') {
            initEquipmentDropdowns();
        }
    });

    // Attach event listeners for energy meter buttons
    document.addEventListener('DOMContentLoaded', function() {
        const addEnergyMeterBrandBtn = document.getElementById('add-energy-meter-brand-btn');
        const addEnergyMeterModelBtn = document.getElementById('add-energy-meter-model-btn');

        if (addEnergyMeterBrandBtn) {
            addEnergyMeterBrandBtn.addEventListener('click', function() {
                showAddBrandModal('energy_meter');
            });
        }
        if (addEnergyMeterModelBtn) {
            // Use the JavaScript function instead of the PHP one
            addEnergyMeterModelBtn.addEventListener('click', function() {
                showAddModelModal('energy_meter');
            });
        }
    });
</script>

<!-- Leaflet for Location Map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Turf.js/6.5.0/turf.min.js"></script>
<script>
    (function() {
        function formatLatLng(latlng) {
            return latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6);
        }

        function initCommMap() {
            var mapEl = document.getElementById('comm_map');
            if (!mapEl) return;

            var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            });

            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: '© Esri'
            });

            var map = L.map('comm_map', {
                center: [39.5, -8.0],
                zoom: 6,
                layers: [osm]
            });
            window._comm_map = map;

            // Helper to render existing polygon from hidden input value
            function renderExistingPolygon() {
                try {
                    var existingCoordsInput = document.getElementById('comm_existing_polygon_coords');
                    if (!existingCoordsInput) return;
                    var val = existingCoordsInput.value;
                    // Clear previous polygon
                    drawnItems.clearLayers();
                    window.currentMapPolygonCoords = null;
                    if (!val) {
                        return;
                    }
                    var existingCoords = JSON.parse(val);
                    if (Array.isArray(existingCoords) && existingCoords.length) {
                        var existingPol = L.polygon(existingCoords).addTo(map);
                        drawnItems.addLayer(existingPol);
                        // compute area if turf available
                        try {
                            var geojson = existingPol.toGeoJSON();
                            var area = turf.area(geojson);
                            document.getElementById('comm_map_area_m2').value = area.toFixed(2);
                            window.currentMapPolygonCoords = existingCoords;
                        } catch (e) {}
                        map.fitBounds(existingPol.getBounds());
                    }
                } catch (e) {
                    /* ignore */
                }
            }

            // Load existing polygon if provided on the report
            try {
                renderExistingPolygon();
                var existingCoordsInput = document.getElementById('comm_existing_polygon_coords');
                if (existingCoordsInput) {
                    existingCoordsInput.addEventListener('change', function() {
                        renderExistingPolygon();
                    });
                }
            } catch (e) {
                /* ignore */
            }

            var clickMarker = null;
            var drawnItems = new L.FeatureGroup();
            var drawControl = new L.Control.Draw({
                edit: {
                    featureGroup: drawnItems
                },
                draw: {
                    polygon: true,
                    polyline: false,
                    rectangle: true,
                    circle: false,
                    marker: false
                }
            });
            map.addLayer(drawnItems);
            map.addControl(drawControl);
            var gpsInput = document.getElementById('gps');

            function computeAzimuth(lat1, lon1, lat2, lon2) {
                var toRad = Math.PI / 180;
                var toDeg = 180 / Math.PI;
                var φ1 = lat1 * toRad;
                var φ2 = lat2 * toRad;
                var Δλ = (lon2 - lon1) * toRad;
                var y = Math.sin(Δλ) * Math.cos(φ2);
                var x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
                var θ = Math.atan2(y, x);
                var bearing = (θ * toDeg + 360) % 360;
                return bearing;
            }

            var clickPoints = [];

            function goToCoords(coordStr) {
                if (!coordStr) return false;
                var parts = coordStr.split(/[\s,;]+/).map(function(s) {
                    return s.trim();
                }).filter(Boolean);
                if (parts.length >= 2) {
                    var la = parseFloat(parts[0]);
                    var lo = parseFloat(parts[1]);
                    if (!Number.isNaN(la) && !Number.isNaN(lo) && la >= -90 && la <= 90 && lo >= -180 && lo <= 180) {
                        if (clickMarker) map.removeLayer(clickMarker);
                        map.setView([la, lo], 17);
                        clickMarker = L.marker([la, lo], {
                            draggable: true
                        }).addTo(map);
                        clickMarker.on('dragend', function(e) {
                            var ll = e.target.getLatLng();
                            if (gpsInput) gpsInput.value = formatLatLng(ll);
                        });
                        return true;
                    }
                }
                return false;
            }

            // Initial load
            if (gpsInput && gpsInput.value) {
                goToCoords(gpsInput.value);
                var commGpsInputInit = document.getElementById('comm_map_gps');
                if (commGpsInputInit) commGpsInputInit.value = gpsInput.value;
            }

            // Listen for input changes
            if (gpsInput) {
                gpsInput.addEventListener('change', function() {
                    goToCoords(this.value);
                });
                gpsInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') goToCoords(this.value);
                });
            }

            // Draw created handler
            map.on(L.Draw.Event.CREATED, function(e) {
                var layer = e.layer;
                drawnItems.clearLayers();
                drawnItems.addLayer(layer);
                // compute area using turf if available
                try {
                    var geojson = layer.toGeoJSON();
                    var area = turf.area(geojson); // in m^2
                    document.getElementById('comm_map_area_m2').value = area.toFixed(2);
                    // Save polygon coords as [lat,lng] pairs
                    if (geojson.geometry && geojson.geometry.coordinates && geojson.geometry.coordinates[0]) {
                        window.currentMapPolygonCoords = geojson.geometry.coordinates[0].map(function(coord) {
                            return [coord[1], coord[0]];
                        });
                        document.getElementById('comm_existing_polygon_coords').value = JSON.stringify(window.currentMapPolygonCoords);
                    }
                } catch (err) {
                    document.getElementById('comm_map_area_m2').value = '';
                    window.currentMapPolygonCoords = null;
                }
            });

            // Click on map to set GPS
            map.on('click', function(ev) {
                var latlng = ev.latlng;
                if (clickMarker) map.removeLayer(clickMarker);
                clickMarker = L.marker(latlng, {
                    draggable: true
                }).addTo(map);
                if (gpsInput) {
                    gpsInput.value = formatLatLng(latlng);
                }
                var commGpsInput = document.getElementById('comm_map_gps');
                if (commGpsInput) commGpsInput.value = formatLatLng(latlng);
                // track points for azimuth; keep last two
                clickPoints.push([latlng.lat, latlng.lng]);
                if (clickPoints.length > 2) clickPoints.shift();
                if (clickPoints.length === 2) {
                    var a = clickPoints[0],
                        b = clickPoints[1];
                    var az = computeAzimuth(a[0], a[1], b[0], b[1]);
                    document.getElementById('comm_map_azimuth_deg').value = az.toFixed(1);
                }
                // when user clicks, clear drawn polygon (unless they want to draw it)
                // (we don't want the click to automatically define a polygon)
                clickMarker.on('dragend', function(e) {
                    var ll = e.target.getLatLng();
                    if (gpsInput) {
                        gpsInput.value = formatLatLng(ll);
                    }
                    var commGpsInput = document.getElementById('comm_map_gps');
                    if (commGpsInput) commGpsInput.value = formatLatLng(ll);
                });
            });

            // Clear button
            document.getElementById('comm_map_clear').addEventListener('click', function() {
                if (clickMarker) {
                    map.removeLayer(clickMarker);
                    clickMarker = null;
                }
                if (gpsInput) gpsInput.value = '';
                if (drawnItems) drawnItems.clearLayers();
                document.getElementById('comm_map_area_m2').value = '';
                var commGpsInput = document.getElementById('comm_map_gps');
                if (commGpsInput) commGpsInput.value = '';
                document.getElementById('comm_map_azimuth_deg').value = '';
                window.currentMapPolygonCoords = null;
                document.getElementById('comm_existing_polygon_coords').value = '';
            });

            // Satellite toggle
            var satBtn = document.getElementById('comm_map_toggle_sat');
            satBtn.addEventListener('click', function() {
                if (map.hasLayer(satellite)) {
                    map.removeLayer(satellite);
                    map.addLayer(osm);
                    satBtn.classList.remove('active');
                } else {
                    map.addLayer(satellite);
                    map.removeLayer(osm);
                    satBtn.classList.add('active');
                }
            });

            // Fix map size when General tab shown
            var genTab = document.getElementById('general-tab');
            if (genTab) {
                genTab.addEventListener('click', function() {
                    setTimeout(function() {
                        map.invalidateSize();
                    }, 300);
                });
            }
            setTimeout(function() {
                map.invalidateSize();
            }, 500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCommMap);
        } else {
            initCommMap();
        }
    })();
</script>


<?php
// Include footer
include 'includes/footer.php';
?>