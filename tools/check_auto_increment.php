<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT table_name, column_name, column_type, extra FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'id' ORDER BY table_name");
$bad = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hasAI = stripos($r['extra'], 'auto_increment') !== false;
    if (!$hasAI) {
        $bad[] = $r;
    }
}
if (empty($bad)) {
    echo "All id columns have AUTO_INCREMENT\n";
} else {
    echo "Tables missing AUTO_INCREMENT on id:\n";
    foreach ($bad as $b) {
        echo " - {$b['table_name']} ({$b['column_type']}) extra='{$b['extra']}'\n";
    }
}
