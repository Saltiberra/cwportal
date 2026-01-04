<?php
require_once 'config/database.php';

try {
    $pdo->exec("ALTER TABLE site_survey_reports ADD COLUMN IF NOT EXISTS map_area_m2 DECIMAL(10,2) NULL COMMENT 'Measured area from map in square meters'");
    $pdo->exec("ALTER TABLE site_survey_reports ADD COLUMN IF NOT EXISTS map_azimuth_deg DECIMAL(5,1) NULL COMMENT 'Calculated azimuth from map in degrees'");
    $pdo->exec("ALTER TABLE site_survey_reports ADD COLUMN IF NOT EXISTS map_polygon_coords JSON NULL COMMENT 'Polygon coordinates for area measurement'");
    // Also add map columns to commissioning_reports to support map preview in commissioning reports
    $pdo->exec("ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS map_area_m2 DECIMAL(10,2) NULL COMMENT 'Measured area from map in square meters'");
    $pdo->exec("ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS map_azimuth_deg DECIMAL(5,1) NULL COMMENT 'Calculated azimuth from map in degrees'");
    $pdo->exec("ALTER TABLE commissioning_reports ADD COLUMN IF NOT EXISTS map_polygon_coords JSON NULL COMMENT 'Polygon coordinates for area measurement'");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
