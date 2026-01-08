<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Set admin session to simulate a logged in admin
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testadmin';
$_SESSION['role'] = 'admin';

// Accept project id via arg or hardcode
$id = $argv[1] ?? 0;
if (!$id) {
    echo "Usage: php test_delete_project.php <project_id>\n";
    exit(1);
}

// Prepare request emulation
$_POST['action'] = 'delete_project';
$_POST['id'] = $id;
// Also set REQUEST superglobal in case it's used by the script
$_REQUEST['action'] = 'delete_project';
$_REQUEST['id'] = $id;

// Include the endpoint script and let it handle output
include __DIR__ . '/../ajax/manage_field_supervision.php';
