<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Build target URL for the HTML renderer
$project = isset($_GET['project']) ? rawurlencode($_GET['project']) : '';
$severity = isset($_GET['severity']) ? rawurlencode($_GET['severity']) : '';
$search = isset($_GET['search']) ? rawurlencode($_GET['search']) : '';

$script = __DIR__ . '/tools/render_punch_list_pdf.js';
$node = 'node'; // assume node available in PATH

$htmlUrl = sprintf("http://localhost/cleanwattsportal/server_render_punch_list_html.php?project=%s&severity=%s&search=%s", $project, $severity, $search);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'punch_list_' . time() . '.pdf';

// Get session cookie name and id
$cookieName = session_name();
session_write_close();
$cookieValue = session_id();

// Build command - escape args
$cmd = escapeshellcmd($node) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($htmlUrl) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($cookieName) . ' ' . escapeshellarg($cookieValue);

// Execute (blocking)
exec($cmd . ' 2>&1', $out, $rc);

if ($rc !== 0 || !file_exists($tmp)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "Puppeteer render failed (rc={$rc}).\nOutput:\n" . implode("\n", $out);
    exit;
}

// Stream PDF to client
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Open_Punch_Lists.pdf"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
