<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
if (!$projectId) {
    echo "Usage: php test_list_project_notes.php <project_id>\n";
    exit(1);
}

$_GET['action'] = 'list_project_notes';
$_REQUEST['action'] = 'list_project_notes';
$_GET['project_id'] = $projectId;
// Debug removed: invoking action set below
// include endpoint and print result
include __DIR__ . '/../ajax/manage_field_supervision.php';
