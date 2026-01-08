<?php
require_once __DIR__ . '/../config/database.php';
try {
    $r = $pdo->query('SELECT COUNT(*) AS c FROM report_form_sessions WHERE id = 0')->fetch(PDO::FETCH_ASSOC);
    echo "rows_with_id_zero=" . intval($r['c']) . PHP_EOL;
    $max = $pdo->query('SELECT MAX(id) AS m FROM report_form_sessions')->fetch(PDO::FETCH_ASSOC);
    echo "max_id=" . (is_null($max['m']) ? 'NULL' : intval($max['m'])) . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
