<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$_GET['action'] = 'list_problems';
$_REQUEST['action'] = 'list_problems';
// no filters by default
include __DIR__ . '/../ajax/manage_field_supervision.php';
// include file executes action via $_REQUEST
