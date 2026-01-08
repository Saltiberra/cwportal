<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$projectId = $argv[1] ?? 0;
$text = $argv[2] ?? 'test note ' . time();
if (!$projectId) {
    echo "Usage: php test_add_project_note.php <project_id> [text]\n";
    exit(1);
}

$_POST['action'] = 'add_project_timeline';
$_POST['project_id'] = $projectId;
$_POST['entry_type'] = 'note';
$_POST['entry_text'] = $text;
$_REQUEST['action'] = 'add_project_timeline';
// include endpoint and print result
include __DIR__ . '/../ajax/manage_field_supervision.php';
