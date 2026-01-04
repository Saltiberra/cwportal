<?php
// Apply field supervision migration via CLI (ONLY RUN IN DEVELOPMENT OR STAGING)
require_once __DIR__ . '/../config/database.php';
$file = __DIR__ . '/../db_migrate_field_supervision_procedures.sql';
if (!file_exists($file)) {
    echo "Migration file not found: $file\n";
    exit(1);
}
$sql = file_get_contents($file);
// Remove comments and empty lines to be safer
$lines = preg_split('/\r?\n/', $sql);
$filtered = [];
foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '#') === 0) continue;
    $filtered[] = $line;
}
$sql = implode("\n", $filtered);
// Try to execute the file as a whole first
try {
    $pdo->exec($sql);
    echo "Migration executed successfully (exec).\n";
    exit(0);
} catch (PDOException $e) {
    echo "Bulk exec failed, attempting split by statements: " . $e->getMessage() . "\n";
}
// Fallback: split by semicolon into statements
$stmts = preg_split('/;\s*\n/', $sql);
foreach ($stmts as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
        echo "OK: " . (strlen($stmt) > 60 ? substr($stmt, 0, 60) . '...' : $stmt) . "\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
echo "Done\n";
