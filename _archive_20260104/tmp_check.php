<?php
$s = file_get_contents(__DIR__ . '/comissionamento.php');
echo 'BRACE_DIFF: ' . (substr_count($s, '{') - substr_count($s, '}')) . PHP_EOL;
echo 'TRY_COUNT: ' . substr_count($s, 'try {') . ' CATCH_COUNT: ' . substr_count($s, 'catch (') . PHP_EOL;
