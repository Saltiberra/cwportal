<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testadmin';
$_SESSION['role'] = 'admin';

$id = $argv[1] ?? 0;
if (!$id) {
    echo "Usage: php test_update_project.php <project_id>\n";
    exit(1);
}

$_POST['action'] = 'update_project';
$_POST['id'] = $id;
$_POST['title'] = 'Updated Title ' . time();
$_POST['description'] = 'Updated description via test.';

// Ensure REQUEST has action too
$_REQUEST['action'] = 'update_project';
$_REQUEST['id'] = $id;

include __DIR__ . '/../ajax/manage_field_supervision.php';
