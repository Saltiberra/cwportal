<?php
$path = __DIR__ . '/punch_pdf_output.bin';
if (!file_exists($path)) {
    echo "MISSING_FILE\n";
    exit(1);
}
$bin = file_get_contents($path);
$has_png = strpos($bin, "\x89PNG") !== false;
$has_jpeg = strpos($bin, "\xFF\xD8\xFF") !== false;
$has_subtype_image = strpos($bin, '/Subtype /Image') !== false;
echo "PNG:" . ($has_png ? 'YES' : 'NO') . "\n";
echo "JPEG:" . ($has_jpeg ? 'YES' : 'NO') . "\n";
echo "/Subtype /Image:" . ($has_subtype_image ? 'YES' : 'NO') . "\n";
echo "filesize:" . filesize($path) . "\n";
