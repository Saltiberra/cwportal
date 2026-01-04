<?php
// tools/ensure_project_links_migration.php
// Run from CLI: php tools/ensure_project_links_migration.php
require_once __DIR__ . '/../config/database.php';

function columnExists($pdo, $table, $column)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$table = 'field_supervision_project';
$columns = [
    'project_plan_url' => "VARCHAR(1024) NULL AFTER `description`",
    'pv_solution_url' => "VARCHAR(1024) NULL AFTER `project_plan_url`",
    'sld_url' => "VARCHAR(1024) NULL AFTER `pv_solution_url`",
];

echo "Checking DB for columns on table {$table}...\n";
foreach ($columns as $col => $definition) {
    if (columnExists($pdo, $table, $col)) {
        echo " - Column {$col} already exists\n";
    } else {
        echo " - Column {$col} not found, adding... ";
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
            echo "added\n";
        } catch (Throwable $e) {
            echo "failed: {$e->getMessage()}\n";
        }
    }
}

// Normalise empty strings to NULL to avoid empty values
try {
    $pdo->exec("UPDATE `{$table}` SET `project_plan_url` = NULL WHERE `project_plan_url` = ''");
    $pdo->exec("UPDATE `{$table}` SET `pv_solution_url` = NULL WHERE `pv_solution_url` = ''");
    $pdo->exec("UPDATE `{$table}` SET `sld_url` = NULL WHERE `sld_url` = ''");
    echo "Normalized empty strings to NULL in project link columns.\n";
} catch (Throwable $e) {
    echo "Normalization failed: {$e->getMessage()}\n";
}

echo "Done.\n";
