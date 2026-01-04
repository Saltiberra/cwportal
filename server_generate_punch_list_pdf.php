<?php
// Server-side PDF generator for Open Punch Lists (respects filters)
// Usage: server_generate_punch_list_pdf.php?project=...&severity=High&search=...

require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/database.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(501);
    echo "Dompdf is not installed. Install with Composer:\n  composer require dompdf/dompdf:1.2.*\n";
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// If Dompdf classes are not available (manual vendor install not present), show clear message
if (!class_exists('Dompdf\\Dompdf')) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(501);
    echo "Dompdf classes not found. Please ensure Dompdf is installed and vendor/autoload.php loads it.\n";
    echo "If you installed manually, verify files exist at vendor/dompdf/dompdf/autoload.inc.php\n";
    echo "Or run (from project root):\n  C:\\xampp\\php\\php.exe C:\\ProgramData\\ComposerSetup\\bin\\composer.phar require dompdf/dompdf:1.2.*\n";
    exit;
}

// Prefer to render the full HTML version (includes Bootstrap/styles) and pass to Dompdf.
// Include the HTML renderer while ensuring relative requires resolve correctly.
$_GET_BACKUP = $_GET;
$prevCwd = getcwd();
chdir(__DIR__);
// Set filters for the renderer
$_GET['project'] = $_GET_BACKUP['project'] ?? '';
$_GET['severity'] = $_GET_BACKUP['severity'] ?? '';
$_GET['search'] = $_GET_BACKUP['search'] ?? '';
ob_start();
include __DIR__ . '/server_render_punch_list_html.php';
$renderedHtml = ob_get_clean();
// If running from CLI (test harness), save rendered HTML for inspection
if (php_sapi_name() === 'cli') {
    @file_put_contents(__DIR__ . '/tools/punch_rendered.html', $renderedHtml);
}
// If GD is not available in the *Apache* PHP runtime, Dompdf will throw when processing PNGs —
// strip the logo <img> (which has alt="logo") and add a small note instead so the endpoint
// fails gracefully instead of returning HTTP 500.
if (!extension_loaded('gd')) {
    error_log('[PDF] GD extension not present in PHP (Apache). Stripping logo from HTML to avoid Dompdf fatal error.');
    // remove any <img ... alt="logo" ...>
    $renderedHtml = preg_replace('/<img[^>]*alt="logo"[^>]*>/i', '', $renderedHtml);
    $renderedHtml = '<div style="color:#a00;font-size:12px;margin-bottom:8px;">(Nota: imagens não foram incluídas no PDF porque a extensão GD não está activa no servidor)</div>' . $renderedHtml;
}

// restore
chdir($prevCwd ?: __DIR__);
$_GET = $_GET_BACKUP;

$html = '';
// Use the full HTML produced by the renderer when available (preserves logo, styles)
if (!empty($renderedHtml)) {
    $html = $renderedHtml;
} else {
    // Fallback: if renderer didn't output HTML, build a minimal table representation
    // Try to extract items array from the renderer's scope; if empty, fail.
    if (empty($items)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "Failed to render punch list HTML and no items available.\n";
        exit;
    }
    $project = isset($_GET['project']) ? trim($_GET['project']) : '';
    $severity = isset($_GET['severity']) ? trim($_GET['severity']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family: DejaVu Sans, Arial, sans-serif;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;font-size:12px}th{background:#f4f4f4}</style></head><body>';
    $html .= '<h2>Open Punch Lists</h2>';
    $html .= '<p>Filters: ' . htmlspecialchars(($project ?: 'All')) . ' | ' . htmlspecialchars(($severity ?: 'All')) . ' | Search: ' . htmlspecialchars(($search ?: 'All')) . '</p>';
    $html .= '<table><thead><tr><th>Project</th><th>EPC</th><th>Description</th><th>Priority</th><th>Assigned</th><th>Date</th></tr></thead><tbody>';

    foreach ($items as $it) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($it['project_name'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['epc_company'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['description'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['severity_level'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['assigned_to'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['created_at'] ?? '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></body></html>';
}

// Apply filters from query string (in case fallback was used or items need re-filtering)
$project = isset($_GET['project']) ? trim($_GET['project']) : '';
$severity = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($project !== '') {
    $items = array_filter($items, function ($it) use ($project) {
        return (isset($it['project_name']) && $it['project_name'] === $project);
    });
}
if ($severity !== '') {
    $items = array_filter($items, function ($it) use ($severity) {
        return (isset($it['severity_level']) && $it['severity_level'] === $severity);
    });
}
if ($search !== '') {
    $s = mb_strtolower($search);
    $items = array_filter($items, function ($it) use ($s) {
        return (mb_stripos($it['project_name'] ?? '', $s) !== false) ||
            (mb_stripos($it['epc_company'] ?? '', $s) !== false) ||
            (mb_stripos($it['description'] ?? '', $s) !== false);
    });
}

// If we didn't get a full rendered HTML (from the renderer above), build a minimal table representation as a fallback.
if (empty($html)) {
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family: DejaVu Sans, Arial, sans-serif;}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;font-size:12px}th{background:#f4f4f4}</style></head><body>';
    $html .= '<h2>Open Punch Lists</h2>';
    $html .= '<p>Filters: ' . htmlspecialchars(($project ?: 'All')) . ' | ' . htmlspecialchars(($severity ?: 'All')) . ' | Search: ' . htmlspecialchars(($search ?: 'All')) . '</p>';
    $html .= '<table><thead><tr><th>Project</th><th>EPC</th><th>Description</th><th>Priority</th><th>Assigned</th><th>Date</th></tr></thead><tbody>';

    foreach ($items as $it) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($it['project_name'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['epc_company'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['description'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['severity_level'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['assigned_to'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['created_at'] ?? '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></body></html>';
}

$options = new Options();
$options->set('isRemoteEnabled', true);
// Use the HTML5 parser to ensure data-URIs and modern markup are handled correctly
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
try {
    $dompdf->render();
} catch (Throwable $e) {
    // Log detailed error and return readable message instead of letting PHP die with a fatal
    error_log('[PDF] Dompdf render failed: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "Erro ao gerar PDF: " . htmlspecialchars($e->getMessage()) . "\nVerifique os logs do servidor (apache error.log) para mais detalhes.";
    exit;
}
// If we created a temporary JPEG logo file, add it to the top-left of the first page directly
$tmpLogo = __DIR__ . '/tools/logo_for_pdf.jpg';
if (file_exists($tmpLogo)) {
    try {
        $canvas = $dompdf->getCanvas();
        $pageHeight = $canvas->get_height();
        // Position 20pt from left, 20pt from top (convert top to y coordinate in points)
        $x = 20;
        $imgH = 48; // points (approx); width set to auto by preserving aspect ratio isn't supported here, so set width
        $imgW = 140; // points
        $y = $pageHeight - 20 - $imgH;
        $canvas->image($tmpLogo, $x, $y, $imgW, $imgH);
    } catch (Throwable $e) {
        // Non-fatal: if canvas/image fails, continue without overlay
        error_log('Logo overlay failed: ' . $e->getMessage());
    }
}
$filename = 'Open_Punch_Lists.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
