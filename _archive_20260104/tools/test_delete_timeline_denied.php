<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Create a timeline entry as user 1
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
if (!$projectId) {
    echo "Usage: php test_delete_timeline_denied.php <project_id>\n";
    exit(1);
}

$stmt = $pdo->prepare("INSERT INTO field_supervision_timeline (project_id,entry_type,entry_text,created_by) VALUES (?, 'note', ?, ?)");
$stmt->execute([$projectId, 'denied timeline entry ' . time(), $_SESSION['user_id']]);
$id = $pdo->lastInsertId();
echo "Created timeline id: $id as admin user 1\n";

// Now switch to non-admin user
$_SESSION['user_id'] = 99;
$_SESSION['role'] = 'operador';

// Try to delete
$_POST['action'] = 'delete_project_timeline';
$_POST['id'] = $id;
$_REQUEST['action'] = 'delete_project_timeline';
$_REQUEST['id'] = $id;
include __DIR__ . '/../ajax/manage_field_supervision.php';
