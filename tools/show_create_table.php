<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SHOW CREATE TABLE credential_store");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Not found\n";
    exit(1);
}
echo $row['Create Table'] ?? print_r($row, true);
