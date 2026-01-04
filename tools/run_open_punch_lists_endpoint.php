<?php
// Run the real endpoint locally and capture JSON output (bypass auth by setting a session)
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'dev';
$_SESSION['role'] = 'admin';

ob_start();
include __DIR__ . '/../ajax/get_open_punch_lists.php';
$output = ob_get_clean();
header('Content-Type: text/plain; charset=utf-8');
echo "Captured output (truncated):\n\n";
// Pretty-print the result to check severity of PL014
$json = json_decode($output, true);
if (!$json) {
    echo "Could not decode JSON output. Raw:\n" . substr($output, 0, 8000) . "\n";
} else {
    echo "success: " . ($json['success'] ? 'true' : 'false') . " count: " . ($json['count'] ?? 0) . "\n\n";
    foreach (($json['data'] ?? []) as $item) {
        if (strpos($item['description'] ?? '', 'Do contador de Consumo') !== false || ($item['id'] ?? 0) < 0) {
            echo "id=" . ($item['id'] ?? '') . " report_id=" . ($item['report_id'] ?? '') . " sev=" . ($item['severity_level'] ?? '') . " desc=" . substr($item['description'] ?? '', 0, 120) . "\n";
        }
    }
}
