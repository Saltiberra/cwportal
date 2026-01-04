<?php
require_once __DIR__ . '/../config/database.php';
$id = intval($argv[1] ?? 0);
if ($id <= 0) {
    echo "Usage: php tools/show_encrypted_secret.php <id>\n";
    exit(1);
}
$stmt = $pdo->prepare('SELECT id, name, encrypted_secret FROM credential_store WHERE id=?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Not found\n";
    exit(1);
}
echo "ID: {$row['id']} Name: {$row['name']}\n";
echo "encrypted_secret: {$row['encrypted_secret']}\n";
echo "len: " . strlen($row['encrypted_secret']) . "\n";
