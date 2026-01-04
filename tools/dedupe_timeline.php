<?php
require_once __DIR__ . '/../config/database.php';
if ($argc < 2) {
    echo "Usage: php dedupe_timeline.php <project_id|project_title> [--delete]\n";
    exit(1);
}
$target = $argv[1];
$delete = in_array('--delete', $argv);
// If a numeric id is provided, use it; otherwise treat as title
if (is_numeric($target)) {
    $projectId = intval($target);
} else {
    $stmt = $pdo->prepare("SELECT id FROM field_supervision_project WHERE title = ? LIMIT 1");
    $stmt->execute([$target]);
    $projectId = $stmt->fetchColumn();
    if (!$projectId) {
        echo "Project not found by title: $target\n";
        exit(1);
    }
}

echo "Checking duplicates for project id: $projectId\n";

$sql = "SELECT entry_type, entry_text, created_by, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id ASC) as ids
        FROM field_supervision_timeline WHERE project_id = ? GROUP BY entry_type, entry_text, created_by HAVING cnt > 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$projectId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No duplicates found.\n";
    exit(0);
}
foreach ($rows as $r) {
    echo "Duplicate group: cnt={$r['cnt']} created_by={$r['created_by']} entry_type={$r['entry_type']}\n";
    echo "ids: {$r['ids']}\n";
    if ($delete) {
        $ids = explode(',', $r['ids']);
        // keep first id, delete the rest
        $keep = array_shift($ids);
        $in = implode(',', array_map('intval', $ids));
        $delSql = "DELETE FROM field_supervision_timeline WHERE id IN ($in)";
        $pdo->exec($delSql);
        echo "Deleted duplicates for group, kept id: $keep\n";
    }
}
echo "Done.\n";
