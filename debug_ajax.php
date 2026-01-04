<?php
// Test file to debug the manage_responsibles AJAX endpoint

session_start();

echo "<pre>";
echo "=== SESSION DEBUG ===\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "\n=== DATABASE TEST ===\n";

try {
    require_once 'config/database.php';
    echo "Database: Connected ✓\n";

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM commissioning_responsibles");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Commissioning Responsibles table: " . $result['cnt'] . " records\n";
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

echo "\n=== API TEST ===\n";
echo "Try calling: http://localhost/ComissionamentoV2/ajax/manage_responsibles.php?action=list\n";
echo "</pre>";
