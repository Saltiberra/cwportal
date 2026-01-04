<?php

/**
 * Run All Migrations Script
 * 
 * This script executes all SQL migration files in the sql_migrations folder.
 */

// Include database connection configuration
require_once 'config/database.php';

$migrationDir = 'sql_migrations';

if (!is_dir($migrationDir)) {
    die("Migration directory not found: $migrationDir\n");
}

$files = glob("$migrationDir/*.sql");
sort($files); // Execute in order

foreach ($files as $file) {
    echo "Executing migration: " . basename($file) . "\n";
    
    $sql = file_get_contents($file);
    if (empty($sql)) {
        echo "  Skipped: empty file\n";
        continue;
    }

    try {
        $pdo->exec($sql);
        echo "  Success\n";
    } catch (PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

echo "All migrations attempted.\n";

?>