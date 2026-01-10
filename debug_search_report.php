<?php
$host = 'localhost';
$db   = 'cleanwattsportal';
$user = 'root';
$pass = '';

try {
     $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
     $stmt = $pdo->prepare("SELECT id, project_name, created_at FROM commissioning_reports WHERE project_name LIKE '%UPAC23%'");
     $stmt->execute();
     $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
     if ($res) {
        print_r($res);
     } else {
        echo "No report found with 'UPAC23'.\n";
        // List all recent to confirm DB works
        $stmt2 = $pdo->query("SELECT id, project_name FROM commissioning_reports ORDER BY created_at DESC LIMIT 5");
        echo "Recent reports in DB:\n";
        print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
     }
} catch (\PDOException $e) {
     echo 'Error: ' . $e->getMessage();
}
