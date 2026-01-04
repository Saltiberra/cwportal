<?php
// Download Dompdf v1.2.2 release zip and extract into vendor/dompdf/dompdf
$root = __DIR__ . '/..';
$zipPath = $root . '/vendor/dompdf-1.2.2.zip';
$destDir = $root . '/vendor/dompdf';
$downloadUrl = 'https://github.com/dompdf/dompdf/releases/download/v1.2.2/dompdf-1.2.2.zip';

if (!is_dir($root . '/vendor')) mkdir($root . '/vendor', 0777, true);

echo "Downloading $downloadUrl...\n";
$ctx = stream_context_create(['http' => ['timeout' => 60]]);
$data = @file_get_contents($downloadUrl, false, $ctx);
if ($data === false) {
    echo "ERROR: Could not download $downloadUrl\n";
    exit(1);
}

file_put_contents($zipPath, $data);

$zip = new ZipArchive();
if ($zip->open($zipPath) === TRUE) {
    // Extract to vendor/dompdf
    // First remove existing dest
    if (is_dir($destDir)) {
        // recursive remove
        $it = new RecursiveDirectoryIterator($destDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getRealPath());
            else unlink($file->getRealPath());
        }
        rmdir($destDir);
    }
    mkdir($destDir, 0777, true);
    $zip->extractTo($destDir);
    $zip->close();
    // After extraction, move the inner folder to vendor/dompdf/dompdf if necessary
    $inner = $destDir . '/dompdf-1.2.2';
    $target = $destDir . '/dompdf';
    if (is_dir($inner)) {
        // move contents
        rename($inner, $target);
    } else {
        // try to detect a dompdf folder inside
        foreach (scandir($destDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (is_dir($destDir . '/' . $f) && stripos($f, 'dompdf') !== false) {
                rename($destDir . '/' . $f, $target);
                break;
            }
        }
    }
    // remove zip
    @unlink($zipPath);
    echo "OK: dompdf extracted to vendor/dompdf/dompdf\n";
    exit(0);
} else {
    echo "ERROR: Could not open zip file\n";
    exit(1);
}
