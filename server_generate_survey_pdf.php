<?php
// Server-side PDF generator for Site Survey using Dompdf
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($surveyId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid survey id';
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
    echo "Then reload this URL: /server_generate_survey_pdf.php?id={$surveyId}\n";
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// Build HTML by including the same page but with no action bar
ob_start();
$_GET['id'] = $surveyId;
include __DIR__ . '/generate_survey_report_new.php';
$html = ob_get_clean();

// Remove action UI; expect a container with class .no-print
$html = preg_replace('/<div class="no-print"[\s\S]*?<\/div>\s*/i', '', $html, 1);

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultPaperSize', 'A4');
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Site_Survey_Report_' . $surveyId . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);

exit;
