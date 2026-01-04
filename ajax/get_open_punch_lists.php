<?php

/**
 * GET OPEN PUNCH LISTS ENDPOINT
 * 
 * Returns all open punch lists from all reports
 * with information about: project_name, epc_company, severity_level, description
 * 
 * Response: JSON array of open punch items
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to JSON output
error_log('[PUNCH_LIST_API] Endpoint called');

require_once '../includes/auth.php';
require_once '../config/database.php';

// 🔒 Require authentication
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    error_log('[PUNCH_LIST_API] Starting query...');
    // Build dynamic SQL based on existing columns to support multiple schema variants
    error_log('[PUNCH_LIST_API] Inspecting table columns...');
    $colsStmt = $pdo->query("SHOW COLUMNS FROM report_punch_list");
    if (!$colsStmt) {
        throw new Exception('Could not obtain columns from report_punch_list');
    }
    $cols = array_map(function ($r) {
        return $r['Field'];
    }, $colsStmt->fetchAll(PDO::FETCH_ASSOC));
    error_log('[PUNCH_LIST_API] Columns found: ' . implode(', ', $cols));

    $descCol   = in_array('issue_description', $cols) ? 'issue_description' : (in_array('description', $cols) ? 'description' : null);
    $prioCol   = in_array('issue_priority', $cols) ? 'issue_priority' : (in_array('severity_level', $cols) ? 'severity_level' : (in_array('priority', $cols) ? 'priority' : null));
    $statusCol = in_array('issue_status', $cols) ? 'issue_status' : (in_array('status', $cols) ? 'status' : (in_array('is_completed', $cols) ? 'is_completed' : null));
    // Optional resolution date columns (used as open/closed criteria when status is missing)
    $resDateCol = null;
    foreach (['resolution_date', 'resolved_at', 'closed_at', 'completed_at', 'date_resolved'] as $c) {
        if (in_array($c, $cols)) {
            $resDateCol = $c;
            break;
        }
    }
    $assignCol = in_array('assigned_to', $cols) ? 'assigned_to' : null;

    // Commissioning reports columns mapping (project_name, epc_company, installation_address)
    $crColsStmt = $pdo->query("SHOW COLUMNS FROM commissioning_reports");
    if (!$crColsStmt) {
        throw new Exception('Could not obtain columns from commissioning_reports');
    }
    $crCols = array_map(function ($r) {
        return $r['Field'];
    }, $crColsStmt->fetchAll(PDO::FETCH_ASSOC));

    $crProjectCol = null;
    foreach (['project_name', 'project', 'name', 'project_title', 'site_name'] as $c) {
        if (in_array($c, $crCols)) {
            $crProjectCol = $c;
            break;
        }
    }
    $crEpcCol = null;
    foreach (['epc_company', 'epc', 'installer_company', 'contractor', 'company', 'client_company'] as $c) {
        if (in_array($c, $crCols)) {
            $crEpcCol = $c;
            break;
        }
    }
    $crHasEpcId = in_array('epc_id', $crCols);
    $crAddrCol = null;
    foreach (['installation_address', 'address', 'site_address', 'installation_location', 'location'] as $c) {
        if (in_array($c, $crCols)) {
            $crAddrCol = $c;
            break;
        }
    }
    $crHasUserId = in_array('user_id', $crCols);

    $selectParts = [
        'pl.id',
        'pl.report_id',
        ($descCol ? "pl.$descCol AS description" : "'' AS description"),
        ($prioCol ? "pl.$prioCol AS severity_level" : "'Minor' AS severity_level"),
        'pl.created_at',
        ($crProjectCol ? "cr.$crProjectCol AS project_name" : "'N/A' AS project_name"),
        // Prefer JOIN with epcs when cr has epc_id
        ($crHasEpcId ? "COALESCE(e.name, 'N/A') AS epc_company" : ($crEpcCol ? "cr.$crEpcCol AS epc_company" : "'N/A' AS epc_company")),
        ($crAddrCol ? "cr.$crAddrCol AS installation_address" : "'N/A' AS installation_address"),
        ($crHasUserId ? 'u.username as created_by' : "NULL as created_by")
    ];
    $selectParts[] = $assignCol ? "pl.$assignCol AS assigned_to" : "NULL AS assigned_to";

    $selectSql = implode(",\n                ", $selectParts);

    // Open criteria:
    // - If we have a status column: open when status not in Closed variants OR resolution date is empty
    // - If we don't have status: open when resolution date empty (or no column available => include)
    if ($statusCol === 'is_completed') {
        $statusOpen = "(pl.is_completed = 0 OR pl.is_completed IS NULL)";
        $resOpen = $resDateCol ? "(pl.$resDateCol IS NULL OR pl.$resDateCol = '' OR pl.$resDateCol = '0000-00-00')" : '0';
        $whereSql = $resDateCol ? "(($statusOpen) OR $resOpen)" : $statusOpen;
    } elseif ($statusCol) {
        $statusOpen = "COALESCE(pl.$statusCol, 'Open') NOT IN ('Closed','Concluído','Fechado')";
        $resOpen = $resDateCol ? "(pl.$resDateCol IS NULL OR pl.$resDateCol = '' OR pl.$resDateCol = '0000-00-00')" : '0';
        $whereSql = $resDateCol ? "(($statusOpen) OR $resOpen)" : $statusOpen;
    } else {
        if ($resDateCol) {
            $whereSql = "(pl.$resDateCol IS NULL OR pl.$resDateCol = '' OR pl.$resDateCol = '0000-00-00')";
        } else {
            $whereSql = '1=1';
        }
    }

    // Exclude punch lists from deleted reports
    $whereSql .= " AND (cr.is_deleted = FALSE OR cr.is_deleted IS NULL)";

    // Exclude deleted punch list items
    $whereSql .= " AND (pl.is_deleted = FALSE OR pl.is_deleted IS NULL)";

    if ($prioCol) {
        // Order by IEC-like priority (High / Medium / Low) while keeping legacy labels compatible
        $orderSql = "CASE\n                    WHEN LOWER(COALESCE(pl.$prioCol,'low')) IN ('high','severe','critical') THEN 1\n                    WHEN LOWER(COALESCE(pl.$prioCol,'low')) IN ('medium','major') THEN 2\n                    WHEN LOWER(COALESCE(pl.$prioCol,'low')) IN ('low','minor','') THEN 3\n                    ELSE 4\n                END, pl.created_at DESC";
    } else {
        $orderSql = 'pl.created_at DESC';
    }

    $sql = "SELECT\n                $selectSql\n            FROM report_punch_list pl\n            INNER JOIN commissioning_reports cr ON pl.report_id = cr.id\n            " . ($crHasEpcId ? "LEFT JOIN epcs e ON cr.epc_id = e.id\n            " : "") . ($crHasUserId ? "LEFT JOIN users u ON cr.user_id = u.id\n            " : "") .
        "WHERE $whereSql\n            ORDER BY $orderSql";
    error_log('[PUNCH_LIST_API] Final SQL built: ' . $sql);
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparing dynamic query: ' . implode(', ', $pdo->errorInfo()));
    }
    $stmt->execute();
    $openPunchLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log('[PUNCH_LIST_API] Found ' . count($openPunchLists) . ' punch items in report_punch_list');

    // Process and format the response (primary source: report_punch_list)
    $formattedData = array_map(function ($item) {
        // Normalize priority/severity to IEC-style: High / Medium / Low
        $sev = $item['severity_level'] ?? '';
        $sevLower = strtolower((string)$sev);
        $sevNorm = 'Low';
        if (in_array($sevLower, ['high', 'severe', 'critical'])) $sevNorm = 'High';
        elseif (in_array($sevLower, ['medium', 'major'])) $sevNorm = 'Medium';

        return [
            'id' => (int)$item['id'],
            'report_id' => (int)$item['report_id'],
            'project_name' => $item['project_name'] ?? 'N/A',
            'epc_company' => $item['epc_company'] ?? 'N/A',
            'location' => $item['installation_address'] ?? 'N/A',
            'description' => $item['description'] ?? '',
            'severity_level' => $sevNorm,
            'created_by' => $item['created_by'] ?? 'Unknown',
            'created_at' => $item['created_at'] ?? '',
            'assigned_to' => $item['assigned_to'] ?? 'Unassigned',
            'source' => 'sql',
            'closable' => true
        ];
    }, $openPunchLists);

    // ----------------------------------------------------------------------------
    // SECONDARY SOURCE: Legacy items stored in report_equipment (equipment_type='Punch List Item')
    // These rows are parsed from the free-text 'characteristics' column. They are
    // considered OPEN when Resolution Date is empty/'-'/'0000-00-00' and no explicit
    // Status: Closed flag is present.
    // ----------------------------------------------------------------------------
    try {
        $reColsStmt = $pdo->query("SHOW COLUMNS FROM report_equipment");
        $reCols = array_map(function ($r) {
            return $r['Field'];
        }, $reColsStmt->fetchAll(PDO::FETCH_ASSOC));
        $hasCreatedAt = in_array('created_at', $reCols);
        $hasCharacteristics = in_array('characteristics', $reCols);
        $hasEquipmentType = in_array('equipment_type', $reCols);
        $hasReportId = in_array('report_id', $reCols);

        if ($hasCharacteristics && $hasEquipmentType && $hasReportId) {
            $reSelect = "SELECT re.id, re.report_id, re.characteristics" . ($hasCreatedAt ? ", re.created_at" : ", NULL as created_at") .
                ", cr." . ($crProjectCol ?: "id") . " AS project_name, " .
                ($crHasEpcId ? "COALESCE(e.name, 'N/A')" : ($crEpcCol ? "cr.$crEpcCol" : "'N/A'")) . " AS epc_company, " .
                ($crAddrCol ? "cr.$crAddrCol" : "'N/A'") . " AS installation_address
             FROM report_equipment re
             INNER JOIN commissioning_reports cr ON re.report_id = cr.id " .
                ($crHasEpcId ? "LEFT JOIN epcs e ON cr.epc_id = e.id " : "") .
                // Exclude legacy punch items from reports that were soft-deleted
                "WHERE re.equipment_type = 'Punch List Item' AND (cr.is_deleted = FALSE OR cr.is_deleted IS NULL)";

            error_log('[PUNCH_LIST_API] Loading legacy equipment punch items...');
            $reStmt = $pdo->prepare($reSelect);
            $reStmt->execute();
            $legacyRows = $reStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('[PUNCH_LIST_API] Found ' . count($legacyRows) . ' legacy punch items in report_equipment');

            foreach ($legacyRows as $row) {
                $chars = $row['characteristics'] ?? '';
                // Fast parse helper
                $getVal = function ($label) use ($chars) {
                    $pattern = '/' . preg_quote($label, '/') . '\s*:\s*([^|]+)/i';
                    if (preg_match($pattern, $chars, $m)) {
                        return trim($m[1]);
                    }
                    return '';
                };

                $desc = $getVal('Description');
                if ($desc === '') {
                    // try Portuguese variants
                    $desc = $getVal('Description');
                }
                $sev = $getVal('Severity');
                $statusText = $getVal('Status');
                $resolutionDate = $getVal('Resolution Date');
                if ($resolutionDate === '') {
                    $resolutionDate = $getVal('Data de Resolução');
                }
                $openingDate = $getVal('Opening Date');
                $responsible = $getVal('Responsible');

                // Normalize legacy severity to canonical IEC-style: High / Medium / Low
                $sevNorm = 'Low';
                $sevLower = strtolower($sev);
                if (in_array($sevLower, ['high', 'alto', 'major', 'severe', 'severo', 'critical', 'crítico', 'critico'])) {
                    $sevNorm = 'High';
                } elseif (in_array($sevLower, ['medium', 'médio', 'medio'])) {
                    $sevNorm = 'Medium';
                } elseif (in_array($sevLower, ['minor', 'baixo', 'low'])) {
                    $sevNorm = 'Low';
                }

                // Determine OPEN/CLOSED
                $statusLower = strtolower($statusText);
                $isExplicitClosed = ($statusLower === 'closed' || $statusLower === 'concluído' || $statusLower === 'fechado' || $statusLower === 'resolved');
                $dateEmpty = ($resolutionDate === '' || $resolutionDate === '-' || $resolutionDate === '0000-00-00' || $resolutionDate === 'null');
                $isOpen = !$isExplicitClosed && $dateEmpty; // open if no resolution date and no "Status: Closed"

                if ($isOpen) {
                    $formattedData[] = [
                        'id' => (int)(-1 * (int)$row['id']), // negative id to avoid collision
                        'report_id' => (int)$row['report_id'],
                        'project_name' => $row['project_name'] ?? 'N/A',
                        'epc_company' => $row['epc_company'] ?? 'N/A',
                        'location' => $row['installation_address'] ?? 'N/A',
                        'description' => $desc,
                        'severity_level' => $sevNorm,
                        'created_by' => 'Unknown',
                        'created_at' => $row['created_at'] ?? ($openingDate ?: ''),
                        'assigned_to' => $responsible ?: 'Unassigned',
                        'source' => 'equipment',
                        'closable' => false
                    ];
                }
            }
        } else {
            error_log('[PUNCH_LIST_API] report_equipment does not have expected columns; skipping legacy source.');
        }
    } catch (Exception $e2) {
        error_log('[PUNCH_LIST_API] Legacy equipment punch list fetch failed: ' . $e2->getMessage());
        // continue with primary source only
    }

    // Sort by canonical severity (High -> Medium -> Low) then date
    usort($formattedData, function ($a, $b) {
        $prio = ['High' => 1, 'Medium' => 2, 'Low' => 3, 'Severe' => 1, 'Critical' => 1, 'Major' => 2, 'Minor' => 3];
        $pa = $prio[$a['severity_level']] ?? 4;
        $pb = $prio[$b['severity_level']] ?? 4;
        if ($pa === $pb) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        }
        return $pa <=> $pb;
    });

    error_log('[PUNCH_LIST_API] Returning ' . count($formattedData) . ' total formatted items (including legacy)');

    echo json_encode([
        'success' => true,
        'count' => count($formattedData),
        'data' => $formattedData
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[PUNCH_LIST_API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
