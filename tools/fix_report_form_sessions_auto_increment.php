<?php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo->beginTransaction();

    $mRow = $pdo->query('SELECT IFNULL(MAX(id), 0) AS m FROM report_form_sessions')->fetch(PDO::FETCH_ASSOC);
    $m = intval($mRow['m']);

    // Find rows with id = 0
    $rows = $pdo->query('SELECT * FROM report_form_sessions WHERE id = 0')->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
        echo "No rows with id=0 found\n";
    } else {
        foreach ($rows as $row) {
            $m++;
            $upd = $pdo->prepare('UPDATE report_form_sessions SET id = ? WHERE id = 0 LIMIT 1');
            $upd->execute([$m]);
            echo "Updated row (session_token={$row['session_token']}) to id={$m}\n";
        }
    }

    // Modify column to auto_increment
    $pdo->exec('ALTER TABLE report_form_sessions MODIFY id INT(11) NOT NULL AUTO_INCREMENT');
    $pdo->exec('ALTER TABLE report_form_sessions AUTO_INCREMENT = ' . ($m + 1));

    $pdo->commit();
    echo "Altered report_form_sessions.id to AUTO_INCREMENT, next auto-increment=" . ($m + 1) . "\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
