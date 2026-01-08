<?php

/**
 * Modern Professional Commissioning Report Generator
 * Optimized for perfect PDF export with intelligent page breaks
 */

// Start session
require_once 'includes/auth.php';
requireLogin();

// Include database connection
require_once 'config/database.php';
require_once 'includes/load_report_data.php';

// Get current user
$user = getCurrentUser();

// Check if report ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$reportId = intval($_GET['id']);
$userId = $user['id'];

// Get report data
try {
    $stmt = $pdo->prepare("
        SELECT
            cr.*,
            e.name AS epc_name,
            r.name AS representative_name,
            r.phone AS representative_phone,
            r.email AS representative_email,
            crsp.name AS commissioning_responsible_name
        FROM
            commissioning_reports cr
        LEFT JOIN
            epcs e ON cr.epc_id = e.id
        LEFT JOIN
            representatives r ON cr.representative_id = r.id
        LEFT JOIN
            commissioning_responsibles crsp ON cr.commissioning_responsible_id = crsp.id
        WHERE
            cr.id = ? AND cr.is_deleted = FALSE
    ");

    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if (!$report) {
        $_SESSION['error'] = 'Report not found.';
        exit;
    }

    // Get equipment data
    $stmt = $pdo->prepare("SELECT * FROM report_equipment WHERE report_id = ? ORDER BY equipment_type");
    $stmt->execute([$reportId]);
    $equipment = $stmt->fetchAll();

    // Get layout data
    $stmt = $pdo->prepare("SELECT * FROM report_system_layout WHERE report_id = ? ORDER BY sort_order");
    $stmt->execute([$reportId]);
    $layout = $stmt->fetchAll();

    $mpptStmt = $pdo->prepare("SELECT * FROM mppt_string_measurements WHERE report_id = ? ORDER BY inverter_index, mppt, string_num");
    $mpptStmt->execute([$reportId]);
    $mpptMeasurements = $mpptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error retrieving report: ' . $e->getMessage();
    header('Location: reports.php');
    exit;
}

// Format date
$formattedDate = date('F j, Y', strtotime($report['date']));

// Helper functions
function formatCharacteristics($characteristics)
{
    if (empty($characteristics)) return '-';
    $result = '';
    foreach (explode('|', $characteristics) as $part) {
        $result .= trim($part) . '<br>';
    }
    return $result;
}

// Format numbers to show up to a fixed number of decimals, but trim unnecessary trailing zeros
function number_format_exact($num, $decimals = 2)
{
    if ($num === null || $num === '') return '-';
    $s = number_format((float)str_replace(',', '.', $num), $decimals, '.', '');
    $s = rtrim($s, '0');
    $s = rtrim($s, '.');
    return $s;
}

// Filter equipment by type
$pvModules = array_filter(
    $equipment,
    fn($eq) =>
    isset($eq['equipment_type']) &&
        preg_match('/pv.*module/i', $eq['equipment_type'])
);

$inverters = array_filter(
    $equipment,
    fn($eq) =>
    isset($eq['equipment_type']) &&
        preg_match('/inverter/i', $eq['equipment_type'])
);

$protectionItems = array_filter(
    $equipment,
    fn($eq) =>
    isset($eq['equipment_type']) &&
        preg_match('/protection.*circuit.*breaker/i', $eq['equipment_type'])
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissioning Report - <?php echo htmlspecialchars($report['project_name']); ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --primary: #2CCCD3;
            --primary-dark: #254A5D;
            --secondary: #186C7F;
            --success: #26989D;
            --text-dark: #101D20;
            --text-muted: #6B797D;
            --bg-light: #f5f7fa;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            font-size: 14px;
        }

        /* PDF Page Structure - Each section is a logical page */
        .pdf-page {
            background: white;
            margin: 0 auto 30px;
            padding: 40px;
            max-width: 210mm;
            /* A4 width */
            min-height: 297mm;
            /* A4 height */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* Modern Header */
        .report-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px;
            margin: -40px -40px 30px -40px;
            border-radius: 0;
        }

        .report-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .report-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item i {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Section Styling */
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary);
            font-size: 24px;
        }

        /* Data Grid - Compact and Clean */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .data-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }

        .data-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .data-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }

        /* Compact Equipment Cards */
        .equipment-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid var(--primary);
        }

        .equipment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .equipment-badge {
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .equipment-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            flex: 1;
        }

        .equipment-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
        }

        .equipment-details>div {
            flex: 1;
            min-width: 150px;
        }

        .equipment-details strong {
            color: var(--text-muted);
            font-weight: 600;
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        /* Tables */
        table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 12px;
        }

        table th {
            background: var(--primary);
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #dee2e6;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        /* Action Bar */
        .action-bar {
            background: white;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn-modern {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 204, 211, 0.3);
        }

        /* Print Optimization */
        @media print {
            body {
                background: white;
            }

            .action-bar,
            .no-print {
                display: none !important;
            }

            .pdf-page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                min-height: 0;
            }

            .pdf-page:last-child {
                page-break-after: auto;
            }

            @page {
                size: A4;
                margin: 0;
            }
        }

        /* Page Break Control */
        .page-break-before {
            page-break-before: always;
            break-before: page;
        }

        .avoid-break {
            page-break-inside: avoid;
            break-inside: avoid;
        }
    </style>
