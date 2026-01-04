<?php

/**
 * Import Dump Script with Error Handling
 * 
 * This script imports the SQL dump, ignoring errors for tables that fail due to dependencies.
 */

// Include database connection configuration
require_once 'config/database.php';

try {
    // Read the dump file
    $dumpFile = 'if0_40570175_cleanwattsportal_20251217.sql';
    if (!file_exists($dumpFile)) {
        die("Dump file not found: $dumpFile\n");
    }

    $sql = file_get_contents($dumpFile);

    // Split into individual statements (basic split by semicolon)
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $successCount = 0;
    $errorCount = 0;

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip comments or empty
        }

        try {
            $pdo->exec($statement);
            $successCount++;
            echo "OK: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "ERROR: " . $e->getMessage() . " in: " . substr($statement, 0, 50) . "...\n";
        }
    }

    echo "\nImport completed: $successCount successful, $errorCount errors.\n";

} catch (Exception $e) {
    echo "Script failed: " . $e->getMessage() . "\n";
}

?>