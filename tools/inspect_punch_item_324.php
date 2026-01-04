<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
// When running under the CLI for debugging, bypass HTTP login redirect
if (PHP_SAPI !== 'cli') {
    requireLogin();
} else {
    // Ensure a sensible session context exists for logging helpers
    if (session_status() === PHP_SESSION_NONE) session_start();
    echo "[DEBUG] Running in CLI mode: skipping requireLogin()\n";
}

$reportId = 324;
$needle = 'PL014';

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
}
echo "Inspecting punch list data for report_id={$reportId} and needle={$needle}\n\n";

try {
    // 1) Check report_punch_list for matching descriptions or ids (detect columns dynamically)
    $colsStmt = $pdo->query("SHOW COLUMNS FROM report_punch_list");
    $cols = $colsStmt ? array_map(function ($r) {
        return $r['Field'];
    }, $colsStmt->fetchAll(PDO::FETCH_ASSOC)) : [];
    $descCol = in_array('issue_description', $cols) ? 'issue_description' : (in_array('description', $cols) ? 'description' : null);
    $prioCol = in_array('issue_priority', $cols) ? 'issue_priority' : (in_array('severity_level', $cols) ? 'severity_level' : (in_array('priority', $cols) ? 'priority' : null));

    $selectParts = ['id', 'report_id', 'created_at'];
    $selectParts[] = $descCol ? "$descCol AS description" : "'' AS description";
    $selectParts[] = $prioCol ? "$prioCol AS priority" : "'' AS priority";

    $sql = 'SELECT ' . implode(',', $selectParts) . ' FROM report_punch_list WHERE report_id = ? ORDER BY id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reportId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "report_punch_list rows: " . count($rows) . "\n";
    foreach ($rows as $r) {
        $desc = $r['description'] ?? '';
        echo "id={$r['id']} priority=" . ($r['priority'] ?? 'N/A') . " created_at={$r['created_at']} desc=" . substr($desc, 0, 120) . "\n";
    }

    echo "\nChecking report_equipment legacy punch items...\n";
    $stmt2 = $pdo->prepare("SELECT id, report_id, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type IN ('Punch List Item','Punch List Item (migrated)') ORDER BY id");
    $stmt2->execute([$reportId]);
    $erows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "report_equipment rows: " . count($erows) . "\n";
    foreach ($erows as $r) {
        echo "id={$r['id']} chars=" . substr($r['characteristics'], 0, 200) . "\n";
    }

    echo "\nChecking report_drafts form_data for punch_list_data...\n";
    $stmt3 = $pdo->prepare("SELECT id, session_id, updated_at, form_data FROM report_drafts WHERE report_id = ? ORDER BY updated_at DESC LIMIT 5");
    $stmt3->execute([$reportId]);
    $drows = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "draft rows: " . count($drows) . "\n";
    foreach ($drows as $d) {
        $s = substr($d['form_data'], 0, 800);
        echo "draft id={$d['id']} session={$d['session_id']} updated_at={$d['updated_at']} form_data (start)=" . $s . "\n\n";
    }

    echo "\nDone.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
