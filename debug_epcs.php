<?php
require_once 'config/database.php';
header('Content-Type: text/plain');
try {
    $stmt = $pdo->query("SELECT id, name, address, phone, email, website, created_at FROM epcs ORDER BY name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "OK count=" . count($rows) . "\n";
    foreach ($rows as $r) {
        echo $r['id'] . " | " . $r['name'] . " | " . ($r['email'] ?? '') . "\n";
    }
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage();
}
