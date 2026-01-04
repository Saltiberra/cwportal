<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Fake admin for CLI usage
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = intval($argv[1] ?? 0);
$text = trim($argv[2] ?? '');
$dryrun = in_array('--dry', $argv);
if (!$projectId || $text === '') {
    echo "Usage: php delete_timeline_by_text.php <project_id> <text-substring> [--dry]\n";
    exit(1);
}

// Find timeline notes matching substring
$stmt = $pdo->prepare("SELECT id,entry_text,created_at FROM field_supervision_timeline WHERE project_id = ? AND entry_type = 'note' AND entry_text LIKE ?");
$stmt->execute([$projectId, '%' . $text . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No timeline notes matching '{$text}' found for project {$projectId}\n";
    exit(0);
}

foreach ($rows as $r) {
    echo "Found timeline id={$r['id']} created_at={$r['created_at']} text={$r['entry_text']}\n";
}

if ($dryrun) {
    echo "Dry run mode - no deletions performed. Use without --dry to delete." . PHP_EOL;
    exit(0);
}

$ids = array_column($rows, 'id');
$deleteStmt = $pdo->prepare("DELETE FROM field_supervision_timeline WHERE id = ?");
foreach ($ids as $id) {
    $deleteStmt->execute([$id]);
    echo "Deleted timeline id={$id}\n";
}

echo "Done. Removed " . count($ids) . " timeline notes." . PHP_EOL;
