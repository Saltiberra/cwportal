<?php
// Simple HTTP fetch test (requires XAMPP running and the site accessible at http://localhost/cleanwattsportal/)
$url = 'http://localhost/cleanwattsportal/ajax/manage_field_supervision.php?action=list_project_notes&project_id=' . urlencode($argv[1] ?? '');
if (!$argv[1]) {
    echo "Usage: php http_test_list_project_notes.php <project_id>\n";
    exit(1);
}
$opts = stream_context_create(['http' => ['method' => 'GET', 'header' => 'Cookie: PHPSESSID=' . session_id() . '\r\n']]);
$response = @file_get_contents($url, false, $opts);
if ($response === false) {
    echo "HTTP request failed\n";
    exit(1);
}
echo $response . PHP_EOL;
