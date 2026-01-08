<?php
require_once __DIR__ . '/../config/database.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$problemId = intval($argv[1] ?? 0);
$status = $argv[2] ?? 'resolved';
if (!$problemId) {
    echo "Usage: php test_update_problem_status.php <problem_id> [status]\n";
    exit(1);
}

$_POST['action'] = 'update_problem';
$_POST['id'] = $problemId;
$_POST['status'] = $status;
$_REQUEST['action'] = 'update_problem';

include __DIR__ . '/../ajax/manage_field_supervision.php';
