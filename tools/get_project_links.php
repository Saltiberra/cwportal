<?php
// tools/get_project_links.php
// Usage: php tools/get_project_links.php <project_id>
require_once __DIR__ . '/../config/database.php';

$id = intval($argv[1] ?? 0);
if ($id <= 0) {
    echo "Usage: php tools/get_project_links.php <project_id>\n";
    exit(1);
}

$stmt = $pdo->prepare('SELECT id,title,project_plan_url,pv_solution_url,sld_url FROM field_supervision_project WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
    echo "Not found: project {$id}\n";
    exit(1);
}

echo 'Project: ' . $r['id'] . ' - ' . ($r['title'] ?? '') . PHP_EOL;
echo 'Project Plan: ' . ($r['project_plan_url'] ?? '') . PHP_EOL;
echo 'PV Solution: ' . ($r['pv_solution_url'] ?? '') . PHP_EOL;
echo 'SLD: ' . ($r['sld_url'] ?? '') . PHP_EOL;
