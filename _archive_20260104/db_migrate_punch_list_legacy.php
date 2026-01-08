<?php

/**
 * Punch List Legacy Migration
 *
 * Migrates legacy punch list items stored in report_equipment (equipment_type='Punch List Item')
 * into the canonical report_punch_list table. Safe to re-run: it skips rows already migrated
 * (equipment_type changed to 'Punch List Item (migrated)') and avoids duplicates by checking
 * for an existing punch list row with same report_id + description.
 *
 * Usage:
 * - Dry run (default):  /cleanwattsportal/db_migrate_punch_list_legacy.php
 * - Execute migration:  /cleanwattsportal/db_migrate_punch_list_legacy.php?run=1
 * - JSON output:        add &format=json
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$asJson = isset($_GET['format']) && strtolower($_GET['format']) === 'json';
$doRun  = isset($_GET['run']) && $_GET['run'] == '1';

function respond($payload, $asJson)
{
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Punch List Migration</title>';
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
        echo '</head><body class="p-4">';
        echo '<div class="container">';
        echo '<h3 class="mb-3">Punch List Legacy Migration</h3>';
        echo '<div class="mb-3">Mode: <span class="badge ' . ($payload['run'] ? 'bg-success' : 'bg-secondary') . '">' . ($payload['run'] ? 'RUN' : 'DRY-RUN') . '</span></div>';
        echo '<pre class="bg-light p-3 border rounded" style="white-space: pre-wrap">' . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<div class="mt-3">';
        if (!$payload['run']) {
            echo '<a class="btn btn-primary" href="db_migrate_punch_list_legacy.php?run=1">Run Migration</a> ';
        }
        echo '<a class="btn btn-outline-secondary" href="index.php">Back to Dashboard</a>';
        echo '</div>';
        echo '</div></body></html>';
    }
}

try {
    // Discover destination columns dynamically
    $colsStmt = $pdo->query("SHOW COLUMNS FROM report_punch_list");
    $plCols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $descCol = null;
    foreach (['issue_description', 'description'] as $c) if (in_array($c, $plCols)) {
        $descCol = $c;
        break;
    }
    $prioCol = null;
    foreach (['issue_priority', 'severity_level', 'priority'] as $c) if (in_array($c, $plCols)) {
        $prioCol = $c;
        break;
    }
    $statusCol = null;
    foreach (['issue_status', 'status', 'is_completed'] as $c) if (in_array($c, $plCols)) {
        $statusCol = $c;
        break;
    }
    $assignedCol = in_array('assigned_to', $plCols) ? 'assigned_to' : null;
    $createdAtCol = null;
    foreach (['created_at', 'created_on', 'date_created'] as $c) if (in_array($c, $plCols)) {
        $createdAtCol = $c;
        break;
    }
    $openDateCol = null;
    foreach (['opening_date', 'opened_at', 'date_opened'] as $c) if (in_array($c, $plCols)) {
        $openDateCol = $c;
        break;
    }
    $resDateCol = null;
    foreach (['resolution_date', 'resolved_at', 'closed_at', 'date_resolved'] as $c) if (in_array($c, $plCols)) {
        $resDateCol = $c;
        break;
    }

    // Load legacy items
    $legacySql = "SELECT id, report_id, characteristics FROM report_equipment WHERE equipment_type = 'Punch List Item'";
    $legacyStmt = $pdo->query($legacySql);
    $legacyRows = $legacyStmt ? $legacyStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $migrated = [];
    $skipped  = [];
    $errors   = [];

    // Helper to parse a value from characteristics string
    $parse = function ($chars, $label) {
        $pattern = '/' . preg_quote($label, '/') . '\s*:\s*([^|]+)/i';
        if (preg_match($pattern, $chars, $m)) return trim($m[1]);
        return '';
    };

    foreach ($legacyRows as $row) {
        $chars = $row['characteristics'] ?? '';
        $desc = $parse($chars, 'Description');
        if ($desc === '') $desc = $parse($chars, 'Description');
        $sev  = $parse($chars, 'Severity');
        $open = $parse($chars, 'Opening Date');
        $resp = $parse($chars, 'Responsible');
        $res  = $parse($chars, 'Resolution Date');
        if ($res === '') $res = $parse($chars, 'Data de Resolução');
        $statusText = $parse($chars, 'Status');

        // Normalize severity
        $sevNorm = 'Low';
        $s = strtolower($sev);
        if (in_array($s, ['critical', 'crítico', 'critico', 'severe'])) $sevNorm = 'Critical';
        elseif (in_array($s, ['major', 'alto', 'high'])) $sevNorm = 'High';
        elseif (in_array($s, ['medium', 'médio', 'medio'])) $sevNorm = 'Medium';
        elseif (in_array($s, ['minor', 'baixo', 'low'])) $sevNorm = 'Low';

        // Determine open/closed
        $statusLower = strtolower($statusText);
        $isClosed = ($statusLower === 'closed' || $statusLower === 'concluído' || $statusLower === 'fechado' || $statusLower === 'resolved' || ($res && $res !== '-' && $res !== '0000-00-00'));

        // Check for existing punch with same description (best-effort dedupe)
        $exists = false;
        if ($descCol) {
            $chk = $pdo->prepare("SELECT id FROM report_punch_list WHERE report_id=? AND $descCol=? LIMIT 1");
            $chk->execute([$row['report_id'], $desc]);
            $exists = (bool)$chk->fetchColumn();
        }

        if ($exists) {
            $skipped[] = ['equipment_id' => (int)$row['id'], 'reason' => 'duplicate-desc', 'report_id' => (int)$row['report_id'], 'description' => $desc];
            // Even if duplicate, mark as migrated to avoid reprocessing later
            if ($doRun) {
                $pdo->prepare("UPDATE report_equipment SET equipment_type='Punch List Item (migrated)' WHERE id=? AND equipment_type='Punch List Item'")->execute([$row['id']]);
            }
            continue;
        }

        // Build destination insert dynamically
        $cols = ['report_id'];
        $vals = [$row['report_id']];
        $phs  = ['?'];

        if ($descCol) {
            $cols[] = $descCol;
            $vals[] = $desc;
            $phs[] = '?';
        }
        if ($prioCol) {
            $cols[] = $prioCol;
            $vals[] = $sevNorm;
            $phs[] = '?';
        }
        if ($assignedCol) {
            $cols[] = $assignedCol;
            $vals[] = $resp ?: null;
            $phs[] = '?';
        }
        if ($createdAtCol) {
            $cols[] = $createdAtCol;
            $vals[] = $open ?: date('Y-m-d H:i:s');
            $phs[] = '?';
        }
        if ($openDateCol) {
            $cols[] = $openDateCol;
            $vals[] = $open ?: null;
            $phs[] = '?';
        }
        if ($resDateCol) {
            $cols[] = $resDateCol;
            $vals[] = $res ?: null;
            $phs[] = '?';
        }
        if ($statusCol) {
            if ($statusCol === 'is_completed') {
                $cols[] = 'is_completed';
                $vals[] = $isClosed ? 1 : 0;
                $phs[] = '?';
            } else {
                $cols[] = $statusCol;
                $vals[] = $isClosed ? 'Closed' : 'Open';
                $phs[] = '?';
            }
        }

        $sql = 'INSERT INTO report_punch_list (' . implode(',', $cols) . ') VALUES (' . implode(',', $phs) . ')';

        if ($doRun) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($vals);
                $newId = (int)$pdo->lastInsertId();
                // Mark legacy row as migrated
                $pdo->prepare("UPDATE report_equipment SET equipment_type='Punch List Item (migrated)' WHERE id=? AND equipment_type='Punch List Item'")->execute([$row['id']]);
                $migrated[] = [
                    'equipment_id' => (int)$row['id'],
                    'new_punch_id' => $newId,
                    'report_id' => (int)$row['report_id'],
                    'description' => $desc,
                    'severity' => $sevNorm,
                    'status' => $isClosed ? 'Closed' : 'Open'
                ];
            } catch (Throwable $e) {
                $errors[] = ['equipment_id' => (int)$row['id'], 'error' => $e->getMessage(), 'sql' => $sql, 'values' => $vals];
            }
        } else {
            // DRY RUN preview
            $migrated[] = [
                'equipment_id' => (int)$row['id'],
                'preview_insert' => ['sql' => $sql, 'values' => $vals],
                'report_id' => (int)$row['report_id'],
                'description' => $desc,
                'severity' => $sevNorm,
                'status' => $isClosed ? 'Closed' : 'Open'
            ];
        }
    }

    $payload = [
        'run' => $doRun,
        'totals' => [
            'legacy_found' => count($legacyRows),
            'migrated' => count($migrated),
            'skipped'  => count($skipped),
            'errors'   => count($errors)
        ],
        'migrated_items' => $migrated,
        'skipped_items'  => $skipped,
        'errors' => $errors,
        'tips' => [
            "Re-run this page with ?run=1 to execute the migration.",
            "After migration, the dashboard will list all open items from report_punch_list.",
            "Legacy rows are marked as 'Punch List Item (migrated)' to avoid reprocessing."
        ]
    ];

    respond($payload, $asJson);
} catch (Throwable $e) {
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo 'Migration failed: ' . $e->getMessage();
    }
}
