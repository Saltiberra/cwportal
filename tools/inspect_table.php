<?php
require_once __DIR__ . '/../config/database.php';
try {
    $stmt = $pdo->query("SHOW CREATE TABLE report_form_sessions");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'];
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
