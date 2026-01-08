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

    // Load merged report data which includes parsed string measurements (fallback + mppt merged)
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

function formatCableText($text)
{
    if (empty($text)) return '';
    // Replace problematic UTF-8 encoded mm² with clean version
    // Also handle cases where it might be "mm²2" (malformed double)
    $text = preg_replace('/mm[\xC2\xB2]+2?/u', 'mm²', $text);
    return $text;
}

function removeAccents($text)
{
    // Remove accents from Portuguese and other Latin characters
    // é ó ã õ â ê ç etc. → e o a o a e c
    $accents = [
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ç' => 'c',
        'ñ' => 'n',
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ç' => 'C',
        'Ñ' => 'N'
    ];

    return strtr($text, $accents);
}

// Format numbers to show up to a fixed number of decimals, but trim unnecessary trailing zeros
function number_format_exact($num, $decimals = 2)
{
    if ($num === null || $num === '') return '-';
    $s = number_format((float)$num, $decimals, '.', '');
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

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Leaflet CSS for map preview (commissioning) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS for map preview -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Turf.js/6.5.0/turf.min.js"></script>

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
                    <div class="data-value" data-gps="<?php echo htmlspecialchars($report['gps'] ?? ''); ?>"><?php echo htmlspecialchars($report['gps'] ?? '-'); ?></div>
                </div>
                <div class="data-item">
                    <div class="data-label">Measured Area</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['map_area_m2'] ?? '-'); ?> m²</div>
                </div>
                <div class="data-item">
                    <div class="data-label">Azimuth</div>
                    <div class="data-value"><?php echo htmlspecialchars($report['map_azimuth_deg'] ?? '-'); ?>°</div>
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

            <!-- Installation Site Map - inside General Information -->
            <?php if (!empty($report['map_polygon_coords']) || !empty($report['gps'])): ?>
                <div class="map-section" style="margin-top: 25px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-map-location-dot" style="color: var(--primary);"></i>
                        Installation Site Map
                    </h3>
                    <div id="report-map-comm" style="width: 100%; height: 200px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa;"></div>
                    <input type="hidden" id="comm_existing_polygon_coords" value="<?php echo htmlspecialchars($report['map_polygon_coords'] ?? ''); ?>">
                    <script>
                        try {
                            const REPORT_POLYGON_COORDS = <?php echo (!empty($report['map_polygon_coords']) && $report['map_polygon_coords'] !== 'null') ? json_encode(json_decode($report['map_polygon_coords'], true)) : 'null'; ?>;
                            window.REPORT_POLYGON_COORDS = REPORT_POLYGON_COORDS;
                        } catch (e) {
                            window.REPORT_POLYGON_COORDS = null;
                        }
                    </script>
                </div>
            <?php endif; ?>
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
                                <?php echo number_format($powerKW * 1000, 0); ?> W
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
                    $locationCol = $inv['location'] ?? '';

                    // Parse ALL characteristics from the inverter
                    $serial = '';
                    $location = $locationCol;
                    $maxOut = '';
                    $cb = '';
                    $cbRated = '';
                    $diff = '';
                    $diffRated = '';
                    $diffMA = '';
                    $cable = '';
                    $datasheet = '';

                    if ($chars) {
                        $parts = array_map('trim', explode('|', $chars));
                        foreach ($parts as $p) {
                            if (stripos($p, 'Serial:') === 0) $serial = trim(substr($p, strlen('Serial:')));
                            elseif (stripos($p, 'Location:') === 0 && !$location) $location = trim(substr($p, strlen('Location:')));
                            elseif (stripos($p, 'Max Output Current:') === 0) $maxOut = trim(substr($p, strlen('Max Output Current:')));
                            elseif (stripos($p, 'Circuit Breaker:') === 0) {
                                $cb = trim(substr($p, strlen('Circuit Breaker:')));
                                if (preg_match('/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A/i', $cb, $m)) {
                                    $cbRated = str_replace(',', '.', $m[1]);
                                }
                            } elseif (stripos($p, 'Differential:') === 0) {
                                $diff = trim(substr($p, strlen('Differential:')));
                                // Extract rated current (e.g., "Rated: 25A")
                                if (preg_match('/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A/i', $diff, $m)) {
                                    $diffRated = str_replace(',', '.', $m[1]);
                                }
                                // Extract mA value (e.g., "30mA" or "30 mA")
                                if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)\s*mA/i', $diff, $m2)) {
                                    $diffMA = str_replace(',', '.', $m2[1]);
                                }
                            } elseif (stripos($p, 'Cable:') === 0) $cable = trim(substr($p, strlen('Cable:')));
                            elseif (stripos($p, 'Datasheet:') === 0) $datasheet = trim(substr($p, strlen('Datasheet:')));
                        }
                    }

                    // Format Circuit Breaker display
                    $cbBase = $cb;
                    if ($cbBase) {
                        $cbBase = trim(preg_replace('/Rated\s*:\s*[0-9]+(?:[\.,][0-9]+)?\s*A/i', '', $cbBase));
                    }
                    $cbText = $cbBase;
                    if ($cbRated) {
                        $cbText .= ($cbText ? ' ' : '') . '(' . $cbRated . 'A)';
                    }
                    $cbDisplay = $cbText ?: ($cb ?: '');

                    // Format Differential display
                    $diffBase = $diff;
                    if ($diffBase) {
                        $diffBase = trim(preg_replace('/Rated\s*:\s*[0-9]+(?:[\.,][0-9]+)?\s*A/i', '', $diffBase));
                        $diffBase = trim(preg_replace('/[0-9]+(?:[\.,][0-9]+)?\s*mA/i', '', $diffBase));
                    }
                    $diffTextParts = [];
                    if ($diffRated) $diffTextParts[] = $diffRated . 'A';
                    if ($diffMA) $diffTextParts[] = $diffMA . 'mA';
                    $diffText = $diffBase;
                    if (!empty($diffTextParts)) {
                        $diffText .= ($diffText ? ' ' : '') . '(' . implode(', ', $diffTextParts) . ')';
                    }
                    $diffDisplay = $diffText ?: ($diff ?: '');
                    ?>
                    <div class="equipment-item avoid-break" style="margin-bottom: 20px;">
                        <div class="equipment-header">
                            <div class="equipment-badge"><?php echo $idx; ?></div>
                            <div class="equipment-name">
                                <?php echo htmlspecialchars(trim(($brand ?: '') . ' ' . ($model ?: 'Inverter'))); ?>
                            </div>
                            <?php if ($status): ?>
                                <span class="badge bg-<?php echo $status === 'New' ? 'success' : ($status === 'Replacement' ? 'warning' : 'secondary'); ?>" style="padding: 4px 12px;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Basic Info -->
                        <div class="equipment-details" style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
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
                            <div>
                                <strong>Status</strong>
                                <?php echo htmlspecialchars($status ?: '-'); ?>
                            </div>
                        </div>

                        <!-- Technical Specs -->
                        <div class="equipment-details" style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                            <?php if ($serial): ?>
                                <div>
                                    <strong><i class="fas fa-barcode" style="color: var(--primary); margin-right: 4px;"></i> Serial Number</strong>
                                    <?php echo htmlspecialchars($serial); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($location): ?>
                                <div>
                                    <strong><i class="fas fa-location-dot" style="color: var(--primary); margin-right: 4px;"></i> Location</strong>
                                    <?php echo htmlspecialchars($location); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($maxOut): ?>
                                <div>
                                    <strong><i class="fas fa-bolt" style="color: var(--primary); margin-right: 4px;"></i> Max Output Current</strong>
                                    <?php echo htmlspecialchars($maxOut); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Protection Devices -->
                        <?php if ($cbDisplay || $diffDisplay || $cable): ?>
                            <div class="equipment-details">
                                <?php if ($cbDisplay): ?>
                                    <div style="min-width: 100%;">
                                        <strong><i class="fas fa-shield-alt" style="color: var(--primary); margin-right: 4px;"></i> Circuit Breaker</strong>
                                        <?php echo htmlspecialchars($cbDisplay); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($diffDisplay): ?>
                                    <div style="min-width: 100%;">
                                        <strong><i class="fas fa-plug" style="color: var(--primary); margin-right: 4px;"></i> Differential</strong>
                                        <?php echo htmlspecialchars($diffDisplay); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($cable): ?>
                                    <div style="min-width: 100%;">
                                        <strong><i class="fas fa-network-wired" style="color: var(--primary); margin-right: 4px;"></i> Cable</strong>
                                        <?php echo formatCableText(htmlspecialchars($cable, ENT_NOQUOTES, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($datasheet): ?>
                                    <div style="min-width: 100%;">
                                        <strong><i class="fas fa-file-lines" style="color: var(--primary); margin-right: 4px;"></i> Datasheet</strong>
                                        <a href="<?php echo htmlspecialchars($datasheet); ?>" target="_blank" style="color: var(--primary); text-decoration: none;">
                                            <?php echo htmlspecialchars($datasheet); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
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
                                <?php
                                $currentVal = ($m['current'] !== null && $m['current'] !== '') ? $m['current'] : ($m['isc'] ?? '');
                                ?>
                                <td><?php echo ($currentVal !== null && $currentVal !== '') ? htmlspecialchars((string)$currentVal) : '-'; ?></td>
                                <td><?php echo ($m['rins'] !== null && $m['rins'] !== '') ? htmlspecialchars((string)$m['rins']) : '-'; ?></td>
                                <td><?php echo ($m['irr'] !== null && $m['irr'] !== '') ? htmlspecialchars((string)$m['irr']) : '-'; ?></td>
                                <td><?php echo ($m['temp'] !== null && $m['temp'] !== '') ? htmlspecialchars((string)$m['temp']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- PAGE 5: Protection Systems -->
        <?php
        // Get all protection-related equipment
        $protectionCables = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                (strtolower(trim($eq['equipment_type'])) === 'protection - cable' ||
                    preg_match('/protection.*cable/i', $eq['equipment_type']))
        );

        $clamps = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'amperometric clamp'
        );

        $earth = null;
        $homopolar = null;
        foreach ($equipment as $eq) {
            if (isset($eq['equipment_type'])) {
                if (strtolower(trim($eq['equipment_type'])) === 'earth protection circuit') {
                    $earth = $eq;
                }
                if (strtolower(trim($eq['equipment_type'])) === 'homopolar protection') {
                    $homopolar = $eq;
                }
            }
        }

        $hasProtectionData = !empty($protectionItems) || !empty($protectionCables) || !empty($clamps) || $earth || $homopolar;
        ?>

        <?php if ($hasProtectionData): ?>
            <div class="pdf-page page-break-before" data-page="protection">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Protection Systems
                </h2>

                <!-- Circuit Breakers -->
                <?php if (!empty($protectionItems)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-bolt" style="color: var(--primary); margin-right: 8px;"></i>
                        Circuit Protection
                    </h3>
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
                                $scope = '';
                                $rated = $p['rated_current'] ?? '';
                                if (!empty($p['characteristics'])) {
                                    if (preg_match('/Scope:\s*([^|]+)/i', $p['characteristics'], $m)) {
                                        $scope = trim($m[1]);
                                    }
                                    if (!$rated && preg_match('/Rated\s+Current:\s*([0-9]+(?:\.[0-9]+)?)/i', $p['characteristics'], $m)) {
                                        $rated = trim($m[1]);
                                    }
                                }
                                $scope = $scope ?: ($p['scope'] ?? $p['equipment_subtype'] ?? '-');
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
                <?php endif; ?>

                <!-- Homopolar Protection -->
                <?php if ($homopolar): ?>
                    <?php
                    $homInstaller = '';
                    $homBrand = $homopolar['brand'] ?? '';
                    $homModel = $homopolar['model'] ?? '';
                    if (!empty($homopolar['characteristics'])) {
                        if (preg_match('/Installer:\s*([^|]+)/i', $homopolar['characteristics'], $m)) {
                            $homInstaller = trim($m[1]);
                        }
                        if (preg_match('/Brand:\s*([^|]+)/i', $homopolar['characteristics'], $m)) {
                            $homBrand = trim($m[1]);
                        }
                        if (preg_match('/Model:\s*([^|]+)/i', $homopolar['characteristics'], $m)) {
                            $homModel = trim($m[1]);
                        }
                    }
                    ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-plug" style="color: var(--primary); margin-right: 8px;"></i>
                        Homopolar Protection
                    </h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="data-label">Installer</div>
                            <div class="data-value"><?php echo htmlspecialchars($homInstaller ?: '-'); ?></div>
                        </div>
                        <div class="data-item">
                            <div class="data-label">Brand</div>
                            <div class="data-value"><?php echo htmlspecialchars($homBrand ?: '-'); ?></div>
                        </div>
                        <div class="data-item">
                            <div class="data-label">Model</div>
                            <div class="data-value"><?php echo htmlspecialchars($homModel ?: '-'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Protection Cables -->
                <?php if (!empty($protectionCables)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-network-wired" style="color: var(--primary); margin-right: 8px;"></i>
                        Protection Cables (PV Board / Point of Injection)
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Scope</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Size</th>
                                <th>Insulation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($protectionCables as $c): ?>
                                <?php
                                $scope = '';
                                $size = '';
                                $ins = '';
                                if (!empty($c['characteristics'])) {
                                    if (preg_match('/Scope:\s*([^|]+)/i', $c['characteristics'], $m)) {
                                        $scope = trim($m[1]);
                                    }
                                    if (preg_match('/Size:\s*([^|]+)/i', $c['characteristics'], $m)) {
                                        $size = trim($m[1]);
                                    }
                                    if (preg_match('/Insulation:\s*([^|]+)/i', $c['characteristics'], $m)) {
                                        $ins = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($scope ?: ($c['scope'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($c['brand'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($c['model'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($size ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ins ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Amperometric Clamp Measurements -->
                <?php if (!empty($clamps)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-gauge-high" style="color: var(--primary); margin-right: 8px;"></i>
                        Amperometric Clamp Measurements
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>L1 (A)</th>
                                <th>L2 (A)</th>
                                <th>L3 (A)</th>
                                <th>Match with meter?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clamps as $c): ?>
                                <?php
                                $equipmentName = $c['brand'] ?? '';
                                $l1 = '';
                                $l2 = '';
                                $l3 = '';
                                $match = '';
                                if (!empty($c['characteristics'])) {
                                    if (preg_match('/L1 Current:\s*([0-9]+(?:\.[0-9]+)?)A/i', $c['characteristics'], $m)) {
                                        $l1 = trim($m[1]);
                                    }
                                    if (preg_match('/L2 Current:\s*([0-9]+(?:\.[0-9]+)?)A/i', $c['characteristics'], $m)) {
                                        $l2 = trim($m[1]);
                                    }
                                    if (preg_match('/L3 Current:\s*([0-9]+(?:\.[0-9]+)?)A/i', $c['characteristics'], $m)) {
                                        $l3 = trim($m[1]);
                                    }
                                    if (preg_match('/Matches with meter:\s*(Yes|No)/i', $c['characteristics'], $m)) {
                                        $match = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($equipmentName ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($l1 ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($l2 ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($l3 ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($match ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Earth Protection Circuit -->
                <?php if ($earth): ?>
                    <?php
                    $resistance = '';
                    $reinforce = '';
                    if (!empty($earth['characteristics'])) {
                        if (preg_match('/Resistance:\s*([0-9]+(?:\.[0-9]+)?)/i', $earth['characteristics'], $m)) {
                            $resistance = trim($m[1]);
                        }
                        if (preg_match('/Earthing\/Reinforcement Needed:\s*(Yes|No)/i', $earth['characteristics'], $m)) {
                            $reinforce = trim($m[1]);
                        }
                    }
                    ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-bolt-lightning" style="color: var(--primary); margin-right: 8px;"></i>
                        Earth Protection Circuit
                    </h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <div class="data-label">Resistance (Ω)</div>
                            <div class="data-value"><?php echo htmlspecialchars($resistance ? $resistance . ' Ω' : '-'); ?></div>
                        </div>
                        <div class="data-item">
                            <div class="data-label">Reinforcement Needed</div>
                            <div class="data-value"><?php echo htmlspecialchars($reinforce ?: '-'); ?></div>
                        </div>
                    </div>
                    <?php if (strtolower($reinforce) === 'yes'): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 15px; border-radius: 4px;">
                            <i class="fas fa-exclamation-triangle" style="color: #856404; margin-right: 8px;"></i>
                            <strong style="color: #856404;">Recommendation:</strong>
                            <span style="color: #856404;">Resistance above 10Ω - Earth reinforcement is recommended</span>
                        </div>
                    <?php endif; ?>
                    <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 12px; margin-top: 15px; border-radius: 4px; font-size: 12px; color: #0c5460;">
                        <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                        <strong>Regulatory Information:</strong><br>
                        In Portugal, the acceptable earth values in an electrical installation are regulated by the Safety Regulations for Low Voltage Electrical Installations (RSIEBT).
                        According to this regulation, the maximum permissible value for earth resistance is <strong>100 Ω</strong> for installations with a nominal current of less than 32 A and <strong>10 Ω</strong> for installations with a nominal current of more than 32 A.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE 6: Telemetry & Communications -->
        <?php
        // Get telemetry-related equipment
        $credentials = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'telemetry - credential'
        );

        $communications = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'communications'
        );

        $smartMeters = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'telemetry - meter'
        );

        $energyMeters = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'energy meter'
        );

        $hasTelemetryData = !empty($credentials) || !empty($communications) || !empty($smartMeters) || !empty($energyMeters);

        // Build inverter reference map
        $inverterMap = [];
        foreach ($inverters as $idx => $inv) {
            $invNum = $idx + 1;
            $invBrand = $inv['brand'] ?? '';
            $invModel = $inv['model'] ?? '';
            $inverterMap[$invNum] = $invBrand . ' ' . $invModel;
        }
        ?>

        <?php if ($hasTelemetryData): ?>
            <div class="pdf-page page-break-before" data-page="telemetry">
                <h2 class="section-title">
                    <i class="fas fa-satellite-dish"></i>
                    Telemetry & Communications
                </h2>

                <!-- Telemetry Credentials -->
                <?php if (!empty($credentials)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-key" style="color: var(--primary); margin-right: 8px;"></i>
                        Telemetry Credentials
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Inverter Ref.</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($credentials as $cred): ?>
                                <?php
                                $invRef = '';
                                $username = '';
                                $password = '';
                                $ip = '';
                                if (!empty($cred['characteristics'])) {
                                    if (preg_match('/Inverter Ref:\s*([^|]+)/i', $cred['characteristics'], $m)) {
                                        $invRefRaw = trim($m[1]);
                                        if (preg_match('/Inverter\s*(\d+)/i', $invRefRaw, $mm)) {
                                            $invIdx = intval($mm[1]);
                                            $invRef = isset($inverterMap[$invIdx]) ? $inverterMap[$invIdx] : $invRefRaw;
                                        } else {
                                            $invRef = $invRefRaw;
                                        }
                                    }
                                    if (preg_match('/Username:\s*([^|]+)/i', $cred['characteristics'], $m)) {
                                        $username = trim($m[1]);
                                    }
                                    if (preg_match('/Password:\s*([^|]+)/i', $cred['characteristics'], $m)) {
                                        $password = trim($m[1]);
                                    }
                                    if (preg_match('/IP:\s*([^|]+)/i', $cred['characteristics'], $m)) {
                                        $ip = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($invRef ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($username ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($password ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ip ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Communications Devices -->
                <?php if (!empty($communications)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-router" style="color: var(--primary); margin-right: 8px;"></i>
                        Communications Devices
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Model</th>
                                <th>ID/Serial</th>
                                <th>MAC</th>
                                <th>IP</th>
                                <th>SIM</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($communications as $comm): ?>
                                <?php
                                // Equipment is stored in 'brand' field, Model in 'model' field
                                $commEquipment = $comm['brand'] ?? '';
                                $model = $comm['model'] ?? '';
                                $idSerial = '';
                                $mac = '';
                                $ip = '';
                                $sim = '';
                                $location = '';
                                if (!empty($comm['characteristics'])) {
                                    if (preg_match('/ID\/Serial:\s*([^|]+)/i', $comm['characteristics'], $m)) {
                                        $idSerial = trim($m[1]);
                                    }
                                    if (preg_match('/MAC:\s*([^|]+)/i', $comm['characteristics'], $m)) {
                                        $mac = trim($m[1]);
                                    }
                                    if (preg_match('/IP:\s*([^|]+)/i', $comm['characteristics'], $m)) {
                                        $ip = trim($m[1]);
                                    }
                                    if (preg_match('/SIM Card:\s*([^|]+)/i', $comm['characteristics'], $m)) {
                                        $sim = trim($m[1]);
                                    }
                                    if (preg_match('/Location:\s*([^|]+)/i', $comm['characteristics'], $m)) {
                                        $location = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($commEquipment ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($model ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($idSerial ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mac ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ip ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($sim ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($location ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Smart Meters -->
                <?php if (!empty($smartMeters)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-microchip" style="color: var(--primary); margin-right: 8px;"></i>
                        Smart Meters
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Serial</th>
                                <th>CT Ratio</th>
                                <th>SIM</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($smartMeters as $sm): ?>
                                <?php
                                $mode = '';
                                $brand = $sm['brand'] ?? '';
                                $model = $sm['model'] ?? '';
                                $serial = '';
                                $ctRatio = '';
                                $sim = '';
                                $location = '';
                                if (!empty($sm['characteristics'])) {
                                    if (preg_match('/Mode:\s*([^|]+)/i', $sm['characteristics'], $m)) {
                                        $mode = trim($m[1]);
                                    }
                                    if (preg_match('/Serial:\s*([^|]+)/i', $sm['characteristics'], $m)) {
                                        $serial = trim($m[1]);
                                    }
                                    if (preg_match('/CT Ratio:\s*([^|]+)/i', $sm['characteristics'], $m)) {
                                        $ctRatio = trim($m[1]);
                                    }
                                    if (preg_match('/SIM:\s*([^|]+)/i', $sm['characteristics'], $m)) {
                                        $sim = trim($m[1]);
                                    }
                                    if (preg_match('/Location:\s*([^|]+)/i', $sm['characteristics'], $m)) {
                                        $location = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($mode ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($brand ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($model ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($serial ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ctRatio ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($sim ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($location ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Energy Meters -->
                <?php if (!empty($energyMeters)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-bolt" style="color: var(--primary); margin-right: 8px;"></i>
                        Energy Meters
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Scope</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>RS485 Address</th>
                                <th>CT Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($energyMeters as $em): ?>
                                <?php
                                // Scope is stored in deployment_status field
                                $scope = $em['deployment_status'] ?? '';
                                $brand = $em['brand'] ?? '';
                                $model = $em['model'] ?? '';
                                $rs485 = '';
                                $ctRatio = '';
                                if (!empty($em['characteristics'])) {
                                    if (preg_match('/RS485 Address:\s*([^|]+)/i', $em['characteristics'], $m)) {
                                        $rs485 = trim($m[1]);
                                    }
                                    if (preg_match('/CT Ratio:\s*([^|]+)/i', $em['characteristics'], $m)) {
                                        $ctRatio = trim($m[1]);
                                    }
                                }
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($scope ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($brand ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($model ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($rs485 ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ctRatio ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE 7: Punch List -->
        <?php
        $punchList = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'punch list item'
        );
        ?>

        <?php if (!empty($punchList)): ?>
            <div class="pdf-page page-break-before" data-page="punchlist">
                <h2 class="section-title">
                    <i class="fas fa-clipboard-list"></i>
                    Punch List
                </h2>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Severity</th>
                            <th>Description</th>
                            <th>Opening Date</th>
                            <th>Responsible</th>
                            <th>Resolution Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($punchList as $punch): ?>
                            <?php
                            $itemId = '';
                            $severity = '';
                            $description = '';
                            $openDate = '';
                            $responsible = '';
                            $resDate = '';

                            if (!empty($punch['characteristics'])) {
                                if (preg_match('/ID:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $itemId = trim($m[1]);
                                }
                                if (preg_match('/Severity:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $severity = trim($m[1]);
                                }
                                if (preg_match('/Description:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $description = trim($m[1]);
                                }
                                if (preg_match('/Opening Date:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $openDate = trim($m[1]);
                                }
                                if (preg_match('/Responsible:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $responsible = trim($m[1]);
                                }
                                if (preg_match('/Resolution Date:\s*([^|]+)/i', $punch['characteristics'], $m)) {
                                    $resDate = trim($m[1]);
                                }
                            }

                            // Severity badge color
                            $severityLower = strtolower($severity);
                            $severityColor = '#6c757d'; // default
                            if (in_array($severityLower, ['critical', 'high'])) {
                                $severityColor = '#dc3545'; // danger
                            } elseif ($severityLower === 'medium') {
                                $severityColor = '#ffc107'; // warning
                            } elseif (in_array($severityLower, ['low', 'minor'])) {
                                $severityColor = '#28a745'; // success
                            } elseif ($severityLower === 'info') {
                                $severityColor = '#17a2b8'; // info
                            }
                            ?>
                            <tr class="avoid-break">
                                <td><?php echo htmlspecialchars($itemId ?: '-'); ?></td>
                                <td>
                                    <?php if ($severity): ?>
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; background: <?php echo $severityColor; ?>; color: white; font-weight: 600; font-size: 11px;">
                                            <?php echo htmlspecialchars($severity); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($description ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($openDate ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($responsible ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($resDate ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- PAGE 8: Additional Notes -->
        <?php
        $notes = array_filter(
            $equipment,
            fn($eq) =>
            isset($eq['equipment_type']) &&
                strtolower(trim($eq['equipment_type'])) === 'additional notes'
        );
        ?>

        <?php if (!empty($notes)): ?>
            <div class="pdf-page page-break-before" data-page="notes">
                <h2 class="section-title">
                    <i class="fas fa-sticky-note"></i>
                    Additional Notes
                </h2>

                <?php foreach ($notes as $note): ?>
                    <?php
                    $notesText = '';
                    if (!empty($note['characteristics'])) {
                        if (preg_match('/Notes:\s*(.+)/is', $note['characteristics'], $m)) {
                            $notesText = trim($m[1]);
                        } else {
                            $notesText = $note['characteristics'];
                        }
                    }
                    ?>
                    <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <p style="margin: 0; line-height: 1.6; color: #495057; white-space: pre-wrap;">
                            <?php echo nl2br(htmlspecialchars($notesText ?: 'No notes available')); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        // PAGE 9: Finish - Photos & Evidence
        $finishChecklist = array_filter(
            $equipment,
            function ($eq) {
                if (!isset($eq['equipment_type'])) return false;
                $t = (string)$eq['equipment_type'];
                return preg_match('/finish\s*-\s*photo\s*checklist/i', $t);
            }
        );
        $finishLinks = array_filter(
            $equipment,
            function ($eq) {
                if (!isset($eq['equipment_type'])) return false;
                $t = (string)$eq['equipment_type'];
                return preg_match('/finish\s*-\s*photos?\s*link/i', $t);
            }
        );
        $hasFinish = !empty($finishChecklist) || !empty($finishLinks);
        ?>

        <?php if ($hasFinish): ?>
            <div class="pdf-page page-break-before" data-page="finish">
                <h2 class="section-title">
                    <i class="fas fa-camera"></i>
                    Finish: Photos & Evidence
                </h2>

                <?php if (!empty($finishLinks)): ?>
                    <?php
                    $linkUrl = '';
                    $first = reset($finishLinks);
                    if (!empty($first['characteristics'])) {
                        if (preg_match('/URL:\s*(.+)/i', $first['characteristics'], $mm)) {
                            $linkUrl = trim($mm[1]);
                        } else {
                            $linkUrl = trim($first['characteristics']);
                        }
                    }
                    ?>
                    <?php if ($linkUrl): ?>
                        <div class="equipment-item" style="border-left-color: #0d6efd; background: #e9f2ff;">
                            <div style="font-weight:600; margin-bottom:6px; color:#0d47a1;">Photos Repository</div>
                            <a href="<?php echo htmlspecialchars($linkUrl); ?>" target="_blank" style="color:#0d6efd; text-decoration:none; word-break: break-all;">
                                <?php echo htmlspecialchars($linkUrl); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($finishChecklist)): ?>
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--primary-dark); margin: 20px 0 15px 0;">
                        <i class="fas fa-list-check" style="color: var(--primary); margin-right: 8px;"></i>
                        Photo Checklist
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finishChecklist as $fc): ?>
                                <?php
                                $itemLabel = $fc['brand'] ?? '';
                                $status = $fc['deployment_status'] ?? '';
                                $note = '';
                                if (!empty($fc['characteristics'])) {
                                    if (preg_match('/Note:\s*(.+)/i', $fc['characteristics'], $m)) {
                                        $note = trim($m[1]);
                                    }
                                }
                                // Badge color for status
                                $statusLower = strtolower($status);
                                $badgeBg = '#6c757d';
                                if ($statusLower === 'completed') $badgeBg = '#28a745';
                                elseif ($statusLower === 'pending') $badgeBg = '#ffc107';
                                elseif ($statusLower === 'n/a' || $statusLower === 'na') $badgeBg = '#adb5bd';
                                ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($itemLabel ?: '-'); ?></td>
                                    <td>
                                        <?php if ($status): ?>
                                            <span style="display:inline-block; padding: 4px 10px; border-radius: 12px; background: <?php echo $badgeBg; ?>; color: white; font-weight: 600; font-size: 11px;">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($note ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PDF Export Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Turf.js/6.5.0/turf.min.js"></script>
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
            const project = '<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '_', removeAccents($report['project_name'] ?? 'Report')); ?>';
            const reportDate = '<?php echo date('Y-m-d', strtotime($report['date'] ?? 'now')); ?>';

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

                // Make links inside this page clickable by overlaying PDF link annotations
                try {
                    const pageEl = pages[i];
                    const rectPage = pageEl.getBoundingClientRect();
                    const ratioX = pageWidth / rectPage.width;
                    const ratioY = pageHeight / rectPage.height;
                    const anchors = pageEl.querySelectorAll('a[href]');
                    anchors.forEach(a => {
                        const href = a.getAttribute('href');
                        if (!href) return;
                        const r = a.getBoundingClientRect();
                        const x = (r.left - rectPage.left) * ratioX;
                        const y = (r.top - rectPage.top) * ratioY;
                        const w = r.width * ratioX;
                        const h = r.height * ratioY;
                        // jsPDF v2: link(x, y, w, h, { url })
                        try {
                            pdf.link(x, y, w, h, {
                                url: href
                            });
                        } catch (e) {
                            /* ignore */
                        }
                    });
                } catch (e) {
                    console.warn('PDF link overlay failed for page', i + 1, e);
                }
            }

            pdf.save(`COMR_${project}_${reportDate}.pdf`);
        });
    </script>

    <!-- Initialize Commissioning Map Preview -->
    <script>
        function initCommReportMap() {
            const mapEl = document.getElementById('report-map-comm');
            if (!mapEl) return;

            // Prevent multiple initializations
            if (window.REPORT_MAP_INITIALIZED) return;

            // parse JSON from hidden field; prefer REPORT_POLYGON_COORDS injected by server
            let coords = null;
            if (typeof window.REPORT_POLYGON_COORDS !== 'undefined' && window.REPORT_POLYGON_COORDS) {
                coords = window.REPORT_POLYGON_COORDS;
            } else {
                const coordsField = document.getElementById('comm_existing_polygon_coords');
                if (coordsField && coordsField.value) {
                    try {
                        coords = JSON.parse(coordsField.value);
                    } catch (e) {
                        coords = null;
                    }
                }
            }

            // read gps value (scope to report content)
            const gpsEl = document.querySelector('#report-content [data-gps]');
            const gpsVal = gpsEl ? gpsEl.getAttribute('data-gps') : null;
            let gpsCoords = null;
            if (gpsVal) {
                const parts = gpsVal.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
                if (parts.length >= 2) {
                    const la = parseFloat(parts[0]);
                    const lo = parseFloat(parts[1]);
                    if (!isNaN(la) && !isNaN(lo)) gpsCoords = [la, lo];
                }
            }

            if (!coords && !gpsCoords) return;

            try {
                console.log('[REPORT MAP] coords', coords, 'gpsVal:', gpsVal);
                const center = coords && coords.length ? coords[0] : gpsCoords || [39.5, -8.0];
                const map = L.map('report-map-comm', {
                    zoomControl: false,
                    attributionControl: false
                }).setView(center, 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(map);
                if (coords && coords.length) {
                    const polygon = L.polygon(coords, {
                        color: '#007bff',
                        fillOpacity: 0.2
                    }).addTo(map);
                    map.fitBounds(polygon.getBounds(), {
                        padding: [20, 20]
                    });
                }
                if (gpsCoords) {
                    L.marker(gpsCoords, {
                        icon: L.divIcon({
                            className: 'gps-marker',
                            html: '<i class="fas fa-map-marker-alt" style="color: #dc3545; font-size: 24px;"></i>',
                            iconSize: [24, 24],
                            iconAnchor: [12, 24]
                        })
                    }).addTo(map);
                }
                window.REPORT_MAP_INITIALIZED = true;
                console.log('[REPORT MAP] initialized successfully');
                try {
                    map.invalidateSize();
                } catch (e) {}
                setTimeout(function() {
                    try {
                        map.invalidateSize();
                    } catch (e) {}
                }, 300);
                setTimeout(function() {
                    try {
                        map.invalidateSize();
                    } catch (e) {}
                }, 1000);
            } catch (e) {
                console.error('Error initializing commissioning report map:', e);
            }
        }
        document.addEventListener('DOMContentLoaded', initCommReportMap);
        window.addEventListener('load', initCommReportMap);
        // Also try a timeout in case map is within a print element or dynamically shown
        setTimeout(initCommReportMap, 500);
        setTimeout(initCommReportMap, 1500);
    </script>
    </script>
</body>

</html>