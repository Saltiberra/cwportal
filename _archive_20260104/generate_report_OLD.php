<?php

/**
 * Report Generation Script
 *
 * This file generates a professional commissioning report
 * based on the data collected in the commissioning form
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

function number_format_exact_old($num, $decimals = 2)
{
    if ($num === null || $num === '') return '-';
    $s = number_format((float)str_replace(',', '.', $num), $decimals, '.', '');
    $s = rtrim($s, '0');
    $s = rtrim($s, '.');
    return $s;
}

$reportId = intval($_GET['id']);
$userId = $user['id'];

// Get report data
try {
    // 🔒 SECURITY: Verify report exists and user is authenticated (session_id check removed for better UX)
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
        error_log("[SECURITY] ❌ Report {$reportId} not found or deleted");
        $_SESSION['error'] = 'Report not found.';
        // Temporarily disabled redirect for debugging
        // header('Location: index.php');
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

    // 🔥 Get MPPT measurements data
    $loadedData = loadReportData($reportId, $pdo);
    $mpptMeasurements = $loadedData['string_measurements'] ?? [];

    // Auto-populate Inv column when there's only 1 inverter
    $stmtInvCount = $pdo->prepare("SELECT COUNT(*) as inv_count FROM report_equipment WHERE report_id = ? AND equipment_type = 'Inverter'");
    $stmtInvCount->execute([$reportId]);
    $invCountResult = $stmtInvCount->fetch(PDO::FETCH_ASSOC);
    $inverterCount = (int)($invCountResult['inv_count'] ?? 0);

    // If there's only 1 inverter and measurements have no inverter_index, set it to 1
    if ($inverterCount === 1 && !empty($mpptMeasurements)) {
        foreach ($mpptMeasurements as &$measurement) {
            if (empty($measurement['inverter_index'])) {
                $measurement['inverter_index'] = '1';
            }
        }
        unset($measurement);
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error retrieving report: ' . $e->getMessage();
    header('Location: reports.php');
    exit;
}

// Helper: format characteristics string with pipes
function formatCharacteristics($characteristics)
{
    if (empty($characteristics)) return '-';
    $result = '';
    foreach (explode('|', $characteristics) as $part) {
        $result .= trim($part) . '<br>';
    }
    return $result;
}

// 🔥 Render MPPT Measurements Table
function renderMPPTTable($measurements)
{
    if (empty($measurements)) {
        return '<div class="text-center text-muted p-4"><i class="fas fa-exclamation-circle me-2"></i>No MPPT measurements recorded for this report.</div>';
    }

    $html = '<table style="width:100%; border-collapse: collapse; font-size: 11px;">';
    $html .= '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #333;">';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Inv</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">MPPT</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Str</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Voc (V)</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Rins (Ω)</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Irr (W/m²)</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">T (°C)</th>';
    $html .= '<th style="border: 1px solid #ddd; padding: 6px;">Current (A)</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($measurements as $m) {
        $html .= '<tr style="border-bottom: 1px solid #ddd;">';
        $invDisplay = is_numeric($m['inverter_index'] ?? null) ? (intval($m['inverter_index']) + 1) : '-';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: center;">' . htmlspecialchars($invDisplay) . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: center;">' . htmlspecialchars($m['mppt'] ?? '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: center;">' . htmlspecialchars($m['string_num'] ?? '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: right;">' . (($m['voc'] !== null && $m['voc'] !== '') ? htmlspecialchars((string)$m['voc']) : '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: right;">' . (($m['rins'] !== null && $m['rins'] !== '') ? htmlspecialchars((string)$m['rins']) : '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: right;">' . (($m['irr'] !== null && $m['irr'] !== '') ? htmlspecialchars((string)$m['irr']) : '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: right;">' . (($m['temp'] !== null && $m['temp'] !== '') ? htmlspecialchars((string)$m['temp']) : '-') . '</td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: right;">' . (($m['current'] !== null && $m['current'] !== '') ? htmlspecialchars((string)$m['current']) : '-') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

// Render a full inverter card with parsed characteristics (brand, model, qty, status,
// max output current, serial, location, circuit breaker, differential, cable, datasheet)
function renderInverterCard($inverter, $index)
{
    $brand = trim((string)($inverter['brand'] ?? ''));
    $model = trim((string)($inverter['model'] ?? ''));
    $qty = isset($inverter['quantity']) ? (int)$inverter['quantity'] : 1;
    $statusRaw = (string)($inverter['deployment_status'] ?? '');
    $status = $statusRaw !== '' ? ucfirst(strtolower($statusRaw)) : '';
    $chars = (string)($inverter['characteristics'] ?? '');
    $locationCol = (string)($inverter['location'] ?? '');

    // Parse key/value pairs from characteristics
    $serial = '';
    $location = $locationCol; // prefer dedicated column
    $maxOut = '';
    $cb = '';
    $cbRated = '';
    $diff = '';
    $diffRated = '';
    $diffMA = '';
    $cable = '';
    $datasheet = '';

    if ($chars !== '') {
        $parts = array_map('trim', explode('|', $chars));
        foreach ($parts as $p) {
            if (stripos($p, 'Serial:') === 0) $serial = trim(substr($p, strlen('Serial:')));
            elseif (stripos($p, 'Location:') === 0 && $location === '') $location = trim(substr($p, strlen('Location:')));
            elseif (stripos($p, 'Max Output Current:') === 0) $maxOut = trim(substr($p, strlen('Max Output Current:')));
            elseif (stripos($p, 'Circuit Breaker:') === 0) {
                $cb = trim(substr($p, strlen('Circuit Breaker:')));
                if (preg_match('/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A/i', $cb, $m)) {
                    $cbRated = str_replace(',', '.', $m[1]);
                }
            } elseif (stripos($p, 'Differential:') === 0) {
                $diff = trim(substr($p, strlen('Differential:')));
                if (preg_match('/Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A/i', $diff, $m)) {
                    $diffRated = str_replace(',', '.', $m[1]);
                }
                if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)\s*mA/i', $diff, $m2)) {
                    $diffMA = str_replace(',', '.', $m2[1]);
                }
            } elseif (stripos($p, 'Cable:') === 0) $cable = trim(substr($p, strlen('Cable:')));
            elseif (stripos($p, 'Datasheet:') === 0) $datasheet = trim(substr($p, strlen('Datasheet:')));
        }
    }

    // Normalize display values
    $brandDisp = htmlspecialchars($brand !== '' ? $brand : '-');
    $modelDisp = htmlspecialchars($model !== '' ? $model : '-');
    $qtyDisp = $qty > 0 ? $qty : 1;
    $statusDisp = htmlspecialchars($status !== '' ? $status : '-');
    $serialDisp = htmlspecialchars($serial !== '' ? $serial : '-');
    $locationDisp = htmlspecialchars($location !== '' ? $location : '-');
    $maxOutDisp = htmlspecialchars($maxOut !== '' ? $maxOut : '-');
    // Build friendly Circuit Breaker string: "Brand Model (123A)"
    $cbBase = $cb;
    if ($cbBase !== '') {
        // remove "Rated: ...A" token from base
        $cbBase = trim(preg_replace('/Rated\s*:\s*[0-9]+(?:[\.,][0-9]+)?\s*A/i', '', $cbBase));
    }
    $cbText = $cbBase;
    if ($cbRated !== '') {
        $cbText .= ($cbText !== '' ? ' ' : '') . '(' . $cbRated . 'A)';
    }
    $cbDisp = htmlspecialchars($cbText !== '' ? $cbText : ($cb !== '' ? $cb : '-'));

    // Build friendly Differential string: "Brand Model (123A, 30mA)"
    $diffBase = $diff;
    if ($diffBase !== '') {
        $diffBase = trim(preg_replace('/Rated\s*:\s*[0-9]+(?:[\.,][0-9]+)?\s*A/i', '', $diffBase));
        $diffBase = trim(preg_replace('/[0-9]+(?:[\.,][0-9]+)?\s*mA/i', '', $diffBase));
    }
    $diffTextParts = [];
    if ($diffRated !== '') $diffTextParts[] = $diffRated . 'A';
    if ($diffMA !== '') $diffTextParts[] = $diffMA . 'mA';
    $diffText = $diffBase;
    if (!empty($diffTextParts)) {
        $diffText .= ($diffText !== '' ? ' ' : '') . '(' . implode(', ', $diffTextParts) . ')';
    }
    $diffDisp = htmlspecialchars($diffText !== '' ? $diffText : ($diff !== '' ? $diff : '-'));
    $cableDisp = htmlspecialchars($cable !== '' ? $cable : '-');
    $title = trim(($brand !== '' ? $brand : '') . ' ' . ($model !== '' ? $model : ''));
    if ($title === '') $title = 'Inverter';

    // Build HTML
    $html = '<div class="equipment-card inverter-card">';
    $html .= '<div class="equipment-header" style="position: relative;">';
    $html .= '<div class="equipment-number">' . ($index + 1) . '</div>';
    $html .= '<h6 class="equipment-title" style="margin-right:100px;">' . htmlspecialchars($title) . '</h6>';
    // Status badge similar to form card
    $badgeColor = 'secondary';
    $statusKey = strtolower($statusRaw);
    if ($statusKey === 'new') $badgeColor = 'success';
    elseif ($statusKey === 'existing') $badgeColor = 'secondary';
    elseif ($statusKey === 'replacement') $badgeColor = 'warning';
    if ($status !== '') {
        $html .= '<span class="badge bg-' . $badgeColor . '" style="position:absolute; right:12px; top:10px;">' . htmlspecialchars($status) . '</span>';
    }
    $html .= '</div>';

    // Basic brand/model row
    $html .= '<div class="inverter-basic-info">';
    $html .= '<div class="row g-2">';
    $html .= '<div class="col-6"><small class="text-muted d-block">Brand</small><strong>' . $brandDisp . '</strong></div>';
    $html .= '<div class="col-6"><small class="text-muted d-block">Model</small><strong>' . $modelDisp . '</strong></div>';
    $html .= '</div>';
    $html .= '</div>';

    // Specs arranged like the form card with icons, two-column grid
    $iconItem = function ($icon, $label, $value) {
        $val = $value !== '' ? $value : '-';
        return '<div class="d-flex align-items-start mb-1">'
            . '<i class="fas ' . $icon . ' me-2" style="color:#254A5D;"></i>'
            . '<div><div class="fw-bold" style="font-size:0.9rem;">' . $label . ':</div>'
            . '<div>' . $val . '</div></div></div>';
    };

    $html .= '<div class="row g-3 px-2 pb-2">';
    // Left column
    $html .= '<div class="col-md-6">';
    $html .= $iconItem('fa-hashtag', 'Quantity', $qtyDisp);
    $html .= $iconItem('fa-circle', 'Status', $statusDisp);
    $html .= $iconItem('fa-bolt', 'Max Output Current', $maxOutDisp);
    $html .= '</div>';
    // Right column
    $html .= '<div class="col-md-6">';
    $html .= $iconItem('fa-barcode', 'Serial Number', $serialDisp);
    $html .= $iconItem('fa-location-dot', 'Location', $locationDisp);
    $html .= '</div>';
    $html .= '</div>';

    // Secondary row for protective devices and cable
    $html .= '<div class="row g-3 px-2 pb-2">';
    $html .= '<div class="col-md-6">' . $iconItem('fa-shield-alt', 'Circuit Breaker', $cbDisp) . '</div>';
    $html .= '<div class="col-md-6">' . $iconItem('fa-plug', 'Differential', $diffDisp) . '</div>';
    $html .= '<div class="col-12">' . $iconItem('fa-network-wired', 'Cable', $cableDisp) . '</div>';

    // Add Differential Current if available
    if ($diffMA !== '') {
        $html .= '<div class="col-md-6">' . $iconItem('fa-flash', 'Differential Current', $diffMA . ' mA') . '</div>';
    }

    if ($datasheet !== '') {
        $dsSafe = htmlspecialchars($datasheet);
        $html .= '<div class="col-12">' . $iconItem('fa-file-lines', 'Datasheet', '<a href="' . $dsSafe . '" target="_blank">' . $dsSafe . '</a>') . '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

// Generate formatted date
$formattedDate = date('F j, Y', strtotime($report['date']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissioning Report - <?php echo htmlspecialchars($report['project_name']); ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        /* Modern Professional Design - Cleanwatts Colors */
        :root {
            --primary-gradient: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%);
            --secondary-gradient: linear-gradient(135deg, #186C7F 0%, #26989D 100%);
            --success-gradient: linear-gradient(135deg, #26989D 0%, #1BA5B7 100%);
            --info-gradient: linear-gradient(135deg, #1BA5B7 0%, #89D1D7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #101D20 0%, #254A5D 100%);

            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 12px 40px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 20px 60px rgba(0, 0, 0, 0.2);

            --text-primary: #101D20;
            --text-secondary: #6B797D;
            --text-muted: #ADBDC4;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #ABDBDE 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.55;
            font-size: 14px;
        }

        /* Header Section */
        .report-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 1.5rem;
            margin: 0 auto 2rem;
            /* center and keep bottom margin */
            max-width: 1300px !important;
            /* Container width set to 1300px as requested */
            width: calc(100% - 2rem);
            border-radius: 20px;
            box-shadow: var(--shadow-heavy);
            position: relative;
            overflow: hidden;
        }

        /* Ensure all main containers have consistent width */
        .report-header,
        .section-card {
            max-width: 1300px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            width: calc(100% - 2rem) !important;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            50% {
                transform: translate(-50%, -50%) rotate(180deg);
            }
        }

        .report-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .report-subtitle {
            font-size: 1.1rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .report-meta {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .report-meta-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .report-meta-item i {
            margin-right: 0.75rem;
            width: 20px;
            opacity: 0.8;
        }

        /* Section Cards */
        .section-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-medium);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            margin-right: 0.75rem;
            font-size: 1.25rem;
            color: white;
            background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%);
            box-shadow: 0 4px 15px rgba(44, 204, 211, 0.3);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        /* Data Display */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .data-item {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .data-item:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .data-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .data-label::before {
            content: '';
            width: 3px;
            height: 12px;
            background: var(--primary-gradient);
            margin-right: 0.5rem;
            border-radius: 2px;
        }

        .data-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-primary);
            margin: 0;
        }

        .data-value strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Equipment Cards */
        .equipment-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 15px;
            padding: 1.1rem;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .equipment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .equipment-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .equipment-number {
            background: var(--primary-gradient);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            font-size: 0.875rem;
        }

        .equipment-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        /* Statistics Cards */
        .stats-card {
            background: var(--success-gradient);
            color: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            box-shadow: var(--shadow-medium);
        }

        .stats-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            font-size: 0.875rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Action Buttons */
        .action-buttons {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-light);
        }

        .btn-modern {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            margin-right: 0.5rem;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-secondary-modern {
            background: var(--secondary-gradient);
            color: white;
        }

        .btn-outline-modern {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--text-primary);
        }

        .btn-outline-modern:hover {
            background: var(--text-primary);
            color: white;
        }

        /* Snap-to-page sections: each .pdf-snap aims to be a full page */
        #report-content .pdf-snap {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Insert a page break before every snap after the first when exporting */
        #report-content.pdf-export .pdf-snap+.pdf-snap {
            break-before: page;
            page-break-before: always;
        }

        /* Inline key-value chips for compact horizontal layout */
        .kv-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .kv-item {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 10px;
            padding: 0.45rem 0.6rem;
            font-size: 0.93rem;
            display: inline-flex;
            align-items: baseline;
            gap: 0.4rem;
        }

        .kv-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-secondary);
        }

        .kv-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Page break utilities (screen for html2pdf + print) */
        #report-content.pdf-export .page-break {
            break-before: page;
            page-break-before: always;
        }

        #report-content.pdf-export .no-break-inside,
        #report-content.pdf-export table,
        #report-content.pdf-export thead,
        #report-content.pdf-export tbody,
        #report-content.pdf-export tr,
        #report-content.pdf-export td,
        #report-content.pdf-export th {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* PDF-export compact mode: applied only while generating PDF */
        #report-content.pdf-export .report-header {
            padding: 1.25rem 1rem;
            margin-bottom: 1rem;
            border-radius: 14px;
        }

        #report-content.pdf-export .report-title {
            font-size: 1.6rem;
            margin-bottom: 0.25rem;
        }

        #report-content.pdf-export .report-subtitle {
            font-size: 1rem;
        }

        #report-content.pdf-export .report-meta {
            padding: 1rem;
            border-radius: 10px;
        }

        #report-content.pdf-export .section-card {
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 14px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        #report-content.pdf-export .section-header {
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
        }

        #report-content.pdf-export .section-icon {
            width: 42px;
            height: 42px;
            font-size: 1.1rem;
        }

        #report-content.pdf-export .section-title {
            font-size: 1.1rem;
        }

        #report-content.pdf-export .data-grid {
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        #report-content.pdf-export .data-item {
            padding: 0.75rem;
        }

        #report-content.pdf-export .data-label {
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
        }

        #report-content.pdf-export .data-value {
            font-size: 0.95rem;
        }

        #report-content.pdf-export .equipment-card {
            padding: 0.85rem;
            margin-bottom: 0.6rem;
            border-radius: 12px;
        }

        #report-content.pdf-export .equipment-header {
            margin-bottom: 0.6rem;
        }

        #report-content.pdf-export .equipment-number {
            width: 26px;
            height: 26px;
            font-size: 0.8rem;
            margin-right: 0.6rem;
        }

        #report-content.pdf-export .equipment-title {
            font-size: 1rem;
        }

        #report-content.pdf-export .stats-card {
            padding: 0.75rem;
        }

        #report-content.pdf-export .stats-number {
            font-size: 1.35rem;
        }

        #report-content.pdf-export .kv-item {
            padding: 0.35rem 0.5rem;
            font-size: 0.88rem;
        }

        #report-content.pdf-export table th,
        #report-content.pdf-export table td {
            padding: 0.45rem 0.55rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .report-header {
                padding: 2rem 1rem;
            }

            .report-title {
                font-size: 2rem;
            }

            .section-card {
                padding: 1.5rem;
            }

            .data-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Print Styles - Modern Professional Design */
        @media print {
            @page {
                margin: 0.75in 0.5in;
                size: A4;
            }

            body {
                background: white !important;
                color: #000 !important;
                font-family: 'Times New Roman', serif !important;
                font-size: 11px !important;
                line-height: 1.4 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .container {
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Hide screen-only elements */
            .action-buttons,
            .no-print {
                display: none !important;
            }

            /* Professional Header */
            .report-header {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                padding: 20px !important;
                margin-bottom: 20px !important;
                border-radius: 0 !important;
                position: relative !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-after: avoid;
            }

            .report-header::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: #fff;
                opacity: 0.3;
            }

            .report-title {
                font-size: 18px !important;
                font-weight: 700 !important;
                margin-bottom: 8px !important;
                text-shadow: none !important;
                color: white !important;
                text-align: center !important;
            }

            .report-subtitle {
                font-size: 14px !important;
                font-weight: 400 !important;
                color: white !important;
                text-align: center !important;
                opacity: 0.9 !important;
            }

            .report-meta {
                background: rgba(255, 255, 255, 0.1) !important;
                border-radius: 0 !important;
                padding: 12px !important;
                margin-top: 15px !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                display: table !important;
                width: 100% !important;
            }

            .report-meta-item {
                display: table-cell !important;
                text-align: center !important;
                padding: 0 10px !important;
                border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
            }

            .report-meta-item:last-child {
                border-right: none !important;
            }

            .report-meta-item i {
                margin-right: 5px !important;
                width: 12px !important;
                font-size: 10px !important;
            }

            .report-meta-item strong {
                font-size: 10px !important;
                color: white !important;
                display: block !important;
                margin-bottom: 2px !important;
            }

            .report-meta-item span {
                font-size: 10px !important;
                color: white !important;
            }

            /* Section Cards - Professional Layout */
            .section-card {
                background: white !important;
                border: 1px solid #ddd !important;
                border-radius: 0 !important;
                padding: 15px !important;
                margin-bottom: 15px !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
                position: relative !important;
            }

            .section-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, #2CCCD3 0%, #254A5D 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .section-header {
                margin-bottom: 12px !important;
                padding-bottom: 8px !important;
                border-bottom: 1px solid #2CCCD3 !important;
                display: flex !important;
                align-items: center !important;
            }

            .section-icon {
                width: 24px !important;
                height: 24px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin-right: 10px !important;
                font-size: 10px !important;
                color: white !important;
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .section-title {
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #000 !important;
                margin: 0 !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
            }

            /* Data Grid - Card Layout for Print */
            .data-grid {
                display: flex !important;
                flex-wrap: wrap !important;
                width: 100% !important;
                margin-bottom: 10px !important;
                gap: 12px !important;
            }

            .data-item {
                display: block !important;
                width: calc(33.333% - 8px) !important;
                /* 3 cards per row */
                background: #fff !important;
                border: 1px solid #dee2e6 !important;
                padding: 10px !important;
                border-radius: 8px !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Remove alternating background used for table layout */
            .data-item:nth-child(4n+1) {
                background: #fff !important;
            }

            .data-label {
                font-size: 8px !important;
                font-weight: 700 !important;
                color: #495057 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                margin-bottom: 6px !important;
                display: block !important;
                position: relative !important;
                padding-left: 10px !important;
            }

            .data-label::before {
                content: '' !important;
                position: absolute !important;
                left: 0 !important;
                top: 2px !important;
                bottom: 2px !important;
                width: 3px !important;
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-radius: 2px !important;
            }

            .data-value {
                font-size: 10px !important;
                font-weight: 500 !important;
                color: #000 !important;
                margin: 0 !important;
                line-height: 1.3 !important;
            }

            .data-value strong {
                color: #000 !important;
                font-weight: 700 !important;
            }

            /* Equipment Cards - Professional Table Layout */
            .equipment-card {
                background: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 0 !important;
                padding: 10px !important;
                margin-bottom: 8px !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .equipment-header {
                margin-bottom: 8px !important;
                display: flex !important;
                align-items: center !important;
            }

            .equipment-number {
                width: 20px !important;
                height: 20px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-weight: 600 !important;
                font-size: 8px !important;
                margin-right: 8px !important;
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .equipment-title {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #000 !important;
                margin: 0 !important;
            }

            /* Statistics Cards - Professional Layout */
            .stats-card {
                background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
                color: #000 !important;
                border-radius: 0 !important;
                padding: 12px !important;
                text-align: center !important;
                border: 1px solid #2CCCD3 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .stats-number {
                font-size: 16px !important;
                font-weight: 700 !important;
                margin-bottom: 4px !important;
                color: #000 !important;
            }

            .stats-label {
                font-size: 8px !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                color: #495057 !important;
                font-weight: 600 !important;
            }

            /* Badge Styling */
            .badge {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                font-size: 8px !important;
                padding: 2px 6px !important;
                border-radius: 3px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Table Styling */
            table {
                font-size: 9px !important;
                border-collapse: collapse !important;
                width: 100% !important;
                margin-bottom: 10px !important;
            }

            th,
            td {
                padding: 6px !important;
                border: 1px solid #dee2e6 !important;
                text-align: left !important;
            }

            th {
                background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%) !important;
                color: white !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                font-size: 8px !important;
                letter-spacing: 0.5px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Responsive adjustments for print */
            .row {
                display: table !important;
                width: 100% !important;
            }

            .col-md-4,
            .col-md-6,
            .col-lg-8,
            .col-lg-4 {
                display: table-cell !important;
                vertical-align: top !important;
                padding: 0 5px !important;
            }

            .col-md-4 {
                width: 33.33% !important;
            }

            .col-md-6 {
                width: 50% !important;
            }

            .col-lg-8 {
                width: 66.67% !important;
            }

            .col-lg-4 {
                width: 33.33% !important;
            }

            /* Icon styling */
            .fas {
                font-size: 10px !important;
            }

            /* Remove all hover effects and animations */
            .equipment-card:hover,
            .data-item:hover,
            .section-card:hover,
            .btn-modern:hover,
            * {
                transform: none !important;
                box-shadow: none !important;
                animation: none !important;
                transition: none !important;
            }

            /* Page break utilities for print */
            .page-break {
                break-before: page !important;
                page-break-before: always !important;
            }

            .pdf-snap {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .pdf-snap+.pdf-snap {
                break-before: page !important;
                page-break-before: always !important;
            }

            .no-break-inside,
            table,
            thead,
            tbody,
            tr,
            td,
            th {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            /* Hide decorative elements */
            .section-card::before,
            .report-header::before {
                display: none !important;
            }

            /* Page breaks */
            .section-card {
                page-break-inside: avoid;
            }

            .equipment-card {
                page-break-inside: avoid;
            }

            /* Ensure PV Modules section is visible in print - Compatible selectors */
            .section-card .section-title {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Target PV Modules section specifically */
            .section-card:has(.section-title),
            .section-card:has(h3:contains("PV Modules")),
            .section-card:has(h4:contains("PV Modules")),
            .section-card:has(h2:contains("PV Modules")),
            .section-card:has(h1:contains("PV Modules")) {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Alternative approach - target by content */
            .section-card {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Make sure PV Modules content is visible */
            .section-card .equipment-card {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Ensure stats cards in PV section are visible */
            .section-card .stats-card {
                display: block !important;
                visibility: visible !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Note: Do not override data-grid to table in print; keep card layout for readability */

            /* Professional spacing */
            h5 {
                font-size: 12px !important;
                font-weight: 600 !important;
                color: #000 !important;
                margin-bottom: 8px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }

            h6 {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #2CCCD3 !important;
                margin-bottom: 6px !important;
            }

            /* Empty state styling */
            .text-center {
                text-align: center !important;
            }

            .text-center .fas {
                font-size: 24px !important;
                color: #adb5bd !important;
                margin-bottom: 8px !important;
            }

            .text-center p {
                font-size: 10px !important;
                color: #6c757d !important;
                margin: 0 !important;
            }

            /* Small text styling */
            .text-muted {
                color: #6c757d !important;
                font-size: 9px !important;
            }

            small {
                font-size: 9px !important;
            }

            /* Power distribution styling */
            .d-flex {
                display: flex !important;
            }

            .justify-content-between {
                justify-content: space-between !important;
            }

            .align-items-center {
                align-items: center !important;
            }

            .p-2 {
                padding: 6px !important;
            }

            .rounded {
                border-radius: 3px !important;
            }

            .fw-bold {
                font-weight: 700 !important;
            }

            /* Inverter Card Print Styles */
            .inverter-card {
                border-left: 3px solid #2CCCD3 !important;
                background: white !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .inverter-basic-info {
                background: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 0 !important;
                padding: 8px !important;
                margin-bottom: 8px !important;
            }

            .protection-section {
                background: #fff3cd !important;
                border: 1px solid #ffeaa7 !important;
                border-radius: 0 !important;
                padding: 8px !important;
                margin-bottom: 8px !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .protection-title {
                color: #2CCCD3 !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                margin-bottom: 6px !important;
                border-bottom: 1px solid #2CCCD3 !important;
                padding-bottom: 4px !important;
            }

            .protection-item {
                transition: all 0.3s ease;
            }

            .protection-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
            }

            .validation-alert {
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% {
                    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
                }

                70% {
                    box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
                }

                100% {
                    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
                }
            }

            /* Compact styles for String Measurement tables */
            .strings-table-compact {
                border-collapse: collapse;
                font-size: 0.82rem;
            }

            .strings-table-compact th,
            .strings-table-compact td {
                padding: 0.35rem 0.5rem;
                vertical-align: middle;
                border: 1px solid rgba(0, 0, 0, 0.06);
            }

            .strings-table-compact thead th {
                background: rgba(44, 204, 211, 0.06);
                font-weight: 600;
                color: #254A5D;
            }

            /* Compact adjustment for cell padding in print */
            .strings-table-compact th,
            .strings-table-compact td {
                padding: 0.25rem 0.4rem;
            }
        }

        /* end @media print */
    </style>
</head>

<body>
    <!-- Action Bar (screen only) -->
    <div class="no-print" style="background: #f8f9fa; padding: 1rem 0; margin-bottom: 2rem; border-bottom: 2px solid #dee2e6;">
        <div class="container" style="max-width: 1300px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                    <a href="comissionamento.php?report_id=<?php echo $reportId; ?>" class="btn btn-success">
                        <i class="fas fa-edit me-2"></i>Edit Report
                    </a>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button id="downloadPdfBtn" class="btn btn-secondary ms-2">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Begin PDF content wrapper (excludes Action Bar above) -->
    <div id="report-content">
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="report-title">
                        <i class="fas fa-solar-panel me-3" style="color: #2CCCD3;"></i>
                        <?php echo htmlspecialchars($report['project_name'] ?? 'Commissioning Report'); ?>
                    </h1>
                    <h2 class="report-subtitle"><?php echo htmlspecialchars($report['project_name'] ?? ''); ?></h2>
                </div>
                <div class="col-lg-4">
                    <div class="report-meta">
                        <div class="report-meta-item">
                            <i class="fas fa-calendar-alt" style="color: #2CCCD3;"></i>
                            <div>
                                <strong>Report Date</strong><br>
                                <span><?php echo htmlspecialchars($formattedDate ?? date('F j, Y')); ?></span>
                            </div>
                        </div>




                        <div class="report-meta-item">
                            <i class="fas fa-hashtag" style="color: #2CCCD3;"></i>
                            <div>
                                <strong>Report ID</strong><br>
                                <span>COM-<?php echo str_pad($report['id'] ?? 0, 5, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>


                    </div>
                </div>
                <!-- Inverters Section removed from header and moved to Equipments container -->
            </div>
        </div>

        <!-- General Information Section (from form 'General' tab) -->
        <div class="section-card pdf-snap">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3 class="section-title m-0">General Information</h3>
            </div>

            <div class="data-grid">
                <div class="data-item">
                    <div class="data-label">Project Name</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['project_name'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Date</div>
                    <p class="data-value"><?php echo htmlspecialchars($formattedDate ?? date('F j, Y')); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Plant Location</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['plant_location'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">GPS Coordinates</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['gps'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">CPE</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['cpe'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Installed Power (kWp)</div>
                    <p class="data-value"><strong><?php echo isset($report['installed_power']) && $report['installed_power'] !== null ? number_format($report['installed_power'], 2) . ' kWp' : '-'; ?></strong></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Total Power (kWp)</div>
                    <p class="data-value"><strong><?php echo isset($report['total_power']) && $report['total_power'] !== null ? number_format($report['total_power'], 2) . ' kWp' : '-'; ?></strong></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Certified Power (kWp)</div>
                    <p class="data-value"><strong><?php echo isset($report['certified_power']) && $report['certified_power'] !== null ? number_format($report['certified_power'], 2) . ' kWp' : '-'; ?></strong></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Commissioning Responsible</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['commissioning_responsible_name'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">EPC Company</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['epc_name'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Representative</div>
                    <p class="data-value">
                        <?php if (!empty($report['representative_name'])): ?>
                            <strong><?php echo htmlspecialchars($report['representative_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php if (!empty($report['representative_phone'])): ?>
                                    <i class="fas fa-phone me-1" style="color: #2CCCD3;"></i><?php echo htmlspecialchars($report['representative_phone']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($report['representative_email'])): ?>
                                    <i class="fas fa-envelope me-1" style="color: #2CCCD3;"></i><?php echo htmlspecialchars($report['representative_email']); ?>
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </p>
                </div>

                <div class="data-item">
                    <div class="data-label">Responsible Person</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['responsible'] ?? '-'); ?></p>
                </div>

                <div class="data-item">
                    <div class="data-label">Technician</div>
                    <p class="data-value"><?php echo htmlspecialchars($report['technician'] ?? '-'); ?></p>
                </div>
            </div>
        </div>


        <!-- Equipments Section -->
        <div class="section-card equipment-section">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-tools"></i>
                </div>
                <h3 class="section-title m-0 page-break">Equipments</h3>
            </div>

            <div class="p-3">
                <div class="pdf-snap">
                    <h4 class="mb-3">PV Modules</h4>

                    <div class="pv-modules-list">
                        <?php
                        $pvModules = array_filter($equipment, function ($eq) {
                            if (!isset($eq['equipment_type'])) return false;
                            // Normalize: remove non-alphanum and lowercase so we match 'PV Module', 'pv_module', 'pv-module', etc.
                            $type = preg_replace('/[^a-z0-9]/', '', strtolower($eq['equipment_type']));
                            return $type === 'pvmodule' || $type === 'pvmodules' || strpos($type, 'pv') !== false && strpos($type, 'module') !== false;
                        });

                        if (empty($pvModules)) {
                            echo '<div class="text-center text-muted p-4"><i class="fas fa-exclamation-circle me-2"></i>No PV modules recorded for this report.</div>';
                        } else {
                            // Build stats: per-entry power (kW) and aggregate total
                            $aggregateKW = 0.0;
                            $perEntry = [];
                            foreach ($pvModules as $idx => $mod) {
                                $qty = isset($mod['quantity']) && $mod['quantity'] !== '' ? intval($mod['quantity']) : 1;
                                $powerVal = $mod['power_rating'] ?? $mod['power'] ?? 0;
                                $powerNum = is_numeric($powerVal) ? floatval($powerVal) : 0;
                                // Treat stored value as W and convert to kW
                                $powerKW = $powerNum / 1000.0;
                                $totalKW = $powerKW * $qty;
                                $aggregateKW += $totalKW;
                                $perEntry[] = [
                                    'qty' => $qty,
                                    'brand' => $mod['brand'] ?? '',
                                    'model' => $mod['model'] ?? '',
                                    'power_kw' => $powerKW,
                                    'total_kw' => $totalKW
                                ];
                            }

                            // Summary stats
                            echo '<div class="mb-3">';
                            echo '<div class="stats-card p-3 d-flex justify-content-between align-items-center">';
                            echo '<div><strong>Module groups:</strong> ' . count($perEntry) . '</div>';
                            echo '<div><strong>Aggregate PV power:</strong> ' . number_format($aggregateKW, 3) . ' kW</div>';
                            echo '</div>';
                            echo '</div>';

                            // Per-entry cards (compact inline key-values)
                            echo '<div class="row g-3">';
                            foreach ($perEntry as $entry) {
                                echo '<div class="col-md-6">';
                                echo '<div class="equipment-card no-break-inside">';
                                echo '<div class="equipment-header">';
                                echo '<div class="equipment-number">' . intval($entry['qty']) . '</div>';
                                $title = htmlspecialchars($entry['brand'] ?: $entry['model'] ?: 'PV Module');
                                echo '<h6 class="equipment-title">' . $title . '</h6>';
                                echo '</div>';
                                echo '<div class="kv-inline">';
                                echo '<div class="kv-item"><span class="kv-label">Model</span><span class="kv-value">' . htmlspecialchars($entry['model'] ?: '-') . '</span></div>';
                                echo '<div class="kv-item"><span class="kv-label">Manufacturer</span><span class="kv-value">' . htmlspecialchars($entry['brand'] ?: '-') . '</span></div>';
                                echo '<div class="kv-item"><span class="kv-label">Power (per module)</span><span class="kv-value">' . number_format($entry['power_kw'], 3) . ' kW</span></div>';
                                echo '<div class="kv-item"><span class="kv-label">Total Power (group)</span><span class="kv-value">' . number_format($entry['total_kw'], 3) . ' kW</span></div>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <!-- System Layout -->
                    <div class="system-layout mt-4">
                        <h4 class="mb-3">System Layout</h4>
                        <?php if (empty($layout)) : ?>
                            <div class="text-center text-muted p-3">No system layout data available for this report.</div>
                        <?php else : ?>
                            <div class="row g-3">
                                <?php foreach ($layout as $l) : ?>
                                    <div class="col-md-6">
                                        <div class="card p-3">
                                            <div><strong>Roof ID:</strong> <?php echo htmlspecialchars($l['roof_id'] ?? '-'); ?></div>
                                            <div><strong>Quantity:</strong> <?php echo htmlspecialchars($l['quantity'] ?? '-'); ?></div>
                                            <div><strong>Azimuth:</strong> <?php echo htmlspecialchars($l['azimuth'] ?? '-'); ?></div>
                                            <div><strong>Tilt:</strong> <?php echo htmlspecialchars($l['tilt'] ?? '-'); ?></div>
                                            <div><strong>Mounting:</strong> <?php echo htmlspecialchars($l['mounting'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div> <!-- /pdf-snap: PV Modules + System Layout -->

                <!-- Inverters -->
                <div class="pdf-snap">
                    <div class="inverters-list mt-4">
                        <h4 class="mb-3">Inverters</h4>
                        <?php
                        $inverters = array_filter($equipment, function ($eq) {
                            if (!isset($eq['equipment_type'])) return false;
                            $type = preg_replace('/[^a-z0-9]/', '', strtolower($eq['equipment_type']));
                            return $type === 'inverter' || strpos($type, 'invert') !== false;
                        });

                        if (empty($inverters)) {
                            echo '<div class="text-center text-muted p-3">No inverter data available for this report.</div>';
                        } else {
                            echo '<div class="row g-3">';
                            foreach (array_values($inverters) as $i => $inv) {
                                echo '<div class="col-md-6">' . renderInverterCard($inv, $i) . '</div>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div> <!-- /pdf-snap: Inverters only -->
            </div>
        </div>

        <!-- 🔥 MPPT String Measurements Section -->
        <div class="section-card mppt-section mt-4">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="section-title m-0 page-break">MPPT String Measurements</h3>
            </div>

            <div class="p-3">
                <h5 class="mb-3">String Measurement Data</h5>
                <div class="table-responsive">
                    <?php echo renderMPPTTable($mpptMeasurements); ?>
                </div>
            </div>
        </div>

        <!-- Protection Section (empty placeholder) -->
        <div class="section-card protection-section mt-4">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="section-title m-0 page-break">Protection</h3>
            </div>

            <div class="p-3">
                <h5 class="mb-3">Circuit Protection</h5>

                <div class="table-responsive">
                    <table class="table table-striped" id="protection-table-report">
                        <thead class="table-header">
                            <tr>
                                <th>Scope</th>
                                <th>Circuit Breaker Brand</th>
                                <th>Circuit Breaker Model</th>
                                <th>Rated Current (A)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Filter for protection / circuit breaker items
                            $protectionItems = array_filter($equipment, function ($eq) {
                                if (empty($eq['equipment_type'])) return false;
                                $typeNorm = preg_replace('/[^a-z0-9]/', '', strtolower($eq['equipment_type']));
                                return $typeNorm === 'protectioncircuitbreaker' ||
                                    (strpos($typeNorm, 'protection') !== false && strpos($typeNorm, 'breaker') !== false) ||
                                    strtolower(trim($eq['equipment_type'])) === 'protection - circuit breaker';
                            });

                            // DEBUG: output protectionItems count and available equipment types as HTML comments (visible in page source)
                            $equipmentTypes = array_map(function ($e) {
                                return isset($e['equipment_type']) ? $e['equipment_type'] : '';
                            }, $equipment);

                            if (empty($protectionItems)) {
                                echo '<tr><td colspan="4" class="text-muted">No circuit protection data recorded for this report.</td></tr>';
                            } else {
                                foreach ($protectionItems as $p) {
                                    $scope = '';
                                    // Prefer structured column if present
                                    $rated = isset($p['rated_current']) && $p['rated_current'] !== '' && $p['rated_current'] !== null
                                        ? (string)$p['rated_current']
                                        : '';
                                    if (!empty($p['characteristics'])) {
                                        if (preg_match('/Scope:\s*([^|]+)/i', $p['characteristics'], $m)) {
                                            $scope = trim($m[1]);
                                        }
                                        if ($rated === '' && preg_match('/Rated\s+Current:\s*([0-9]+(?:\.[0-9]+)?)/i', $p['characteristics'], $m2)) {
                                            $rated = trim($m2[1]);
                                        }
                                    }

                                    // Fallbacks
                                    $scopeDisplay = $scope ?: ($p['scope'] ?? $p['equipment_subtype'] ?? '-');
                                    $brandDisplay = $p['brand'] ?? '-';
                                    $modelDisplay = $p['model'] ?? '-';
                                    $ratedDisplay = ($rated !== '' ? $rated : '-');

                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($scopeDisplay) . "</td>";
                                    echo "<td>" . htmlspecialchars($brandDisplay) . "</td>";
                                    echo "<td>" . htmlspecialchars($modelDisplay) . "</td>";
                                    echo "<td>" . htmlspecialchars($ratedDisplay) . "</td>";
                                    echo "</tr>\n";

                                    // If there are any raw characteristics (free-text) for this protection item,
                                    // render them as a small muted row beneath the main row so custom notes are visible
                                    if (!empty($p['characteristics'])) {
                                        echo "<tr><td colspan=\"4\" class=\"text-muted small\">" . formatCharacteristics($p['characteristics']) . "</td></tr>\n";
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Display Homopolar Protection info if present in $equipment
                $homopolar = null;
                foreach ($equipment as $eq) {
                    if (isset($eq['equipment_type']) && strtolower(trim($eq['equipment_type'])) === 'homopolar protection') {
                        $homopolar = $eq;
                        break;
                    }
                }

                if ($homopolar) {
                    $homInstaller = '';
                    $homBrand = '';
                    $homModel = '';
                    if (!empty($homopolar['characteristics'])) {
                        if (preg_match('/Installer:\s*([^|]+)/i', $homopolar['characteristics'], $h1)) {
                            $homInstaller = trim($h1[1]);
                        }
                        if (preg_match('/Brand:\s*([^|]+)/i', $homopolar['characteristics'], $h2)) {
                            $homBrand = trim($h2[1]);
                        }
                        if (preg_match('/Model:\s*([^|]+)/i', $homopolar['characteristics'], $h3)) {
                            $homModel = trim($h3[1]);
                        }
                    }

                    echo '<div class="mt-4">';
                    echo '<h5 class="mb-3">Homopolar Protection</h5>';
                    echo '<div class="row g-2">';
                    echo '<div class="col-md-4"><div class="data-label">Installer</div><div>' . htmlspecialchars($homInstaller ?: '-') . '</div></div>';
                    echo '<div class="col-md-4"><div class="data-label">Brand</div><div>' . htmlspecialchars($homBrand ?: ($homopolar['brand'] ?? '-')) . '</div></div>';
                    echo '<div class="col-md-4"><div class="data-label">Model</div><div>' . htmlspecialchars($homModel ?: ($homopolar['model'] ?? '-')) . '</div></div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>

                <!-- Protection Cables (PV Board / Point of Injection) -->
                <div class="mt-4">
                    <h5 class="mb-3">Protection Cables (PV Board / Point of Injection)</h5>
                    <div class="table-responsive">
                        <table class="table table-striped" id="protection-cables-report">
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
                                <?php
                                $cables = array_filter($equipment, function ($eq) {
                                    if (empty($eq['equipment_type'])) return false;
                                    return strtolower(trim($eq['equipment_type'])) === 'protection - cable' ||
                                        preg_match('/protection.*cable/i', $eq['equipment_type']);
                                });

                                if (empty($cables)) {
                                    echo '<tr><td colspan="5" class="text-muted">No protection cables recorded for this report.</td></tr>';
                                } else {
                                    foreach ($cables as $c) {
                                        $scope = '';
                                        $size = '';
                                        $ins = '';
                                        if (!empty($c['characteristics'])) {
                                            if (preg_match('/Scope:\s*([^|]+)/i', $c['characteristics'], $s)) {
                                                $scope = trim($s[1]);
                                            }
                                            if (preg_match('/Size:\s*([^|]+)/i', $c['characteristics'], $m)) {
                                                $size = trim($m[1]);
                                            }
                                            if (preg_match('/Insulation:\s*([^|]+)/i', $c['characteristics'], $n)) {
                                                $ins = trim($n[1]);
                                            }
                                        }

                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($scope ?: ($c['scope'] ?? '-')) . "</td>";
                                        echo "<td>" . htmlspecialchars($c['brand'] ?? '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($c['model'] ?? '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($size ?: '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($ins ?: '-') . "</td>";
                                        echo "</tr>\n";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Amperometric Clamp Measurements -->
                <div class="mt-4">
                    <h5 class="mb-3">Amperometric Clamp Measurements</h5>
                    <div class="table-responsive">
                        <table class="table table-striped" id="clamp-measurements-report">
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
                                <?php
                                $clamps = array_filter($equipment, function ($eq) {
                                    return isset($eq['equipment_type']) && strtolower(trim($eq['equipment_type'])) === 'amperometric clamp';
                                });

                                // (no debug) ensure parsing matches how data is saved in save_report.php

                                if (empty($clamps)) {
                                    echo '<tr><td colspan="5" class="text-muted">No clamp measurements recorded for this report.</td></tr>';
                                } else {
                                    foreach ($clamps as $c) {
                                        // The 'Amperometric Clamp' rows are saved in save_report.php with:
                                        // characteristics like "L1 Current: {val}A | L2 Current: {val}A | L3 Current: {val}A | Matches with meter: Yes"
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

                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($equipmentName ?: '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($l1 !== '' ? $l1 : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($l2 !== '' ? $l2 : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($l3 !== '' ? $l3 : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($match ?: '-') . "</td>";
                                        echo "</tr>\n";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Earth Protection Circuit -->
                <?php
                $earth = null;
                foreach ($equipment as $eq) {
                    if (isset($eq['equipment_type']) && strtolower(trim($eq['equipment_type'])) === 'earth protection circuit') {
                        $earth = $eq;
                        break;
                    }
                }

                // DEBUG: Log if earth found
                error_log("EARTH LOAD: earth found = " . ($earth ? 'YES' : 'NO') . " for report_id=$reportId");

                if ($earth) {
                    $resistance = '';
                    $reinforce = '';
                    if (!empty($earth['characteristics'])) {
                        // Be tolerant to encoding/character issues for the omega symbol.
                        // Match the numeric resistance value even if the unit symbol is corrupted (e.g. 'Î©').
                        if (preg_match('/Resistance:\s*([0-9]+(?:\.[0-9]+)?)/i', $earth['characteristics'], $m)) {
                            $resistance = trim($m[1]);
                        }
                        if (preg_match('/Earthing\/Reinforcement Needed:\s*(Yes|No)/i', $earth['characteristics'], $m2)) {
                            $reinforce = trim($m2[1]);
                        }
                    }

                    echo '<div class="mt-4">';
                    echo '<h5 class="mb-3">Earth Protection Circuit</h5>';
                    echo '<div class="row g-2">';
                    echo '<div class="col-md-4"><div class="data-label">Resistance (Ω)</div><div>' . htmlspecialchars($resistance !== '' ? $resistance . ' Ω' : '-') . '</div></div>';
                    echo '<div class="col-md-4"><div class="data-label">Reinforcement Needed</div><div>' . htmlspecialchars($reinforce ?: '-') . '</div></div>';
                    echo '</div>';

                    // Show recommendation if reinforcement is needed
                    if (strtolower($reinforce) === 'yes') {
                        echo '<div class="alert alert-warning mt-3" role="alert" style="display: flex; align-items: flex-start; gap: 10px;">';
                        echo '<i class="fas fa-exclamation-triangle mt-1"></i>';
                        echo '<div>';
                        echo '<strong>Recommendation:</strong><br>';
                        echo 'Resistance above 10Ω - Earth reinforcement is recommended';
                        echo '</div>';
                        echo '</div>';
                    }

                    echo '</div>';
                }
                ?>

                <!-- Regulatory Information -->
                <div class="alert alert-info mt-3" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Regulatory Information:</strong><br>
                    In Portugal, the acceptable earth values in an electrical installation are regulated by the Safety Regulations for Low Voltage Electrical Installations (RSIEBT).<br>
                    According to this regulation, the maximum permissible value for earth resistance is <strong>100 Ω</strong> for installations with a nominal current of less than 32 A and <strong>10 Ω</strong> for installations with a nominal current of more than 32 A.
                </div>
            </div>
        </div>

        <!-- Telemetry Section (generated) -->
        <div class="section-card mt-4">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-satellite-dish"></i>
                </div>
                <h3 class="section-title m-0 page-break">Telemetry</h3>
            </div>

            <div class="p-3">
                <?php
                // Render Telemetry - Credential entries
                $telemetryCreds = [];

                // Build inverter index -> name mapping from saved Inverter entries (preserve insertion order by id)
                $inverterNameMap = [];
                try {
                    $stmtInv = $pdo->prepare("SELECT * FROM report_equipment WHERE report_id = ? AND equipment_type = 'Inverter' ORDER BY id");
                    $stmtInv->execute([$reportId]);
                    $invRows = $stmtInv->fetchAll();
                    foreach ($invRows as $i => $invRow) {
                        $brand = trim($invRow['brand'] ?? '');
                        $model = trim($invRow['model'] ?? '');
                        // try to extract serial from characteristics if present
                        $serial = '';
                        if (!empty($invRow['characteristics'])) {
                            if (preg_match('/Serial:\s*([^|]+)/i', $invRow['characteristics'], $m)) {
                                $serial = trim($m[1]);
                            }
                        }
                        $parts = array_filter([$brand, $model]);
                        $name = implode(' ', $parts);
                        if ($serial) $name .= ' - ' . $serial;
                        $inverterNameMap[(string)$i] = $name ?: ('INV' . $i);
                    }
                } catch (Exception $e) {
                    // ignore mapping if query fails
                }
                foreach ($equipment as $eq) {
                    $typeNorm = preg_replace('/[^a-z0-9\- ]/', '', strtolower($eq['equipment_type'] ?? ''));
                    if (stripos($eq['equipment_type'] ?? '', 'Telemetry - Credential') !== false || $typeNorm === preg_replace('/[^a-z0-9\- ]/', '', strtolower('Telemetry - Credential'))) {
                        $telemetryCreds[] = $eq;
                    }
                }

                if (empty($telemetryCreds)) {
                    echo '<div class="alert alert-secondary">No telemetry credentials recorded for this report.</div>';
                } else {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-striped align-middle">';
                    echo '<thead class="table-light"><tr>';
                    echo '<th>Inverter Ref</th>';
                    echo '<th>Username</th>';
                    echo '<th>Password</th>';
                    echo '<th>IP</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($telemetryCreds as $cred) {
                        $chars = $cred['characteristics'] ?? '';
                        $parts = array_map('trim', explode('|', $chars));
                        $row = ['Inverter Ref' => '-', 'Username' => '-', 'Password' => '-', 'IP' => '-'];
                        foreach ($parts as $p) {
                            // Permissive matching for inverter reference
                            if (stripos($p, 'Inverter Ref:') === 0) {
                                $row['Inverter Ref'] = trim(substr($p, strlen('Inverter Ref:')));
                            } elseif (stripos($p, 'Inverter:') === 0) {
                                $row['Inverter Ref'] = trim(substr($p, strlen('Inverter:')));
                            } elseif (stripos($p, 'Inverter Ref') === 0 && strpos($p, ':') === false) {
                                $row['Inverter Ref'] = trim(str_replace('Inverter Ref', '', $p));
                            } elseif (stripos($p, 'Username:') === 0) {
                                $row['Username'] = trim(substr($p, strlen('Username:')));
                            } elseif (stripos($p, 'Password:') === 0) {
                                $row['Password'] = trim(substr($p, strlen('Password:')));
                            } elseif (stripos($p, 'IP:') === 0) {
                                $row['IP'] = trim(substr($p, strlen('IP:')));
                            }
                        }

                        // Fallback: try to infer inverter ref from characteristics if still empty
                        if (($row['Inverter Ref'] === '-' || $row['Inverter Ref'] === '') && !empty($chars)) {
                            // look for numeric id or INV### pattern
                            if (preg_match('/INV\s*#?0*(\d+)/i', $chars, $m)) {
                                $row['Inverter Ref'] = 'INV' . $m[1];
                            } elseif (preg_match('/^(\d{1,4})\b/', trim($chars), $m2)) {
                                $row['Inverter Ref'] = $m2[1];
                            }
                        }

                        // If inverter ref is a numeric index (e.g. '0','1') and we have a mapping, show the human-friendly name
                        if (isset($row['Inverter Ref']) && $row['Inverter Ref'] !== '' && isset($inverterNameMap[(string)$row['Inverter Ref']])) {
                            $row['Inverter Ref'] = $inverterNameMap[(string)$row['Inverter Ref']];
                        }

                        echo '<tr>';
                        $invDisplay = ($row['Inverter Ref'] !== '' && !is_null($row['Inverter Ref'])) ? $row['Inverter Ref'] : '-';
                        echo '<td>' . htmlspecialchars($invDisplay) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Username'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['Password'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['IP'] ?: '-') . '</td>';
                        echo '</tr>';

                        // If inverter ref is missing, show a small muted snippet of raw characteristics for debugging
                        if ($invDisplay === '-' && !empty($chars)) {
                            $snippet = htmlspecialchars(mb_substr(trim($chars), 0, 120));
                            echo '<tr class="text-muted small"><td colspan="4" style="padding-top:0.1rem;padding-bottom:0.6rem;color:#6c757d;">Raw: ' . $snippet . (mb_strlen($chars) > 120 ? '...' : '') . '</td></tr>';
                        }
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                ?>

                <?php
                // Render Communications entries saved as 'Communications'
                $commItems = [];
                foreach ($equipment as $eq) {
                    if (stripos($eq['equipment_type'] ?? '', 'Communications') !== false) {
                        $commItems[] = $eq;
                    }
                }

                if (!empty($commItems)) {
                    echo '<hr>';
                    echo '<h6 class="mt-3">Communications Devices</h6>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-striped align-middle">';
                    echo '<thead class="table-light"><tr>';
                    echo '<th>Equipment</th>';
                    echo '<th>Model</th>';
                    echo '<th>ID/Serial</th>';
                    echo '<th>MAC</th>';
                    echo '<th>IP</th>';
                    echo '<th>SIM Card</th>';
                    echo '<th>Location</th>';
                    echo '<th>FTP Server</th>';
                    echo '<th>FTP Username</th>';
                    echo '<th>FTP Password</th>';
                    echo '<th>File Format</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($commItems as $c) {
                        $equipmentName = $c['brand'] ?: '-';
                        $modelName = $c['model'] ?: '-';
                        $chars = $c['characteristics'] ?? '';
                        $parts = array_map('trim', explode('|', $chars));
                        $row = ['ID/Serial' => '-', 'MAC' => '-', 'IP' => '-', 'SIM Card' => '-', 'Location' => '-', 'FTP Server' => '-', 'FTP Username' => '-', 'FTP Password' => '-', 'File Format' => '-'];
                        foreach ($parts as $p) {
                            if (stripos($p, 'ID/Serial:') === 0) $row['ID/Serial'] = trim(substr($p, strlen('ID/Serial:')));
                            elseif (stripos($p, 'MAC:') === 0) $row['MAC'] = trim(substr($p, strlen('MAC:')));
                            elseif (stripos($p, 'IP:') === 0) $row['IP'] = trim(substr($p, strlen('IP:')));
                            elseif (stripos($p, 'SIM Card:') === 0) $row['SIM Card'] = trim(substr($p, strlen('SIM Card:')));
                            elseif (stripos($p, 'Location:') === 0) $row['Location'] = trim(substr($p, strlen('Location:')));
                            elseif (stripos($p, 'FTP Server:') === 0) $row['FTP Server'] = trim(substr($p, strlen('FTP Server:')));
                            elseif (stripos($p, 'FTP Username:') === 0) $row['FTP Username'] = trim(substr($p, strlen('FTP Username:')));
                            elseif (stripos($p, 'FTP Password:') === 0) $row['FTP Password'] = trim(substr($p, strlen('FTP Password:')));
                            elseif (stripos($p, 'File Format:') === 0) $row['File Format'] = trim(substr($p, strlen('File Format:')));
                        }

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($equipmentName) . '</td>';
                        echo '<td>' . htmlspecialchars($modelName) . '</td>';
                        echo '<td>' . htmlspecialchars($row['ID/Serial']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['MAC']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['IP']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['SIM Card']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Location']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['FTP Server']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['FTP Username']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['FTP Password']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['File Format']) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                ?>

                <?php
                // Render Telemetry - Meter (Smart Meter) entries
                $smartMeters = [];
                foreach ($equipment as $eq) {
                    $typeNorm = preg_replace('/[^a-z0-9\- ]/', '', strtolower($eq['equipment_type'] ?? ''));
                    if (stripos($eq['equipment_type'] ?? '', 'Telemetry - Meter') !== false || $typeNorm === preg_replace('/[^a-z0-9\- ]/', '', strtolower('Telemetry - Meter'))) {
                        $smartMeters[] = $eq;
                    }
                }

                if (!empty($smartMeters)) {
                    echo '<hr>';
                    echo '<h6 class="mt-3">Smart Meters</h6>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-striped align-middle">';
                    echo '<thead class="table-light"><tr>';
                    echo '<th>Mode</th>';
                    echo '<th>Brand</th>';
                    echo '<th>Model</th>';
                    echo '<th>Serial</th>';
                    echo '<th>CT Ratio</th>';
                    echo '<th>SIM</th>';
                    echo '<th>Location</th>';
                    echo '<th>LED1</th>';
                    echo '<th>LED2</th>';
                    echo '<th>LED6</th>';
                    echo '<th>GSM</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($smartMeters as $m) {
                        $brand = $m['brand'] ?: '-';
                        $model = $m['model'] ?: '-';
                        $chars = $m['characteristics'] ?? '';
                        $parts = array_map('trim', explode('|', $chars));
                        $row = [
                            'Mode' => '-',
                            'Serial' => '-',
                            'CT Ratio' => '-',
                            'SIM' => '-',
                            'Location' => '-',
                            'LED1' => '-',
                            'LED2' => '-',
                            'LED6' => '-',
                            'GSM' => '-'
                        ];
                        foreach ($parts as $p) {
                            if (stripos($p, 'Mode:') === 0) $row['Mode'] = trim(substr($p, strlen('Mode:')));
                            elseif (stripos($p, 'Serial:') === 0) $row['Serial'] = trim(substr($p, strlen('Serial:')));
                            elseif (stripos($p, 'CT Ratio:') === 0) $row['CT Ratio'] = trim(substr($p, strlen('CT Ratio:')));
                            elseif (stripos($p, 'SIM:') === 0) $row['SIM'] = trim(substr($p, strlen('SIM:')));
                            elseif (stripos($p, 'Location:') === 0) $row['Location'] = trim(substr($p, strlen('Location:')));
                            elseif (stripos($p, 'LED1:') === 0) $row['LED1'] = trim(substr($p, strlen('LED1:')));
                            elseif (stripos($p, 'LED2:') === 0) $row['LED2'] = trim(substr($p, strlen('LED2:')));
                            elseif (stripos($p, 'LED6:') === 0) $row['LED6'] = trim(substr($p, strlen('LED6:')));
                            elseif (stripos($p, 'GSM:') === 0) $row['GSM'] = trim(substr($p, strlen('GSM:')));
                        }

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['Mode']) . '</td>';
                        echo '<td>' . htmlspecialchars($brand) . '</td>';
                        echo '<td>' . htmlspecialchars($model) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Serial']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['CT Ratio']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['SIM']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Location']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['LED1']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['LED2']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['LED6']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['GSM']) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                ?>

                <?php
                // Render Energy Meter (Smart Meter) entries
                $meterItems = [];
                foreach ($equipment as $eq) {
                    if (isset($eq['equipment_type']) && trim($eq['equipment_type']) === 'Energy Meter') {
                        $meterItems[] = $eq;
                    }
                }

                if (!empty($meterItems)) {
                    echo '<hr>';
                    echo '<h6 class="mt-3">Energy Meters</h6>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-striped align-middle">';
                    echo '<thead class="table-light"><tr>';
                    echo '<th>Scope</th>';
                    echo '<th>Brand</th>';
                    echo '<th>Model</th>';
                    echo '<th>RS485 Address</th>';
                    echo '<th>CT Ratio</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($meterItems as $m) {
                        $scope = $m['deployment_status'] ?: '-';
                        $brand = $m['brand'] ?: '-';
                        $model = $m['model'] ?: '-';
                        $chars = $m['characteristics'] ?? '';
                        $parts = array_map('trim', explode('|', $chars));
                        $rs485 = '-';
                        $ct = '-';
                        foreach ($parts as $p) {
                            if (stripos($p, 'RS485 Address:') === 0) $rs485 = trim(substr($p, strlen('RS485 Address:')));
                            elseif (stripos($p, 'CT Ratio:') === 0) $ct = trim(substr($p, strlen('CT Ratio:')));
                        }

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($scope) . '</td>';
                        echo '<td>' . htmlspecialchars($brand) . '</td>';
                        echo '<td>' . htmlspecialchars($model) . '</td>';
                        echo '<td>' . htmlspecialchars($rs485) . '</td>';
                        echo '<td>' . htmlspecialchars($ct) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Punch List Section -->
        <div class="section-card mt-4">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#ffc107 0%,#ffb347 100%);">
                    <i class="fas fa-list"></i>
                </div>
                <h3 class="section-title m-0 page-break">Punch List</h3>
            </div>

            <div class="p-3">
                <?php
                $punchItems = [];
                foreach ($equipment as $eq) {
                    // Only include exact 'Punch List Item' to avoid migrated rows like 'Punch List Item (migrated)'
                    if (isset($eq['equipment_type']) && trim($eq['equipment_type']) === 'Punch List Item') {
                        $punchItems[] = $eq;
                    }
                }

                if (empty($punchItems)) {
                    echo '<div class="alert alert-secondary">No punch list items recorded for this report.</div>';
                } else {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm table-striped align-middle">';
                    echo '<thead class="table-light"><tr>';
                    echo '<th>ID</th>';
                    echo '<th>Severity</th>';
                    echo '<th>Description</th>';
                    echo '<th>Opening Date</th>';
                    echo '<th>Responsible</th>';
                    echo '<th>Resolution Date</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($punchItems as $pi) {
                        $chars = $pi['characteristics'] ?? '';
                        $parts = array_map('trim', explode('|', $chars));
                        $row = ['ID' => '-', 'Severity' => '-', 'Description' => '-', 'Opening Date' => '-', 'Responsible' => '-', 'Resolution Date' => '-'];
                        foreach ($parts as $p) {
                            if (stripos($p, 'ID:') === 0) $row['ID'] = trim(substr($p, strlen('ID:')));
                            elseif (stripos($p, 'Severity:') === 0) $row['Severity'] = trim(substr($p, strlen('Severity:')));
                            elseif (stripos($p, 'Description:') === 0) $row['Description'] = trim(substr($p, strlen('Description:')));
                            elseif (stripos($p, 'Opening Date:') === 0) $row['Opening Date'] = trim(substr($p, strlen('Opening Date:')));
                            elseif (stripos($p, 'Responsible:') === 0) $row['Responsible'] = trim(substr($p, strlen('Responsible:')));
                            elseif (stripos($p, 'Resolution Date:') === 0) $row['Resolution Date'] = trim(substr($p, strlen('Resolution Date:')));
                        }

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['ID']) . '</td>';
                        // Colorize severity
                        $sevRaw = strtolower($row['Severity']);
                        $badgeClass = 'secondary';
                        if (strpos($sevRaw, 'critical') !== false || strpos($sevRaw, 'high') !== false) $badgeClass = 'danger';
                        elseif (strpos($sevRaw, 'medium') !== false) $badgeClass = 'warning';
                        elseif (strpos($sevRaw, 'low') !== false || strpos($sevRaw, 'ok') !== false || strpos($sevRaw, 'minor') !== false) $badgeClass = 'success';
                        elseif (strpos($sevRaw, 'info') !== false) $badgeClass = 'info';
                        echo '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($row['Severity']) . '</span></td>';
                        echo '<td>' . htmlspecialchars($row['Description']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Opening Date']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Responsible']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Resolution Date']) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Additional Notes (placeholder) -->
        <div class="section-card mt-4">
            <div class="section-header d-flex align-items-center" style="gap:14px;">
                <div class="section-icon" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6c757d 0%,#adb5bd 100%);">
                    <i class="fas fa-sticky-note"></i>
                </div>
                <h3 class="section-title m-0 page-break">Additional Notes</h3>
            </div>

            <div class="p-3">
                <?php
                $notesItems = [];
                foreach ($equipment as $eq) {
                    if (isset($eq['equipment_type']) && trim($eq['equipment_type']) === 'Additional Notes') {
                        $notesItems[] = $eq;
                    }
                }

                if (empty($notesItems)) {
                    // Fallback: if user has a preview cookie (unsaved notes in the form), show it
                    $preview = '';
                    if (!empty($_COOKIE['commissioning_notes_preview'])) {
                        $preview = trim(urldecode($_COOKIE['commissioning_notes_preview']));
                    }

                    if ($preview !== '') {
                        echo '<div class="card mb-2"><div class="card-body"><p class="mb-0"><em>Preview (not yet saved):</em><br>' . nl2br(htmlspecialchars($preview)) . '</p></div></div>';
                    } else {
                        echo '<div class="alert alert-secondary">No additional notes recorded for this report.</div>';
                    }
                } else {
                    foreach ($notesItems as $ni) {
                        $chars = $ni['characteristics'] ?? '';
                        $noteText = '';
                        $parts = array_map('trim', explode('|', $chars));
                        foreach ($parts as $p) {
                            if (stripos($p, 'Notes:') === 0) {
                                $noteText = trim(substr($p, strlen('Notes:')));
                                break;
                            }
                        }
                        if ($noteText === '') $noteText = $chars;
                        echo '<div class="card mb-2"><div class="card-body"><p class="mb-0">' . nl2br(htmlspecialchars($noteText)) . '</p></div></div>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- End PDF content wrapper -->
    </div>

    <!-- html2pdf bundle (html2canvas + jsPDF) for client-side PDF export -->
    <!-- Removed SRI integrity to avoid blocking if CDN provides different digest; keep crossorigin for CORS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Ensure jsPDF UMD is available as window.jspdf.jsPDF across browsers/CDNs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function() {
            const btn = document.getElementById('downloadPdfBtn');
            if (!btn) return;
            btn.addEventListener('click', async function() {
                const el = document.getElementById('report-content');
                if (!el) {
                    alert('Report content not found.');
                    return;
                }

                const reportId = 'COM-<?php echo str_pad($report['id'] ?? 0, 5, '0', STR_PAD_LEFT); ?>';
                const project = '<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '_', $report['project_name'] ?? 'Report'); ?>';
                const filename = `Commissioning_Report_${project}_${reportId}.pdf`;

                const snaps = el.querySelectorAll('.pdf-snap');
                const hasSnaps = snaps && snaps.length > 0;
                const hasUMD = !!(window.jspdf && window.jspdf.jsPDF);
                const hasCanvas = !!window.html2canvas;
                const hasMultipage = hasSnaps && hasUMD && hasCanvas;

                if (hasMultipage) {
                    // Multipage export: each .pdf-snap becomes its own page, preserving screen design
                    const {
                        jsPDF
                    } = window.jspdf;
                    const pdf = new jsPDF({
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    });
                    const margin = 10; // mm
                    const pageW = pdf.internal.pageSize.getWidth() - margin * 2;
                    const pageH = pdf.internal.pageSize.getHeight() - margin * 2;
                    const prevBg = el.style.backgroundColor;
                    el.style.backgroundColor = '#FFFFFF';

                    for (let i = 0; i < snaps.length; i++) {
                        const section = snaps[i];
                        // Ensure on-screen styles (no pdf-export compaction)
                        // Render
                        const canvas = await window.html2canvas(section, {
                            scale: 2,
                            useCORS: true,
                            logging: false,
                            backgroundColor: '#FFFFFF'
                        });
                        const imgData = canvas.toDataURL('image/jpeg', 0.95);
                        const imgWpx = canvas.width;
                        const imgHpx = canvas.height;
                        const ratio = imgWpx / imgHpx;
                        let drawW = pageW;
                        let drawH = drawW / ratio;
                        if (drawH > pageH) {
                            drawH = pageH;
                            drawW = drawH * ratio;
                        }
                        if (i > 0) pdf.addPage();
                        pdf.addImage(imgData, 'JPEG', margin, margin, drawW, drawH);
                    }

                    pdf.save(filename);
                    el.style.backgroundColor = prevBg;
                    return;
                }

                // If we have snaps but no jsPDF UMD, try html2pdf pagebreaks to keep design per section
                if (hasSnaps) {
                    const optExact = {
                        margin: [10, 10, 10, 10],
                        filename,
                        image: {
                            type: 'jpeg',
                            quality: 0.98
                        },
                        html2canvas: {
                            scale: 2,
                            useCORS: true,
                            logging: false,
                            backgroundColor: '#FFFFFF'
                        },
                        jsPDF: {
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'portrait'
                        },
                        pagebreak: {
                            mode: ['css', 'legacy'],
                            before: '.pdf-snap'
                        }
                    };
                    const prevBg2 = el.style.backgroundColor;
                    el.style.backgroundColor = '#FFFFFF';
                    html2pdf().set(optExact).from(el).save().finally(() => {
                        el.style.backgroundColor = prevBg2;
                    });
                    return;
                }

                // Fallback: single-shot export of entire content (compact mode)
                const opt = {
                    margin: [10, 10, 10, 10],
                    filename,
                    image: {
                        type: 'jpeg',
                        quality: 0.95
                    },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false
                    },
                    jsPDF: {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    },
                    pagebreak: {
                        mode: ['avoid-all', 'css', 'legacy']
                    }
                };
                const prevBg = el.style.backgroundColor;
                el.style.backgroundColor = '#FFFFFF';
                el.classList.add('pdf-export');
                html2pdf().set(opt).from(el).save().finally(() => {
                    el.style.backgroundColor = prevBg;
                    el.classList.remove('pdf-export');
                });
            });
        })();
    </script>
</body>

</html>