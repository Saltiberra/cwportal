<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
$includeNotes = $argv[2] ?? 0;
if (!$projectId) {
    echo "Usage: php test_list_project_timeline.php <project_id>\n";
    exit(1);
}

$_GET['action'] = 'list_project_timeline';
$_GET['project_id'] = $projectId;
if ($includeNotes) $_GET['include_notes'] = '1';
// include endpoint and print result
include __DIR__ . '/../ajax/manage_field_supervision.php';
