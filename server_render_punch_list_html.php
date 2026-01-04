<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/database.php';

// Accept filters
$project = isset($_GET['project']) ? trim($_GET['project']) : '';
$severity = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Reuse ajax/get_open_punch_lists.php logic by including it in a safe way
// The AJAX script outputs JSON; we can include it and decode the data.
chdir(__DIR__ . '/ajax');
ob_start();
include __DIR__ . '/ajax/get_open_punch_lists.php';
$json = ob_get_clean();
chdir(__DIR__);

$data = json_decode($json, true);
$items = [];
if ($data && isset($data['success']) && $data['success']) {
    $items = $data['data'] ?? [];
}

// Apply server-side filters again for safety (force strict match for severity)
if ($project !== '') {
    $items = array_filter($items, fn($it) => (isset($it['project_name']) && $it['project_name'] === $project));
}
// Ensure severity filter is case-insensitive
if ($severity !== '') {
    $items = array_filter($items, fn($it) => isset($it['severity_level']) && strtolower($it['severity_level']) === strtolower($severity));
}
if ($search !== '') {
    $s = mb_strtolower($search);
    $items = array_filter($items, fn($it) => (mb_stripos($it['project_name'] ?? '', $s) !== false) || (mb_stripos($it['epc_company'] ?? '', $s) !== false) || (mb_stripos($it['description'] ?? '', $s) !== false));
}

// Normalize severity badge class
function severity_badge($sev)
{
    // Match web UI: High = danger (red), Medium = warning (orange), Low = success (green)
    $map = ['High' => 'danger', 'Medium' => 'warning', 'Low' => 'success'];
    $k = $sev ?: 'Low';
    $cls = $map[$k] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . htmlspecialchars($sev) . '</span>';
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Open Punch Lists</title>
    <style>
        /* Inline styles tuned for Dompdf rendering to mimic site look */
        @page {
            margin: 20mm
        }

        body {
            font-family: 'DejaVu Sans', Inter, Arial, sans-serif;
            padding: 0;
            color: #101D20;
            background: #ffffff;
        }

        .pdf-container {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 28px;
        }

        .report-header {
            background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%);
            color: #fff;
            padding: 18px 20px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .report-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0
        }

        .filters {
            margin: 10px 0 18px 0;
            color: #555;
            font-size: 14px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            background: #f4f7f8;
            color: #222;
            font-weight: 600;
            text-align: left;
            padding: 10px;
            border: 1px solid #e1e7e9
        }

        tbody td {
            padding: 10px;
            vertical-align: top;
            border: 1px solid #e7ecee
        }

        tbody tr:nth-child(even) {
            background: #ffffff
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            vertical-align: middle;
        }

        .bg-danger {
            background: #e74c3c
        }

        .bg-warning {
            background: #fd7e14;
            color: #fff
        }

        .bg-success {
            background: #28a745
        }

        /* Assigned-to badge (teal) to match web UI */
        .bg-assigned {
            background: #2CCCD3
        }

        /* Make long descriptions wrap nicely */
        td {
            word-wrap: break-word;
        }

        /* Reduce table font slightly to fit */
        .small {
            font-size: 12px
        }
    </style>
</head>

<body>
    <div class="report-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <?php
            // Logo removido do PDF conforme solicitado
            ?>
            <div>
                <div class="report-title">Open Punch Lists</div>
                <div class="filters">Filters: <?php echo htmlspecialchars($project ?: 'All'); ?> | <?php echo htmlspecialchars($severity ?: 'All'); ?> | Search: <?php echo htmlspecialchars($search ?: 'All'); ?></div>
            </div>
        </div>
    </div>

    <table class="table table-bordered table-striped" role="table">
        <thead>
            <tr>
                <th style="width:20%">Project</th>
                <th style="width:14%">EPC</th>
                <th style="width:38%">Description</th>
                <th style="width:10%">Priority</th>
                <th style="width:10%">Assigned</th>
                <th style="width:8%">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($items as $it) {
                echo '<tr>';
                echo '<td class="small">' . htmlspecialchars($it['project_name'] ?? '') . '</td>';
                echo '<td class="small">' . htmlspecialchars($it['epc_company'] ?? '') . '</td>';
                echo '<td>' . nl2br(htmlspecialchars($it['description'] ?? '')) . '</td>';
                echo '<td style="text-align:center">' . (isset($it['severity_level']) ? severity_badge($it['severity_level']) : '') . '</td>';
                $assignedLabel = '';
                if (!empty($it['assigned_to'])) {
                    $assignedLabel = '<span class="badge bg-assigned">' . htmlspecialchars($it['assigned_to']) . '</span>';
                }
                echo '<td class="small" style="text-align:center">' . $assignedLabel . '</td>';
                echo '<td class="small">' . htmlspecialchars($it['created_at'] ?? '') . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</body>

</html>