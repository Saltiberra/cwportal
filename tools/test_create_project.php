<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Create a fake admin user
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testadmin';
$_SESSION['role'] = 'admin';

try {
    $stmt = $pdo->prepare("INSERT INTO field_supervision_project (title,description,supervisor_user_id,start_date,status) VALUES (?, ?, ?, NOW(), 'planned')");
    $stmt->execute(['Test Project ' . time(), 'Created by test script', $_SESSION['user_id']]);
    echo "Created project id: " . $pdo->lastInsertId() . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
