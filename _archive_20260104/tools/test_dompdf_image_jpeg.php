<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Options;
use Dompdf\Dompdf;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dom = new Dompdf($options);
$logo = __DIR__ . '/../Logos/Logo_CW full color horizontal.png';
if (!file_exists($logo)) {
    echo "MISSING LOGO FILE: $logo\n";
    exit(1);
}
// Use GD to convert to JPEG in memory
$im = imagecreatefrompng($logo);
ob_start();
imagejpeg($im, null, 90);
$jpeg = ob_get_clean();
imagedestroy($im);
$base = base64_encode($jpeg);
$html = '<!doctype html><html><body><h1>JPEG data URI test</h1><img src="data:image/jpeg;base64,' . $base . '" style="height:48px;"/></body></html>';
$dom->loadHtml($html);
$dom->setPaper('A4', 'portrait');
$dom->render();
file_put_contents(__DIR__ . '/dompdf_test_output_jpeg.bin', $dom->output());
echo "Wrote test PDF (jpeg data URI)\n";
