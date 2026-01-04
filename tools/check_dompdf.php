<?php
try {
    require __DIR__ . '/../vendor/autoload.php';
    echo class_exists('Dompdf\\Dompdf') ? "OK: Dompdf available\n" : "MISSING: Dompdf not present\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
