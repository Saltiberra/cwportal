<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Options;
use Dompdf\Dompdf;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dom = new Dompdf($options);
$html = '<!doctype html><html><body><h1>Img test</h1><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=" alt="dot"></body></html>';
$dom->loadHtml($html);
$dom->setPaper('A4', 'portrait');
$dom->render();
file_put_contents(__DIR__ . '/dompdf_test_output.bin', $dom->output());
echo "Wrote test PDF\n";
