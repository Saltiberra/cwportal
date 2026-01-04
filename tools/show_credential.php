<?php
require_once __DIR__ . '/../config/database.php';
$id = intval($argv[1] ?? 0);
if ($id <= 0) {
    echo "Usage: php tools/show_credential.php <id>\n";
    exit(1);
}
$stmt = $pdo->prepare('SELECT * FROM credential_store WHERE id=?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Not found\n";
    exit(1);
}
print_r($row);
