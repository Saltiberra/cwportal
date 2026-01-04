<?php
require_once __DIR__ . '/../config/database.php';
foreach ($pdo->query('SELECT id,title FROM field_supervision_project ORDER BY id') as $r) {
    echo $r['id'] . ' - ' . $r['title'] . PHP_EOL;
}
