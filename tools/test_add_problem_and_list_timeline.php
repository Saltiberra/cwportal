<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
if (!$projectId) {
    echo "Usage: php test_add_problem_and_list_timeline.php <project_id>\n";
    exit(1);
}

// Add a problem via endpoint
$_POST['action'] = 'add_problem';
$_POST['project_id'] = $projectId;
$_POST['title'] = 'CLI Test Problem ' . time();
$_POST['description'] = 'Created by CLI test';
$_POST['severity'] = 'minor';
$_REQUEST['action'] = 'add_problem';

include __DIR__ . '/../ajax/manage_field_supervision.php';

// Now list timeline
$_GET['action'] = 'list_project_timeline';
$_GET['project_id'] = $projectId;
include __DIR__ . '/../ajax/manage_field_supervision.php';