</head>

<body>
    <!-- Action Bar (screen only) -->
    <div class="action-bar no-print">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="commissioning_dashboard.php" class="btn btn-outline-secondary btn-modern me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                    <a href="comissionamento.php?report_id=<?php echo $reportId; ?>" class="btn btn-success btn-modern">
                        <i class="fas fa-edit me-2"></i>Edit Report
                    </a>
                </div>
                <div>
                    <button id="downloadPdfBtn" class="btn btn-primary-modern">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="report-content">
        <!-- PAGE 1: Cover & General Information -->
        <div class="pdf-page" data-page="general">
            <div class="report-header">
                <h1 class="report-title">
                    <i class="fas fa-solar-panel me-3"></i>
                    Commissioning Report
                </h1>
                <div class="report-subtitle"><?php echo htmlspecialchars($report['project_name']); ?></div>

                <div class="report-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div>
                            <div style="font-size: 11px; opacity: 0.8;">Date</div>
                            <div style="font-weight: 600;"><?php echo $formattedDate; ?></div>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-hashtag"></i>
                        <div>
                            <div style="font-size: 11px; opacity: 0.8;">Report ID</div>
                            <div style="font-weight: 600;">COM-<?php echo str_pad($reportId, 5, '0', STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <div style="font-size: 11px; opacity: 0.8;">Location</div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($report['plant_location'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                General Information
            </h2>

            <div class="data-grid">
                <div class="data-item">
                    <div class="data-label">Project Name</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['project_name'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Plant Location</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['plant_location'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">GPS Coordinates</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['gps'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">CPE</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['cpe'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Installed Power (kWp)</div>
                    <div class="data-value"><?php echo isset($report['installed_power']) ? number_format($report['installed_power'], 2) . ' kWp' : '-'; ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Total Power (kWp)</div>
                    <div class="data-value"><?php echo isset($report['total_power']) ? number_format($report['total_power'], 2) . ' kWp' : '-'; ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Certified Power (kWp)</div>
                    <div class="data-value"><?php echo isset($report['certified_power']) ? number_format($report['certified_power'], 2) . ' kWp' : '-'; ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Commissioning Responsible</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['commissioning_responsible_name'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">EPC Company</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['epc_name'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Representative</div>
                    <div class="data-value">
                        <?php if (!empty($report['representative_name'])): ?>
                            <?php echo htmlspecialchars($report['representative_name']); ?><br>
                            <small style="font-size: 11px; color: var(--text-muted);">
                                <?php if (!empty($report['representative_phone'])): ?>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($report['representative_phone']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($report['representative_email'])): ?>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($report['representative_email']); ?>
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>
                <div class="data-item">
                    <div class="data-label">Responsible Person</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['responsible'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Technician</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['technician'] ?? '-'); ?></div>
                </div>
            </div>
        </div>

        <!-- PAGE 2: PV Modules & System Layout -->
        <div class="pdf-page page-break-before" data-page="pv-modules">
            <h2 class="section-title">
                <i class="fas fa-solar-panel"></i>
                PV Modules
            </h2>

            <?php if (empty($pvModules)): ?>
                <p class="text-muted">No PV modules recorded for this report.</p>
            <?php else: ?>
                <?php
                $aggregateKW = 0.0;
                foreach ($pvModules as $mod) {
                    $qty = isset($mod['quantity']) ? intval($mod['quantity']) : 1;
                    $powerVal = $mod['power_rating'] ?? $mod['power'] ?? 0;
                    $powerNum = is_numeric($powerVal) ? floatval($powerVal) : 0;
                    $powerKW = $powerNum / 1000.0;
                    $aggregateKW += $powerKW * $qty;
                }
                ?>

                <div class="equipment-item" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left-color: #2196F3;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Total Modules:</strong> <?php echo count($pvModules); ?> groups
                        </div>
                        <div>
                            <strong>Aggregate Power:</strong> <?php echo number_format($aggregateKW, 3); ?> kW
                        </div>
                    </div>
                </div>

                <?php $idx = 0;
                foreach ($pvModules as $mod): $idx++; ?>
                    <?php
                    $qty = isset($mod['quantity']) ? intval($mod['quantity']) : 1;
                    $powerVal = $mod['power_rating'] ?? $mod['power'] ?? 0;
                    $powerNum = is_numeric($powerVal) ? floatval($powerVal) : 0;
                    $powerKW = $powerNum / 1000.0;
                    ?>
                    <div class="equipment-item avoid-break">
                        <div class="equipment-header">
                            <div class="equipment-badge"><?php echo $qty; ?></div>
                            <div class="equipment-name">
                                <?php echo htmlspecialchars(($mod['brand'] ?: '') . ' ' . ($mod['model'] ?: 'PV Module')); ?>
                            </div>
                        </div>
                        <div class="equipment-details">
                            <div>
                                <strong>Model</strong>
                                <?php echo htmlspecialchars($mod['model'] ?: '-'); ?>
                            </div>
                            <div>
                                <strong>Manufacturer</strong>
                                <?php echo htmlspecialchars($mod['brand'] ?: '-'); ?>
                            </div>
                            <div>
                                <strong>Power (per module)</strong>
                                <?php echo number_format($powerKW, 3); ?> kW
                            </div>
                            <div>
                                <strong>Total Power (group)</strong>
                                <?php echo number_format($powerKW * $qty, 3); ?> kW
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($layout)): ?>
                <h3 class="section-title mt-4" style="font-size: 18px;">
                    <i class="fas fa-layer-group"></i>
                    System Layout
                </h3>

                <div class="row">
                    <?php foreach ($layout as $l): ?>
                        <div class="col-md-6 mb-3">
                            <div class="equipment-item avoid-break">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                    <div><strong>Roof ID:</strong> <?php echo htmlspecialchars($l['roof_id'] ?? '-'); ?></div>
                                    <div><strong>Quantity:</strong> <?php echo htmlspecialchars($l['quantity'] ?? '-'); ?></div>
                                    <div><strong>Azimuth:</strong> <?php echo htmlspecialchars($l['azimuth'] ?? '-'); ?></div>
                                    <div><strong>Tilt:</strong> <?php echo htmlspecialchars($l['tilt'] ?? '-'); ?></div>
                                    <div style="grid-column: 1 / -1;"><strong>Mounting:</strong> <?php echo htmlspecialchars($l['mounting'] ?? '-'); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- PAGE 3: Inverters -->
        <?php if (!empty($inverters)): ?>
            <div class="pdf-page page-break-before" data-page="inverters">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Inverters
                </h2>

                <?php $idx = 0;
                foreach ($inverters as $inv): $idx++; ?>
                    <?php
                    $brand = trim($inv['brand'] ?? '');
                    $model = trim($inv['model'] ?? '');
                    $qty = isset($inv['quantity']) ? intval($inv['quantity']) : 1;
                    $status = ucfirst(strtolower($inv['deployment_status'] ?? ''));
                    $chars = $inv['characteristics'] ?? '';

                    // Parse characteristics
                    $serial = '';
                    $location = $inv['location'] ?? '';
                    $maxOut = '';
                    if ($chars) {
                        if (preg_match('/Serial:\s*([^|]+)/i', $chars, $m)) $serial = trim($m[1]);
                        if (preg_match('/Location:\s*([^|]+)/i', $chars, $m)) $location = trim($m[1]);
                        if (preg_match('/Max Output Current:\s*([^|]+)/i', $chars, $m)) $maxOut = trim($m[1]);
                    }
                    ?>
                    <div class="equipment-item avoid-break">
                        <div class="equipment-header">
                            <div class="equipment-badge"><?php echo $idx; ?></div>
                            <div class="equipment-name">
                                <?php echo htmlspecialchars(($brand ?: '') . ' ' . ($model ?: 'Inverter')); ?>
                            </div>
                            <?php if ($status): ?>
                                <span class="badge bg-<?php echo $status === 'New' ? 'success' : 'secondary'; ?>" style="padding: 4px 12px;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="equipment-details">
                            <div>
                                <strong>Brand</strong>
                                <?php echo htmlspecialchars($brand ?: '-'); ?>
                            </div>
                            <div>
                                <strong>Model</strong>
                                <?php echo htmlspecialchars($model ?: '-'); ?>
                            </div>
                            <div>
                                <strong>Quantity</strong>
                                <?php echo $qty; ?>
                            </div>
                            <?php if ($serial): ?>
                                <div>
                                    <strong>Serial Number</strong>
                                    <?php echo htmlspecialchars($serial); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($location): ?>
                                <div>
                                    <strong>Location</strong>
                                    <?php echo htmlspecialchars($location); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($maxOut): ?>
                                <div>
                                    <strong>Max Output Current</strong>
                                    <?php echo htmlspecialchars($maxOut); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE 4: MPPT Measurements (if any) -->
        <?php if (!empty($mpptMeasurements)): ?>
            <div class="pdf-page page-break-before" data-page="mppt">
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i>
                    MPPT String Measurements
                </h2>

                <table>
                    <thead>
                        <tr>
                            <th>Inv</th>
                            <th>MPPT</th>
                            <th>Str</th>
                            <th>Voc (V)</th>
                            <th>Current (A)</th>
                            <th>Rins (Ω)</th>
                            <th>Irr (W/m²)</th>
                            <th>T (°C)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mpptMeasurements as $m): ?>
                            <tr class="avoid-break">
                                <?php $invDisplay = is_numeric($m['inverter_index'] ?? null) ? (intval($m['inverter_index']) + 1) : '-'; ?>
                                <td><?php echo htmlspecialchars($invDisplay); ?></td>
                                <td><?php echo htmlspecialchars($m['mppt'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($m['string_num'] ?? '-'); ?></td>
                                <td><?php echo ($m['voc'] !== null && $m['voc'] !== '') ? htmlspecialchars((string)$m['voc']) : '-'; ?></td>
                                <td><?php echo ($m['current'] !== null && $m['current'] !== '') ? htmlspecialchars((string)$m['current']) : '-'; ?></td>
                                <td><?php echo ($m['rins'] !== null && $m['rins'] !== '') ? htmlspecialchars((string)$m['rins']) : '-'; ?></td>
                                <td><?php echo ($m['irr'] !== null && $m['irr'] !== '') ? htmlspecialchars((string)$m['irr']) : '-'; ?></td>
                                <td><?php echo ($m['temp'] !== null && $m['temp'] !== '') ? htmlspecialchars((string)$m['temp']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- PAGE 5: Protection (if any) -->
        <?php if (!empty($protectionItems)): ?>
            <div class="pdf-page page-break-before" data-page="protection">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Protection Systems
                </h2>

                <table>
                    <thead>
                        <tr>
                            <th>Scope</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Rated Current (A)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($protectionItems as $p): ?>
                            <?php
                            $scope = $p['scope'] ?? $p['equipment_subtype'] ?? '-';
                            $rated = $p['rated_current'] ?? '';
                            if (!$rated && !empty($p['characteristics'])) {
                                if (preg_match('/Rated\s+Current:\s*([0-9]+(?:\.[0-9]+)?)/i', $p['characteristics'], $m)) {
                                    $rated = trim($m[1]);
                                }
                            }
                            ?>
                            <tr class="avoid-break">
                                <td><?php echo htmlspecialchars($scope); ?></td>
                                <td><?php echo htmlspecialchars($p['brand'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($p['model'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rated ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- PDF Export Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        document.getElementById('downloadPdfBtn').addEventListener('click', async function() {
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const pages = document.querySelectorAll('.pdf-page');
            const reportId = 'COM-<?php echo str_pad($reportId, 5, '0', STR_PAD_LEFT); ?>';
            const project = '<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['project_name'] ?? 'Report'); ?>';

            for (let i = 0; i < pages.length; i++) {
                if (i > 0) pdf.addPage();

                const canvas = await html2canvas(pages[i], {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#FFFFFF'
                });

                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();

                pdf.addImage(imgData, 'JPEG', 0, 0, pageWidth, pageHeight);
            }

            pdf.save(`Commissioning_Report_${project}_${reportId}.pdf`);
        });
    </script>
</body>

</html>