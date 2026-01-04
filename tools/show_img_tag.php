<?php
$html = file_get_contents(__DIR__ . '/punch_rendered.html');
if (preg_match('/<img[^>]+>/i', $html, $m)) {
    echo $m[0] . PHP_EOL;
} else {
    echo "NO IMG TAG FOUND\n";
}
