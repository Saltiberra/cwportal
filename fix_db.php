<?php
require_once 'config/database.php';

echo "<h2>Database Fix Tool</h2>";

try {
    // 1. Check and Add inverter_index column
    echo "Checking 'inverter_index' column in 'mppt_string_measurements'...<br>";
    $stmt = $pdo->query("SHOW COLUMNS FROM mppt_string_measurements LIKE 'inverter_index'");
    if ($stmt->rowCount() == 0) {
        echo "Column missing. Adding 'inverter_index'...<br>";
        $pdo->exec("ALTER TABLE mppt_string_measurements ADD COLUMN inverter_index INT NULL AFTER report_id");
        echo "Column added successfully.<br>";
    } else {
        echo "Column 'inverter_index' already exists.<br>";
    }

    // 2. Check and Add notes column if missing (just in case)
    echo "Checking 'notes' column...<br>";
    $stmt = $pdo->query("SHOW COLUMNS FROM mppt_string_measurements LIKE 'notes'");
    if ($stmt->rowCount() == 0) {
        echo "Column missing. Adding 'notes'...<br>";
        $pdo->exec("ALTER TABLE mppt_string_measurements ADD COLUMN notes TEXT NULL");
        echo "Column 'notes' added successfully.<br>";
    } else {
        echo "Column 'notes' already exists.<br>";
    }

    // 3. Check and Add current column if missing
    echo "Checking 'current' column...<br>";
    $stmt = $pdo->query("SHOW COLUMNS FROM mppt_string_measurements LIKE 'current'");
    if ($stmt->rowCount() == 0) {
        echo "Column missing. Adding 'current'...<br>";
        $pdo->exec("ALTER TABLE mppt_string_measurements ADD COLUMN current VARCHAR(64) NULL");
        echo "Column 'current' added successfully.<br>";
    } else {
        echo "Column 'current' already exists.<br>";
    }

    echo "<h3 style='color:green'>Database check/fix complete.</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
