<?php
// Headless PDF generator using Puppeteer (Node.js)
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($surveyId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid survey id';
    exit;
}

$nodeCmd = trim(shell_exec('where node 2>NUL')) ?: (trim(shell_exec('which node 2>/dev/null')) ?: '');
if (empty($nodeCmd)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(501);
    echo "Node.js is not installed on this server.\n";
    echo "Install Node.js and Puppeteer, then run the Node script at node_scripts/render_survey_pdf.js\n";
    echo "Example: npm init -y && npm install puppeteer --save\n";
    exit;
}

$tmpDir = sys_get_temp_dir();
$tmpFile = tempnam($tmpDir, 'survey_pdf_') . '.pdf';
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/cleanwattsportal/generate_survey_report_new.php?id=' . $surveyId;
// Build command
$scriptPath = __DIR__ . '/node_scripts/render_survey_pdf.js';
$cmd = escapeshellcmd($nodeCmd) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($url) . ' ' . escapeshellarg($tmpFile) . ' 2>&1';
exec($cmd, $output, $ret);
if ($ret !== 0 || !file_exists($tmpFile)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "Failed to render PDF. Node output:\n" . implode("\n", $output);
    exit;
}

// Stream PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Site_Survey_Report_' . $surveyId . '.pdf"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
