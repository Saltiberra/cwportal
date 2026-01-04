<?php

/**
 * inject_energy_meter_dropdown.php
 * Server-side fallback to populate energy meter brand dropdown
 */

// Include DB
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, brand_name FROM energy_meter_brands ORDER BY brand_name");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $brands = [];
}

// Output options
if (!empty($brands)) {
    echo "<option value=\"\">Select Brand...</option>\n";
    foreach ($brands as $b) {
        $id = htmlspecialchars($b['id']);
        $name = htmlspecialchars($b['brand_name']);
        echo "<option value=\"$id\">$name</option>\n";
    }
} else {
    echo "<option value=\"\">No brands</option>\n";
}
