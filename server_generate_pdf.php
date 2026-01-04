<?php
// Server-side PDF generator using Dompdf (optional)
// Usage: /ComissionamentoV2/server_generate_pdf.php?id=123

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid report id';
    exit;
}

// Check Dompdf availability
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(501);
    echo "Dompdf is not installed.\n\n";
    echo "Install with Composer:\n";
    echo "  composer require dompdf/dompdf:1.2.*\n\n";
    echo "Then reload this URL: /ComissionamentoV2/server_generate_pdf.php?id={$reportId}\n";
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// Build minimal HTML by including the same generated markup but without the action bar
ob_start();
$_GET['id'] = $reportId;
include __DIR__ . '/generate_report.php';
$html = ob_get_clean();

// Strip the screen-only action bar; it has class .no-print
$html = preg_replace('/<div class=\"no-print\"[\s\S]*?<\/div>\s*/i', '', $html, 1);

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultPaperSize', 'A4');
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Commissioning_Report_' . $reportId . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
