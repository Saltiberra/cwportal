<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
// Simulate logged in user
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'dev';

$_GET['project'] = '';
$_GET['severity'] = '';
$_GET['search'] = '';

// Capture output (PDF binary) to a file for inspection
$out = '';
ob_start();
try {
    include __DIR__ . '/../server_generate_punch_list_pdf.php';
} catch (Throwable $e) {
    $err = "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    file_put_contents(__DIR__ . '/punch_pdf_error.txt', $err);
    echo "ERROR written to tools/punch_pdf_error.txt\n";
    exit(1);
}
$out = ob_get_clean();
file_put_contents(__DIR__ . '/punch_pdf_output.bin', $out);
echo "Wrote " . strlen($out) . " bytes to tools/punch_pdf_output.bin\n";
