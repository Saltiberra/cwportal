<?php

/**
 * Migration Script: Add Map Fields to site_survey_reports
 * 
 * This script adds the map_area_m2 and map_azimuth_deg columns to the site_survey_reports table.
 * Run this after setting up the database with setup_database.php.
 */

// Include database connection configuration
require_once 'config/database.php';

try {
    // Start a transaction
    $pdo->beginTransaction();

    // SQL to add the new columns
    $sql = [
        "ALTER TABLE site_survey_reports ADD COLUMN map_area_m2 DECIMAL(10,2) NULL COMMENT 'Measured area from map in square meters';",
        "ALTER TABLE site_survey_reports ADD COLUMN map_azimuth_deg DECIMAL(5,1) NULL COMMENT 'Calculated azimuth from map in degrees';"
    ];

    // Execute each SQL statement
    foreach ($sql as $query) {
        $pdo->exec($query);
        echo "Executed: " . substr($query, 0, 50) . "...\n";
    }

    // Commit the transaction
    $pdo->commit();

    echo "Migration completed successfully! The map fields have been added to site_survey_reports.\n";
} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
