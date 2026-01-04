<?php
require_once __DIR__ . '/../config/database.php';
session_start();
// Simulate admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// include list_visits
// Simulate type filter: if first arg = 'problem' then set type for visits query
$filterType = $argv[1] ?? '';
if ($filterType) {
    $_GET['type'] = $filterType;
    $_REQUEST['type'] = $filterType;
}
$_GET['action'] = 'list_visits';
$_REQUEST['action'] = 'list_visits';
ob_start();
include __DIR__ . '/../ajax/manage_field_supervision.php';
$visOutput = ob_get_clean();
$visJson = json_decode($visOutput, true);

// include list_problems
$_GET['action'] = 'list_problems';
$_REQUEST['action'] = 'list_problems';
ob_start();
include __DIR__ . '/../ajax/manage_field_supervision.php';
$probOutput = ob_get_clean();
$probJson = json_decode($probOutput, true);

$visData = $visJson['data'] ?? [];
$probData = $probJson['data'] ?? [];

$combined = [];
foreach ($visData as $v) {
    $combined[] = ['id' => $v['id'], 'type' => 'visit', 'title' => $v['title'], 'project_title' => $v['project_title'] ?? '', 'status' => $v['status'] ?? '', 'severity' => $v['severity'] ?? '', 'start' => $v['date_start'] ?? $v['created_at'] ?? ''];
}
foreach ($probData as $p) {
    $combined[] = ['id' => $p['id'], 'type' => 'problem', 'title' => $p['title'], 'project_title' => $p['project_title'] ?? '', 'status' => $p['status'] ?? '', 'severity' => $p['severity'] ?? '', 'start' => $p['created_at'] ?? ''];
}

usort($combined, function ($a, $b) {
    return strtotime($b['start'] ?: '0') - strtotime($a['start'] ?: '0');
});

echo json_encode(['success' => true, 'data' => $combined], JSON_PRETTY_PRINT);
