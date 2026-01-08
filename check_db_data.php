<?php
require_once 'config/database.php';

$tables = [
    'epcs',
    'representatives',
    'pv_module_brands',
    'inverter_brands',
    'cable_brands',
    'communications_models'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "Table $table: $count rows\n";
    } catch (Exception $e) {
        echo "Table $table: Error - " . $e->getMessage() . "\n";
    }
}
