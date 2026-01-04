<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Simulate logged in user
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'dev';
$_SESSION['full_name'] = 'Dev';

// Simulate empty filters
$_GET['project'] = '';
$_GET['severity'] = '';
$_GET['search'] = '';

echo "Running server_generate_punch_list_pdf.php test...\n";
try {
    include __DIR__ . '/../server_generate_punch_list_pdf.php';
    echo "Included script completed (note: PDF binary may have been output).\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "done.\n";
