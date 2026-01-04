<?php

/**
 * Run SQL migrations present in sql_migrations folder
 * CAUTION: This executes SQL directly; make a DB backup before running.
 * Usage: php tools/run_sql_migration.php
 */
require_once __DIR__ . '/../config/database.php';
$migrationsDir = __DIR__ . '/../sql_migrations';
$migFiles = glob($migrationsDir . '/*.sql');
if (empty($migFiles)) {
    echo "No migration files found in $migrationsDir\n";
    exit(0);
}
foreach ($migFiles as $file) {
    echo "\nRunning migration: $file\n";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "Success\n";
    } catch (Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
