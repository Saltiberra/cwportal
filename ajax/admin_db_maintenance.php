<?php

/**
 * Admin DB Maintenance
 * - Checks and repairs minimal schema used by Admin panels
 * - Only for admin users (session-based)
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$role = $_SESSION['role'] ?? 'operador';
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: admin only']);
    exit;
}

function ensureTableExists(PDO $pdo, string $sqlCreate)
{
    try {
        $pdo->exec($sqlCreate);
    } catch (Exception $e) {
        // ignore; will surface later in operations
    }
}

function ensureEpcsSchema(PDO $pdo): array
{
    $actions = [];
    try {
        // Create base table if missing (minimal)
        ensureTableExists($pdo, "CREATE TABLE IF NOT EXISTS `epcs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $cols = [];
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epcs'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $cols[strtolower($c)] = true;
        }

        $alter = [];
        if (!isset($cols['address'])) $alter[] = "ADD COLUMN `address` varchar(500) DEFAULT NULL AFTER `name`";
        if (!isset($cols['phone']))   $alter[] = "ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `address`";
        if (!isset($cols['email']))   $alter[] = "ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `phone`";
        if (!isset($cols['website'])) $alter[] = "ADD COLUMN `website` varchar(255) DEFAULT NULL AFTER `email`";
        if (count($alter) > 0) {
            $sql = "ALTER TABLE `epcs` " . implode(', ', $alter);
            $pdo->exec($sql);
            $actions[] = 'epcs: altered columns -> ' . implode(', ', $alter);
        } else {
            $actions[] = 'epcs: schema OK';
        }
    } catch (Exception $e) {
        $actions[] = 'epcs Error: ' . $e->getMessage();
    }
    return $actions;
}

function ensureRepresentativesSchema(PDO $pdo): array
{
    $actions = [];
    try {
        ensureTableExists($pdo, "CREATE TABLE IF NOT EXISTS `representatives` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `phone` varchar(50) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `epc_id` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $cols = [];
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'representatives'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $cols[strtolower($c)] = true;
        }

        $alter = [];
        if (!isset($cols['phone'])) $alter[] = "ADD COLUMN `phone` varchar(50) NOT NULL AFTER `name`";
        if (!isset($cols['email'])) $alter[] = "ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `phone`";
        if (!isset($cols['epc_id'])) $alter[] = "ADD COLUMN `epc_id` int(11) DEFAULT NULL AFTER `email`";
        if (count($alter) > 0) {
            $sql = "ALTER TABLE `representatives` " . implode(', ', $alter);
            $pdo->exec($sql);
            $actions[] = 'representatives: altered columns -> ' . implode(', ', $alter);
        } else {
            $actions[] = 'representatives: schema OK';
        }
    } catch (Exception $e) {
        $actions[] = 'representatives Error: ' . $e->getMessage();
    }
    return $actions;
}

function ensureCommissioningResponsiblesSchema(PDO $pdo): array
{
    $actions = [];
    try {
        ensureTableExists($pdo, "CREATE TABLE IF NOT EXISTS `commissioning_responsibles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $actions[] = 'commissioning_responsibles: ensured';
    } catch (Exception $e) {
        $actions[] = 'commissioning_responsibles Error: ' . $e->getMessage();
    }
    return $actions;
}

$summary = [
    'epcs' => ensureEpcsSchema($pdo),
    'representatives' => ensureRepresentativesSchema($pdo),
    'commissioning_responsibles' => ensureCommissioningResponsiblesSchema($pdo),
];

echo json_encode(['success' => true, 'message' => 'DB check/repair completed', 'summary' => $summary], JSON_PRETTY_PRINT);
