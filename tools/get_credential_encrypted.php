<?php
require_once __DIR__ . '/../config/database.php';
$id = intval($argv[1] ?? 0);
if ($id <= 0) {
    echo "Usage: php get_credential_encrypted.php <id>\n";
    exit(1);
}
$stmt = $pdo->prepare('SELECT id,name,encrypted_secret FROM credential_store WHERE id=?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Not found\n";
    exit(1);
}
$len = strlen($row['encrypted_secret'] ?? '');
echo "ID: {$row['id']} Name: {$row['name']} Encrypted length: $len\n";
if ($len) echo substr($row['encrypted_secret'], 0, 40) . "...\n";
