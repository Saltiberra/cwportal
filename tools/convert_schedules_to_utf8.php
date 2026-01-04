<?php

/**
 * convert_schedules_to_utf8.php
 * Helper script to detect and attempt to repair mis-encoded text in schedules table.
 *
 * Usage: php tools/convert_schedules_to_utf8.php
 * Note: This script only previews changes unless you run with ?update=1 in the web or set $doUpdate = true.
 * Always backup your DB BEFORE running.
 */

require_once __DIR__ . '/../config/database.php';

$doUpdate = false; // Change to true to apply changes.
// Or allow via CLI flag
if (in_array('--apply', $argv)) $doUpdate = true;

// Columns to check
$columns = ['title', 'project_name', 'location', 'description'];

$stmt = $pdo->query('SELECT id, title, project_name, location, description FROM schedules');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$changed = [];
foreach ($rows as $r) {
    $id = $r['id'];
    $needUpdate = [];

    foreach ($columns as $col) {
        $val = $r[$col];
        if ($val === null || $val === '') continue;

        // If it's valid UTF-8, skip
        if (mb_check_encoding($val, 'UTF-8')) continue;

        // Try to convert from latin1 (ISO-8859-1) to UTF-8
        $converted = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        if (mb_check_encoding($converted, 'UTF-8') && $converted !== $val) {
            // Heuristic: if converted has less '?' than original, prefer it
            $origQ = substr_count($val, '?');
            $convQ = substr_count($converted, '?');
            if ($convQ <= $origQ) {
                $needUpdate[$col] = $converted;
            }
        }
    }

    if (!empty($needUpdate)) {
        $changed[$id] = $needUpdate;
        echo "Row $id would be updated:\n";
        foreach ($needUpdate as $c => $v) {
            echo " - $c => $v\n";
        }

        if ($doUpdate) {
            $updates = [];
            $params = [':id' => $id];
            foreach ($needUpdate as $c => $v) {
                $updates[] = "`$c` = :$c";
                $params[":$c"] = $v;
            }
            $sql = 'UPDATE schedules SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmtUpd = $pdo->prepare($sql);
            $stmtUpd->execute($params);
            echo " -> Updated row $id\n";
        }
    }
}

echo "Done. Found " . count($changed) . " rows with potential encoding fixes.\n";

if (!$doUpdate) echo "Run with --apply to actually write changes (make sure you have a DB backup!).\n";
