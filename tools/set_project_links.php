<?php
// tools/set_project_links.php
// Usage: php tools/set_project_links.php <project_id> <project_plan_url> <pv_solution_url> <sld_url>
require_once __DIR__ . '/../config/database.php';

$id = intval($argv[1] ?? 0);
$plan = $argv[2] ?? '';
$pv = $argv[3] ?? '';
$sld = $argv[4] ?? '';

if ($id <= 0) {
    echo "Usage: php tools/set_project_links.php <project_id> <project_plan_url> <pv_solution_url> <sld_url>\n";
    exit(1);
}

// Check columns exist
$cols = ['project_plan_url', 'pv_solution_url', 'sld_url'];
foreach ($cols as $c) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'field_supervision_project' AND column_name = ?");
    $stmt->execute([$c]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "Missing column {$c}. Please run the migration first.\n";
        exit(2);
    }
}
$stmt = $pdo->prepare('UPDATE field_supervision_project SET project_plan_url = ?, pv_solution_url = ?, sld_url = ? WHERE id = ?');
$stmt->execute([$plan ?: null, $pv ?: null, $sld ?: null, $id]);

// Basic validation for CLI input (ensure valid URLs or empty)
foreach (['plan' => $plan, 'pv' => $pv, 'sld' => $sld] as $k => $v) {
    if ($v !== '' && filter_var($v, FILTER_VALIDATE_URL) === false) {
        echo "Invalid URL passed for {$k}: {$v}\n";
        exit(3);
    }
}

// perform update
$stmt = $pdo->prepare('UPDATE field_supervision_project SET project_plan_url = ?, pv_solution_url = ?, sld_url = ? WHERE id = ?');
$stmt->execute([$plan ?: null, $pv ?: null, $sld ?: null, $id]);

echo "Updated project {$id} with links.\n";
