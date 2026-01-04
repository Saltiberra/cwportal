<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query('SHOW CREATE TABLE field_supervision_problem');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'] . PHP_EOL;
foreach ($pdo->query('SELECT id, project_id, title, reported_by, created_at FROM field_supervision_problem ORDER BY id DESC LIMIT 10') as $r) {
    echo $r['id'] . ' project:' . $r['project_id'] . ' title:' . $r['title'] . ' reported_by:' . $r['reported_by'] . ' at:' . $r['created_at'] . PHP_EOL;
}
