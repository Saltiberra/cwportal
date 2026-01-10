<?php
$host = 'localhost';
$db   = 'cleanwattsportal';
$user = 'root';
$pass = '';

try {
     $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $stmt = $pdo->query("DESCRIBE mppt_string_measurements");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in mppt_string_measurements: " . implode(", ", $columns) . "\n";
} catch (\PDOException $e) {
     echo 'Error: ' . $e->getMessage();
}
