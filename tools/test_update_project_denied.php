<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate non-admin, non-gestor user
$_SESSION['user_id'] = 99;
$_SESSION['username'] = 'regularuser';
$_SESSION['role'] = 'operador';

$id = $argv[1] ?? 0;
if (!$id) {
    echo "Usage: php test_update_project_denied.php <project_id>\n";
    exit(1);
}

$_POST['action'] = 'update_project';
$_POST['id'] = $id;
$_POST['title'] = 'Attempted Update By Non Owner ' . time();
$_POST['description'] = 'Should be denied for non-supervisor and non-admin users.';

// Ensure REQUEST has action too
$_REQUEST['action'] = 'update_project';
$_REQUEST['id'] = $id;

include __DIR__ . '/../ajax/manage_field_supervision.php';
