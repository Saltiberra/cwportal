<?php
require_once __DIR__ . '/../config/database.php';
$projectId = $argv[1] ?? 0;
$includeNotes = isset($argv[2]) && $argv[2] == '1';
if (!$projectId) {
    echo "Usage: php test_union_timeline_sql.php <project_id>\n";
    exit(1);
}
$parts = [];
$params = [];
if ($pdo->query("SHOW TABLES LIKE 'field_supervision_timeline'")->rowCount() > 0) {
    if ($includeNotes) {
        $parts[] = "SELECT CONCAT('t', id) AS id, entry_type, CONVERT(entry_text USING utf8mb4) AS entry_text, created_by, created_at, 'timeline' as origin FROM field_supervision_timeline WHERE project_id = ?";
    } else {
        $parts[] = "SELECT CONCAT('t', id) AS id, entry_type, CONVERT(entry_text USING utf8mb4) AS entry_text, created_by, created_at, 'timeline' as origin FROM field_supervision_timeline WHERE project_id = ? AND entry_type <> 'note'";
    }
    $params[] = $projectId;
}
if ($pdo->query("SHOW TABLES LIKE 'field_supervision_problem'")->rowCount() > 0) {
    $parts[] = "SELECT CONCAT('p', id) AS id, 'problem' AS entry_type, CONVERT(CONCAT('Problem reported: ', COALESCE(title,'')) USING utf8mb4) AS entry_text, reported_by AS created_by, created_at, 'problem' as origin FROM field_supervision_problem WHERE project_id = ?";
    $params[] = $projectId;
}
if (
    $pdo->query("SHOW TABLES LIKE 'field_visit'")->rowCount() > 0 &&
    $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='field_visit' AND column_name='project_id'")->fetchColumn() > 0
) {
    $parts[] = "SELECT CONCAT('v', id) AS id, 'visit' AS entry_type, CONVERT(CONCAT('Visit: ', COALESCE(title,'')) USING utf8mb4) AS entry_text, supervisor_user_id AS created_by, date_start AS created_at, 'visit' as origin FROM field_visit WHERE project_id = ?";
    $params[] = $projectId;
}
if (empty($parts)) {
    echo "No parts\n";
    exit(0);
}
$sql = implode("\nUNION ALL\n", $parts) . "\nORDER BY created_at DESC";
echo "SQL:\n$sql\n---\n";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
echo "Done\n";
