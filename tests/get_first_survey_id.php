<?php
require_once __DIR__ . '/../config/database.php';
try {
    $stmt = $pdo->query("SELECT id FROM site_survey_reports ORDER BY id ASC LIMIT 1");
    $r = $stmt->fetch();
    if ($r) {
        echo $r['id'] . "\n";
    } else {
        echo "NONE\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
