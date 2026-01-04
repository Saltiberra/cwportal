<?php
require_once __DIR__ . '/../config/database.php';
session_start();
$_SESSION['user_id'] = 1; // admin
$_SESSION['role'] = 'admin';

$visitId = intval($argv[1] ?? 0);
if (!$visitId) {
    echo "Usage: php test_delete_visit.php <visit_id>\n";
    exit(1);
}

$_POST['action'] = 'delete_visit';
$_POST['id'] = $visitId;
$_REQUEST['action'] = 'delete_visit';

include __DIR__ . '/../ajax/manage_field_supervision.php';
