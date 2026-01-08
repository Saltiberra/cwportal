<?php
require_once 'config/database.php';
session_start();
$_SESSION['user_id'] = 3; // fake session

$_POST['action'] = 'autosave';
$_POST['report_id'] = 324;
$_POST['inverter_index'] = 0;
$_POST['mppt'] = 1;
$_POST['string_num'] = 1;
$_POST['field'] = 'voc';
$_POST['value'] = 'test';

require_once 'ajax/mppt_crud.php';
