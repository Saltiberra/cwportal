<?php
require_once __DIR__ . '/../config/database.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$problemId = intval($argv[1] ?? 0);
if (!$problemId) {
    echo "Usage: php test_delete_problem.php <problem_id>\n";
    exit(1);
}

$_POST['action'] = 'delete_problem';
$_POST['id'] = $problemId;
$_REQUEST['action'] = 'delete_problem';

include __DIR__ . '/../ajax/manage_field_supervision.php';
