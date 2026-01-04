<?php
require_once __DIR__ . '/../config/database.php';
$projectId = $argv[1] ?? 0;
if (!$projectId) {
    echo "Usage: php test_select_problems.php <project_id>\n";
    exit(1);
}
$stmt = $pdo->prepare("SELECT id, title, reported_by, created_at FROM field_supervision_problem WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
