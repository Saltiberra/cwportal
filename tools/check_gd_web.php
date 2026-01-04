<?php
header('Content-Type: text/plain; charset=utf-8');
echo "php_version: " . PHP_VERSION . PHP_EOL;
echo "php_ini: " . (php_ini_loaded_file() ?: 'none') . PHP_EOL;
echo "gd_loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . PHP_EOL;
echo "gd_version: " . (extension_loaded('gd') && function_exists('gd_info') ? gd_info()['GD Version'] : 'n/a') . PHP_EOL;
