<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
if (PHP_SAPI !== 'cli') requireLogin();
else echo "[SIM] CLI mode\n";

$reportId = 324;
header('Content-Type: text/plain; charset=utf-8');
echo "Simulating open punch lists for report_id={$reportId}\n\n";
try {
    $formattedData = [];
    // Primary source: report_punch_list (none here)
    $stmt = $pdo->prepare("SELECT * FROM report_punch_list WHERE report_id = ? ORDER BY id");
    $stmt->execute([$reportId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $item) {
        $sev = $item['issue_priority'] ?? $item['severity_level'] ?? $item['priority'] ?? '';
        $s = strtolower((string)$sev);
        $sevNorm = 'Low';
        if (in_array($s, ['high', 'severe', 'critical'])) $sevNorm = 'High';
        elseif (in_array($s, ['medium', 'major'])) $sevNorm = 'Medium';
        $formattedData[] = [
            'id' => (int)$item['id'],
            'report_id' => (int)$item['report_id'],
            'description' => $item['issue_description'] ?? $item['description'] ?? '',
            'severity_level' => $sevNorm,
            'created_at' => $item['created_at'] ?? ''
        ];
    }

    // Legacy source: report_equipment
    $stmt2 = $pdo->prepare("SELECT id, report_id, characteristics FROM report_equipment WHERE report_id = ? AND equipment_type IN ('Punch List Item','Punch List Item (migrated)') ORDER BY id");
    $stmt2->execute([$reportId]);
    $legacy = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($legacy as $row) {
        $chars = $row['characteristics'] ?? '';
        $getVal = function ($label) use ($chars) {
            $pattern = '/' . preg_quote($label, '/') . '\s*:\s*([^|]+)/i';
            if (preg_match($pattern, $chars, $m)) return trim($m[1]);
            return '';
        };
        $desc = $getVal('Description');
        $sev = $getVal('Severity');
        $opening = $getVal('Opening Date');
        $resp = $getVal('Responsible');
        $resolution = $getVal('Resolution Date');

        // Normalize to High/Medium/Low
        $sevNorm = 'Low';
        $s = strtolower($sev);
        if (in_array($s, ['high', 'alto', 'major', 'severe', 'severo', 'critical', 'crítico', 'critico'])) $sevNorm = 'High';
        elseif (in_array($s, ['medium', 'médio', 'medio'])) $sevNorm = 'Medium';
        elseif (in_array($s, ['minor', 'baixo', 'low'])) $sevNorm = 'Low';

        $formattedData[] = [
            'id' => -1 * (int)$row['id'],
            'report_id' => (int)$row['report_id'],
            'description' => $desc,
            'severity_level' => $sevNorm,
            'created_at' => $opening ?: ''
        ];
    }

    echo "Formatted items: " . count($formattedData) . "\n";
    foreach ($formattedData as $f) {
        echo "id={$f['id']} report_id={$f['report_id']} sev={$f['severity_level']} desc=" . substr($f['description'], 0, 120) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
