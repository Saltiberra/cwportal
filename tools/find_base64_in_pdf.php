<?php
$html = file_get_contents(__DIR__ . '/punch_rendered.html');
if (!preg_match('/data:image\/jpeg;base64,([A-Za-z0-9+\/]+=*)/', $html, $m)) {
    echo "NO_BASE64_FOUND_IN_HTML\n";
    exit(1);
}
$snippet = substr($m[1], 0, 40);
$bin = file_get_contents(__DIR__ . '/punch_pdf_output.bin');
$found = strpos($bin, $snippet) !== false;
echo "snippet:" . $snippet . PHP_EOL;
echo ($found ? 'FOUND_IN_PDF' : 'NOTFOUND') . PHP_EOL;
