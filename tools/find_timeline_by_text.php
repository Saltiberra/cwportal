<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Fake admin for CLI usage
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$text = trim($argv[1] ?? '');
if ($text === '') {
    echo "Usage: php find_timeline_by_text.php <text-substring>\n";
    exit(1);
}

$stmt = $pdo->prepare("SELECT id, project_id, entry_text, entry_type, created_at FROM field_supervision_timeline WHERE entry_text LIKE ?");
$stmt->execute(['%' . $text . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "No matching timeline entries found for '{$text}'\n";
    exit(0);
}

foreach ($rows as $r) {
    echo "Found timeline id={$r['id']} project_id={$r['project_id']} type={$r['entry_type']} created_at={$r['created_at']} text={$r['entry_text']}\n";
}

echo "Found " . count($rows) . " matching timeline entries.\n";
