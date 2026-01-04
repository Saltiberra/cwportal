<?php
require_once __DIR__ . '/../config/database.php';
try {
    // Add device_ip column if missing
    $pdo->exec("ALTER TABLE credential_store ADD COLUMN IF NOT EXISTS device_ip VARCHAR(45) NULL AFTER username");
    // Add index for device_ip for search performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_credential_device_ip ON credential_store(device_ip)");
    echo "device_ip column ensured\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
