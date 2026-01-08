<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT cs.id, cs.name, cs.category_id, COALESCE(pc.name,'(null)') AS category_name FROM credential_store cs LEFT JOIN proc_category pc ON cs.category_id = pc.id ORDER BY cs.id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Name: {$r['name']} | category_id: {$r['category_id']} | category_name: {$r['category_name']}\n";
}
if (!count($rows)) echo "No credentials found\n";
