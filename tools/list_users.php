<?php
require_once __DIR__ . '/../config/database.php';
$rows = $pdo->query('SELECT id, username, email, is_active FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
