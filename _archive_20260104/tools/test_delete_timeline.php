<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Fake admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
if (!$projectId) {
    echo "Usage: php test_delete_timeline.php <project_id>\n";
    exit(1);
}

// Create timeline entry
$stmt = $pdo->prepare("INSERT INTO field_supervision_timeline (project_id,entry_type,entry_text,created_by) VALUES (?, 'note', ?, ?)");
$stmt->execute([$projectId, 'test timeline entry ' . time(), $_SESSION['user_id']]);
$id = $pdo->lastInsertId();
echo "Created timeline id: $id\n";

// Now call the endpoint to delete it (simulate POST)
$_POST['action'] = 'delete_project_timeline';
$_POST['id'] = $id;
$_REQUEST['action'] = 'delete_project_timeline';
$_REQUEST['id'] = $id;
include __DIR__ . '/../ajax/manage_field_supervision.php';
