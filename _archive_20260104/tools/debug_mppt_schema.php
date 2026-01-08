<?php

/**
 * Debug helper to print the mppt_string_measurements column types
 * Run in browser or cli: php tools/debug_mppt_schema.php
 */
require_once __DIR__ . '/../config/database.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM mppt_string_measurements");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "mppt_string_measurements schema:\n";
    foreach ($cols as $c) {
        echo sprintf("%s: %s%s", $c['Field'], $c['Type'], PHP_EOL);
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
