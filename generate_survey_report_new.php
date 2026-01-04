<?php

/**
 * Professional Site Survey Report Generator
 * Similar structure to generate_report.php but adapted for Site Survey data
 * Generates beautiful PDF with all collected survey information
 */

// Start session
require_once 'includes/auth.php';
requireLogin();

// Include database connection
require_once 'config/database.php';

// Get current user
$user = getCurrentUser();

// Check if survey ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: survey_index.php');
    exit;
}

$surveyId = intval($_GET['id']);

// Get survey data
try {
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_reports 
        WHERE id = ? AND is_deleted = FALSE
    ");

    $stmt->execute([$surveyId]);
    $survey = $stmt->fetch();

    if (!$survey) {
        $_SESSION['error'] = 'Survey not found.';
        header('Location: survey_index.php');
        exit;
    }

    // Get buildings
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_buildings 
        WHERE report_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$surveyId]);
    $buildings = $stmt->fetchAll();

    // Get roofs grouped by building
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_roofs 
        WHERE report_id = ? 
        ORDER BY building_name, id ASC
    ");
    $stmt->execute([$surveyId]);
    $roofs = $stmt->fetchAll();

    // Get shading assessment
    $stmt = $pdo->prepare("
          SELECT s.*, s.installation_viable AS viable FROM site_survey_shading s 
        WHERE report_id = ? 
        ORDER BY building_name, id ASC
    ");
    $stmt->execute([$surveyId]);
    $shading = $stmt->fetchAll();

    // Get shading objects
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_shading_objects 
        WHERE report_id = ? 
        ORDER BY building_name, id ASC
    ");
    $stmt->execute([$surveyId]);
    $shadingObjects = $stmt->fetchAll();

    // Get checklist items
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_items 
        WHERE report_id = ? AND item_type = 'Survey - Checklist'
        ORDER BY id ASC
    ");
    $stmt->execute([$surveyId]);
    $checklistItems = $stmt->fetchAll();

    // Get photo checklist
    $stmt = $pdo->prepare("
        SELECT * FROM site_survey_items 
        WHERE report_id = ? AND item_type = 'Survey - Photo Checklist'
        ORDER BY id ASC
    ");
    $stmt->execute([$surveyId]);
    $photoChecklist = $stmt->fetchAll();

    // Get photos link
    $stmt = $pdo->prepare("
        SELECT value FROM site_survey_items 
        WHERE report_id = ? AND item_type = 'Survey - Photos Link'
        LIMIT 1
    ");
    $stmt->execute([$surveyId]);
    $photosLinkRow = $stmt->fetch();
    $photosLink = $photosLinkRow['value'] ?? '';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error retrieving survey: ' . $e->getMessage();
    header('Location: survey_index.php');
    exit;
}

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

function getStatusBadgeClass($status)
{
    if (empty($status)) return 'secondary';
    $lower = strtolower($status);
    if (strpos($lower, 'ok') !== false || strpos($lower, 'pass') !== false) return 'success';
    if (strpos($lower, 'fail') !== false || strpos($lower, 'no') !== false) return 'danger';
    if (strpos($lower, 'needs') !== false || strpos($lower, 'requires') !== false) return 'warning';
    return 'info';
}

function yesNo($v)
{
    if ($v === null || $v === '') return '-';
    if ((int)$v === 1) return 'Yes';
    return 'No';
}

// Add helper to remove accents (used when building file name in client-side PDF)
function removeAccents($text)
{
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

// Format date
$formattedDate = date('F j, Y', strtotime($survey['date']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Survey Report - <?php echo htmlspecialchars($survey['project_name']); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --color-bg: #ffffff;
            --color-bg-alt: #f5f7fa;
            --color-border: #d9e1e5;
            --color-primary: #2CCCD3;
            --color-primary-dark: #254A5D;
            --color-accent: #186C7F;
            --color-text: #101D20;
            --color-text-muted: #6B797D;
            --radius-sm: 4px;
            --radius-md: 6px;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --font-xs: 10px;
            --font-sm: 12px;
            --font-base: 13px;
            --font-md: 15px;
            --font-lg: 18px;
        }

        html {
            font-size: 13px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg-alt);
            color: var(--color-text);
            line-height: 1.5;
            margin: 0;
        }

        /* Container */
        #report-content {
            width: 100%;
            max-width: 1000px;
            /* mesma largura visual do cartão do cabeçalho */
            margin: 20px auto 40px;
            background: transparent;
        }

        /* Header (card) */
        .report-header {
            margin: 0 auto var(--space-4);
            max-width: 1000px;
            padding: var(--space-5);
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 90%);
            color: #fff;
            border-radius: var(--radius-md);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .report-title {
            font-size: var(--font-lg);
            font-weight: 600;
            margin: 0 0 var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .report-title i {
            font-size: 1.1em;
        }

        .report-subtitle {
            font-size: var(--font-sm);
            opacity: .9;
            margin: 0 0 var(--space-3);
        }

        .report-meta {
            display: grid;
            gap: var(--space-2);
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            background: rgba(255, 255, 255, .12);
            padding: var(--space-3);
            border-radius: var(--radius-md);
            font-size: var(--font-xs);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .meta-item i {
            font-size: .9em;
            opacity: .85;
        }

        /* Sections */
        .report-section {
            margin: 0 auto var(--space-4);
            max-width: 1000px;
            /* igual ao cabeçalho */
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--space-4);
            box-shadow: 0 2px 6px rgba(16, 29, 32, 0.04);
        }

        .section-title {
            font-size: var(--font-md);
            font-weight: 600;
            margin: 0 0 var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-primary-dark);
        }

        .section-title i {
            color: var(--color-primary);
        }

        /* Grid */
        .data-grid {
            display: grid;
            gap: var(--space-2);
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            margin: 0 0 var(--space-3);
        }

        .data-item {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            padding: var(--space-2) var(--space-2);
            border-radius: var(--radius-sm);
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
        }

        .data-label {
            font-size: var(--font-xs);
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }

        .data-value {
            font-size: var(--font-sm);
            font-weight: 500;
            word-break: break-word;
        }

        /* Tables */
        table.report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: var(--font-sm);
            margin: 0 0 var(--space-3);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .report-table th {
            background: var(--color-bg-alt);
            color: var(--color-primary-dark);
            text-align: left;
            padding: var(--space-2);
            font-size: var(--font-xs);
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 1px solid var(--color-border);
        }

        .report-table td {
            padding: var(--space-2);
            border-bottom: 1px solid var(--color-border);
            vertical-align: top;
        }

        .report-table tr:nth-child(even) td {
            background: #fafbfd;
        }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: var(--font-xs);
            font-weight: 600;
            border-radius: 12px;
            background: #e3e8ec;
            color: #333;
        }

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.danger {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Utility */
        .avoid-break {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .space-top {
            margin-top: var(--space-4);
        }

        .muted {
            color: var(--color-text-muted);
        }

        /* Footer */
        .report-footer {
            text-align: center;
            font-size: var(--font-xs);
            color: var(--color-text-muted);
            margin-top: var(--space-5);
            padding-top: var(--space-3);
            border-top: 1px solid var(--color-border);
        }

        /* Print */
        @media print {
            body {
                background: #fff;
            }

            #report-content {
                box-shadow: none;
                margin: 0 auto;
                max-width: 1000px;
                padding: 0;
            }

            .print-button {
                display: none !important;
            }

            .report-header,
            .report-section {
                box-shadow: none;
                margin-bottom: var(--space-3);
                page-break-inside: avoid;
            }

            /* keep electrical + switchboard group together as much as possible */
            [data-section="electrical"] .electrical-group {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .data-grid {
                gap: var(--space-1);
            }
        }
    </style>
</head>

<body>
    <!-- Download/Print Button (no-print: removed when generating server PDF) -->
    <div class="print-button no-print d-flex align-items-center">
        <a href="survey_index.php" class="btn btn-outline-secondary btn-modern me-2" title="Back to Surveys">
            <i class="fas fa-arrow-left me-2"></i>Back to Surveys
        </a>
        <a href="site_survey.php?survey_id=<?php echo $surveyId; ?>" class="btn btn-success btn-modern me-2" title="Edit Survey">
            <i class="fas fa-edit me-2"></i>Edit Survey
        </a>
        <button class="btn btn-primary-modern btn-modern" id="downloadPdfBtn" title="Download PDF">
            <i class="fas fa-file-download me-2"></i>Download PDF
        </button>
    </div>

    <!-- Report Content - Single Continuous Page -->
    <div id="report-content">
        <!-- HEADER -->
        <section class="report-section" data-section="header">
            <div class="report-header">
                <div class="report-title">
                    <i class="fas fa-map-location-dot me-2"></i>Site Survey Report
                </div>
                <div class="report-subtitle">Pre-Installation Assessment & Analysis</div>

                <div class="report-meta">
                    <div class="meta-item">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($survey['project_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo $formattedDate; ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($survey['responsible'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- GENERAL INFORMATION -->
        <section class="report-section" data-section="general">
            <h2 class="section-title"><i class="fas fa-info-circle"></i>General Information</h2>
            <div class="data-grid">
                <div class="data-item avoid-break">
                    <div class="data-label">Project Name</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['project_name']); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Date</div>
                    <div class="data-value"><?php echo $formattedDate; ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Responsible Person</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['responsible'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Survey Location</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['location'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">GPS Coordinates</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['gps'] ?? '-'); ?></div>
                </div>
            </div>

            <?php if (!empty($photosLink)): ?>
                <h2 class="section-title"><i class="fas fa-images"></i>Photos Repository</h2>
                <div class="data-grid">
                    <div class="data-item avoid-break">
                        <div class="data-label">Photos Link</div>
                        <div class="data-value"><a href="<?php echo htmlspecialchars($photosLink); ?>" target="_blank"><?php echo htmlspecialchars($photosLink); ?></a></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Installation Site & Buildings -->
        <?php if (!empty($buildings)): ?>
            <!-- BUILDINGS -->
            <section class="report-section" data-section="buildings">
                <h2 class="section-title"><i class="fas fa-building"></i>Installation Site - Buildings</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Building Name</th>
                            <th>Parapet Height (m)</th>
                            <th>Mount Location</th>
                            <th>Roof Type</th>
                            <th>Support Structure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildings as $building): ?>
                            <tr class="avoid-break">
                                <td><?php echo htmlspecialchars($building['name']); ?></td>
                                <td><?php echo htmlspecialchars($building['parapet_height_m'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($building['mount_location_type'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($building['roof_type'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($building['support_structure'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <h2 class="section-title"><i class="fas fa-building me-2"></i>Installation Site - Additional Details</h2>
                <div class="data-grid">
                    <div class="data-item avoid-break">
                        <div class="data-label">Is there access to the roof?</div>
                        <div class="data-value"><?php echo yesNo($survey['roof_access_available'] ?? null); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Feasible to install a permanent ladder?</div>
                        <div class="data-value"><?php echo yesNo($survey['permanent_ladder_feasible'] ?? null); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Installation Site Observations</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['installation_site_notes'] ?? '-'); ?></div>
                    </div>
                </div>

                <!-- Map Section -->
                <h2 class="section-title"><i class="fas fa-map-location-dot me-2"></i>Installation Site - Map</h2>
                <div class="data-grid">
                    <div class="data-item avoid-break">
                        <div class="data-label">Selected GPS Location</div>
                        <div class="data-value" data-gps="<?php echo htmlspecialchars($survey['gps'] ?? ''); ?>"><?php echo htmlspecialchars($survey['gps'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Measured Area</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['map_area_m2'] ?? '-'); ?> m²</div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Azimuth</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['map_azimuth_deg'] ?? '-'); ?>°</div>
                    </div>
                </div>

                <?php if (!empty($survey['map_polygon_coords']) || !empty($survey['gps'])): ?>
                    <div class="map-preview-container" style="margin-top: 20px; page-break-inside: avoid;">
                        <div class="data-label" style="margin-bottom: 10px;">Map Preview</div>
                        <div id="report-map" style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 6px;"></div>
                        <!-- Hidden field with polygon coordinates for JavaScript (may be empty) -->
                        <input type="hidden" id="existing_polygon_coords" value="<?php echo htmlspecialchars($survey['map_polygon_coords']); ?>">
                        <script>
                            // Inject polygon coords as raw JSON into a JS variable (fallback to null)
                            try {
                                const REPORT_POLYGON_COORDS = <?php echo (!empty($survey['map_polygon_coords']) && $survey['map_polygon_coords'] !== 'null') ? json_encode(json_decode($survey['map_polygon_coords'], true)) : 'null'; ?>;
                                window.REPORT_POLYGON_COORDS = REPORT_POLYGON_COORDS;
                            } catch (e) {
                                window.REPORT_POLYGON_COORDS = null;
                            }
                        </script>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Additional Notes moved to end of document -->

        <!-- Roof Details -->
        <?php if (!empty($roofs)): ?>
            <!-- ROOFS -->
            <section class="report-section" data-section="roofs">
                <h2 class="section-title"><i class="fas fa-warehouse"></i>Roof Details & Condition Assessment</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Building</th>
                            <th>Roof ID</th>
                            <th>Pitch Angle</th>
                            <th>Orientation</th>
                            <th>Condition</th>
                            <th>Structure Visual</th>
                            <th>Expert Assessment Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roofs as $roof): ?>
                            <tr class="avoid-break">
                                <td><?php echo htmlspecialchars($roof['building_name']); ?></td>
                                <td><?php echo htmlspecialchars($roof['identification'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($roof['angle_pitch'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($roof['orientation'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadgeClass($roof['roof_condition']); ?>">
                                        <?php echo htmlspecialchars($roof['roof_condition'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($roof['structure_visual'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($roof['requires_expert_assessment'] === 'Yes'): ?>
                                        <span class="status-badge warning"><i class="fas fa-exclamation-triangle me-1"></i>Yes</span>
                                    <?php else: ?>
                                        <span class="status-badge success">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <!-- ELECTRICAL / PANEL DETAILS -->
        <section class="report-section" data-section="electrical">
            <div class="electrical-group avoid-break">
                <h2 class="section-title"><i class="fas fa-plug"></i>Electrical & Panel Details</h2>
                <div class="data-grid">
                    <div class="data-item avoid-break">
                        <div class="data-label">Injection Point Type</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['injection_point_type'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Circuit Type</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['circuit_type'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Inverter Location</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['inverter_location'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">PV Protection Board Location</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['pv_protection_board_location'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Cable Distance PV Board → Injection (m)</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['pv_board_to_injection_distance_m'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Space available for isolator/breaker?</div>
                        <div class="data-value"><?php echo yesNo($survey['injection_has_space_for_switch'] ?? null); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Busbar space available for connection?</div>
                        <div class="data-value"><?php echo yesNo($survey['injection_has_busbar_space'] ?? null); ?></div>
                    </div>
                </div>
                <h3 class="section-title space-top" style="font-size: var(--font-md);"><i class="fas fa-bolt"></i>Switchboard Details</h3>
                <div class="data-grid">
                    <div class="data-item avoid-break">
                        <div class="data-label">Panel Cable Exterior to Main Gauge </div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['panel_cable_exterior_to_main_gauge'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Switchboard Brand / Model</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['panel_brand_model'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Circuit Breaker Brand / Model</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['breaker_brand_model'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Rated Current (A)</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['breaker_rated_current_a'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Short-circuit Current (kA)</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['breaker_short_circuit_current_ka'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Residual Current (mA)</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['residual_current_ma'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">Earth Measurement (Ω)</div>
                        <div class="data-value"><?php echo htmlspecialchars($survey['earth_measurement_ohms'] ?? '-'); ?></div>
                    </div>
                    <div class="data-item avoid-break">
                        <div class="data-label">E‑Redes Bidirectional Meter?</div>
                        <div class="data-value"><?php echo yesNo($survey['is_bidirectional_meter'] ?? null); ?></div>
                    </div>
                </div>
            </div> <!-- end .electrical-group -->
        </section>

        <!-- GENERATOR -->
        <section class="report-section" data-section="generator">
            <h2 class="section-title"><i class="fas fa-charging-station"></i>Generator</h2>
            <div class="data-grid">
                <div class="data-item avoid-break">
                    <div class="data-label">Generator present?</div>
                    <div class="data-value"><?php echo yesNo($survey['generator_exists'] ?? null); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Mode of Operation</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['generator_mode'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Feeds</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['generator_scope'] ?? '-'); ?></div>
                </div>
            </div>
        </section>

        <!-- COMMUNICATIONS -->
        <section class="report-section" data-section="communications">
            <h2 class="section-title"><i class="fas fa-wifi"></i>Communications</h2>
            <div class="data-grid">
                <div class="data-item avoid-break">
                    <div class="data-label">Wi‑Fi near PV equipment?</div>
                    <div class="data-value"><?php echo yesNo($survey['comm_wifi_near_pv'] ?? null); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Ethernet near PV equipment?</div>
                    <div class="data-value"><?php echo yesNo($survey['comm_ethernet_near_pv'] ?? null); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">UTP cabling required?</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['comm_utp_requirement'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">UTP cable length (m)</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['comm_utp_length_m'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Client willing to open router ports?</div>
                    <div class="data-value"><?php echo yesNo($survey['comm_router_port_open_available'] ?? null); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Port number</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['comm_router_port_number'] ?? '-'); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Mobile network coverage (1–5)</div>
                    <div class="data-value"><?php echo htmlspecialchars($survey['comm_mobile_coverage_level'] ?? '-'); ?></div>
                </div>
            </div>
        </section>

        <!-- Shading Assessment -->
        <?php if (!empty($shading)): ?>
            <!-- SHADING -->
            <section class="report-section" data-section="shading">
                <h2 class="section-title"><i class="fas fa-cloud-sun-rain"></i>Shading Assessment</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Building</th>
                            <th>Status</th>
                            <th>Viable</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shading as $shadingItem): ?>
                            <tr class="avoid-break">
                                <td><?php echo htmlspecialchars($shadingItem['building_name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadgeClass($shadingItem['shading_status']); ?>">
                                        <?php echo htmlspecialchars($shadingItem['shading_status'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($shadingItem['viable'] === null): ?>
                                        -
                                    <?php elseif ($shadingItem['viable'] == 1): ?>
                                        <span class="status-badge success">Yes</span>
                                    <?php else: ?>
                                        <span class="status-badge danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($shadingItem['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($shadingObjects)): ?>
                    <h3 class="section-title space-top" style="font-size: var(--font-md);"><i class="fas fa-tree"></i>Shading Objects</h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Building</th>
                                <th>Object Type</th>
                                <th>Cause</th>
                                <th>Height (m)</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shadingObjects as $obj): ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($obj['building_name']); ?></td>
                                    <td><?php echo htmlspecialchars($obj['object_type'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($obj['cause'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($obj['height_m'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($obj['quantity'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($obj['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Checklists -->
        <?php if (!empty($checklistItems) || !empty($photoChecklist)): ?>
            <!-- CHECKLISTS -->
            <section class="report-section" data-section="checklists">
                <h2 class="section-title"><i class="fas fa-clipboard-check"></i>Survey Checklists</h2>
                <?php if (!empty($checklistItems)): ?>
                    <h3 class="section-title space-top" style="font-size: var(--font-md); border:0; padding:0; margin: var(--space-2) 0 var(--space-2);"><i class="fas fa-list"></i>General Checklist</h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklistItems as $item): ?>
                                <tr class="avoid-break">
                                    <td><?php echo htmlspecialchars($item['label'] ?? $item['item_key']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($item['status']); ?>">
                                            <?php echo htmlspecialchars($item['status'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['note'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php if (!empty($photoChecklist)): ?>
                    <h3 class="section-title space-top" style="font-size: var(--font-md); border:0; padding:0; margin: var(--space-2) 0 var(--space-2);"><i class="fas fa-camera"></i>Photo Checklist</h3>
                    <div class="data-grid">
                        <?php foreach ($photoChecklist as $photo): ?>
                            <div class="data-item avoid-break"><span><?php echo htmlspecialchars($photo['label'] ?? $photo['item_key']); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <!-- ADDITIONAL NOTES -->
        <section class="report-section" data-section="notes">
            <h2 class="section-title"><i class="fas fa-sticky-note"></i>Additional Notes</h2>
            <div class="data-grid">
                <div class="data-item avoid-break">
                    <div class="data-label">Survey Findings & Observations</div>
                    <div class="data-value"><?php echo nl2br(htmlspecialchars($survey['survey_notes'] ?? '-')); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Challenges or Constraints</div>
                    <div class="data-value"><?php echo nl2br(htmlspecialchars($survey['challenges'] ?? '-')); ?></div>
                </div>
                <div class="data-item avoid-break">
                    <div class="data-label">Installation Site Observations</div>
                    <div class="data-value"><?php echo nl2br(htmlspecialchars($survey['installation_site_notes'] ?? '-')); ?></div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <div class="report-footer">
            <strong>Cleanwatts Portal</strong> — Professional Solar Solutions<br>
            Generated: <?php echo date('Y-m-d H:i:s'); ?> | Report ID: SURVEY-<?php echo str_pad($surveyId, 6, '0', STR_PAD_LEFT); ?>
        </div>
    </div> <!-- end #report-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Leaflet for Map Preview -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Removed continuous view JS - controls are not present for site survey layout -->

    <!-- PDF Export Engine (client-side, matches commissioning layout) -->
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
            // Ensure fonts have loaded for accurate rendering
            try {
                await document.fonts.ready;
            } catch (e) {
                /* ignore */
            }
            // Prepare exact pixel dimensions for A4 at 96dpi
            const A4_PX_WIDTH = Math.round(210 * 96 / 25.4); // ~794px
            const A4_PX_HEIGHT = Math.round(297 * 96 / 25.4); // ~1123px
            // Hide interactive elements that shouldn't appear in the PDF
            const hiddenEls = [];
            document.querySelectorAll('.no-print').forEach(el => {
                hiddenEls.push({
                    el,
                    display: el.style.display
                });
                el.style.display = 'none';
            });
            const project = '<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '_', removeAccents($survey['project_name'] ?? 'SiteSurvey')); ?>';
            const surveyDate = '<?php echo date('Y-m-d', strtotime($survey['date'] ?? 'now')); ?>';
            const idStr = 'SURV-<?php echo str_pad($surveyId, 6, '0', STR_PAD_LEFT); ?>';

            // Capture the entire report as a single canvas then slice into A4 pages
            // Use the natural on-screen layout (no forced width changes)
            const reportEl = document.getElementById('report-content') || document.body;

            // Bring top of report into view
            try {
                reportEl.scrollIntoView();
            } catch (e) {
                // ignore
            }

            const scale = 2;
            // If the report map isn't initialized, adjust preview behavior (map preview visible or fallback message)
            if (!window.REPORT_MAP_INITIALIZED) {
                try {
                    const mapPreview = document.getElementById('report-map');
                    if (mapPreview) {
                        mapPreview.innerHTML = '<div style="text-align: center; color: #666;">\n+                                    <i class="fas fa-info-circle"></i><br>No map preview data available\n+                                </div>';
                    }
                } catch (err) {
                    /* ignore */ }
            }

            const canvas = await html2canvas(reportEl, {
                scale: scale,
                useCORS: true,
                logging: false,
                backgroundColor: '#FFFFFF'
            });
            const canvasWidth = canvas.width;
            const canvasHeight = canvas.height;
            const a4CssHeight = 297 * (96 / 25.4); // CSS px for A4 height (96dpi)
            const a4CanvasHeight = Math.round(a4CssHeight * scale);

            // Margins in mm (left/right/top/bottom)
            const marginLeftMm = 12;
            const marginRightMm = 12;
            const marginTopMm = 10;
            const marginBottomMm = 10;
            const pageWidthMm = pdf.internal.pageSize.getWidth();
            const pageHeightMm = pdf.internal.pageSize.getHeight();
            const availableWidthMm = pageWidthMm - marginLeftMm - marginRightMm;
            const availableHeightMm = pageHeightMm - marginTopMm - marginBottomMm;

            // Build safe breakpoints from elements marked as avoid-break inside report
            const pageRect = reportEl.getBoundingClientRect();
            const avoidEls = Array.from(reportEl.querySelectorAll('.avoid-break'));
            const safeBreaks = avoidEls.map(el => {
                const r = el.getBoundingClientRect();
                const bottomCssPx = r.bottom - pageRect.top; // bottom position relative to page in CSS px
                return Math.round(bottomCssPx * scale); // convert to canvas px
            }).sort((a, b) => a - b);

            let pageIndex = 0;
            for (let sliceStart = 0; sliceStart < canvasHeight;) {
                let desired = sliceStart + a4CanvasHeight;
                // Find best safe breakpoint <= desired and > sliceStart + 0.35*desired (avoid too small first page)
                let sliceEnd = desired;
                for (let i = safeBreaks.length - 1; i >= 0; i--) {
                    const safe = safeBreaks[i];
                    if (safe <= desired && safe > sliceStart + Math.round(a4CanvasHeight * 0.25)) {
                        sliceEnd = safe;
                        break;
                    }
                }
                // Clear any accidental global (not used), keep consistent naming
                // mapInitialized = true; // removed
                // If sliceEnd equals sliceStart (safety), fallback to desired
                if (sliceEnd <= sliceStart) sliceEnd = Math.min(canvasHeight, desired);
                // If a small safe-break right after the desired boundary exists and would avoid a tiny top fragment in the next page,
                // allow extending the slice end by a small margin to include it on this page.
                const extraAllowPx = Math.round(a4CanvasHeight * 0.12); // 12% of A4 height in canvas px
                const nextSafe = safeBreaks.find(s => s > desired);
                if (nextSafe && (nextSafe - desired) <= extraAllowPx && (nextSafe - sliceStart) <= (a4CanvasHeight + extraAllowPx)) {
                    sliceEnd = nextSafe;
                }

                const sliceHeight = Math.min(canvasHeight - sliceStart, sliceEnd - sliceStart);
                if (sliceHeight <= 8) break; // avoid nearly-empty pages

                const sliceCanvas = document.createElement('canvas');
                sliceCanvas.width = canvasWidth;
                sliceCanvas.height = sliceHeight;
                const sctx = sliceCanvas.getContext('2d');
                sctx.drawImage(canvas, 0, sliceStart, canvasWidth, sliceHeight, 0, 0, canvasWidth, sliceHeight);

                const imgDataSlice = sliceCanvas.toDataURL('image/jpeg', 0.95);

                // compute scaling to fit inside availableWidth/Height while maintaining aspect ratio
                let imageWidthMm = availableWidthMm;
                let imageHeightMm = (sliceHeight * imageWidthMm) / canvasWidth;
                if (imageHeightMm > availableHeightMm) {
                    const scaleDown = availableHeightMm / imageHeightMm;
                    imageHeightMm = Math.round(imageHeightMm * scaleDown);
                    imageWidthMm = Math.round(imageWidthMm * scaleDown);
                }

                const xMm = (pageWidthMm - imageWidthMm) / 2; // center horizontally within page width
                const yMm = marginTopMm; // top margin

                if (pageIndex > 0) pdf.addPage();
                pdf.addImage(imgDataSlice, 'JPEG', xMm, yMm, imageWidthMm, imageHeightMm);

                // Add clickable links that are within this slice (query anchors from reportEl)
                try {
                    const anchors = reportEl.querySelectorAll('a[href]');
                    anchors.forEach(a => {
                        const r = a.getBoundingClientRect();
                        const topCss = r.top - pageRect.top; // CSS px
                        const bottomCss = r.bottom - pageRect.top;
                        const topCanvas = Math.round(topCss * scale);
                        const bottomCanvas = Math.round(bottomCss * scale);
                        if (topCanvas >= sliceStart && topCanvas < sliceStart + sliceHeight) {
                            // map CSS px -> mm with x/y offsets and scaling
                            const pageWidthMmLocal = pdf.internal.pageSize.getWidth();
                            const leftPx = r.left - pageRect.left;
                            const x_mm = xMm + (leftPx * (imageWidthMm / canvasWidth));
                            const w_mm = r.width * (imageWidthMm / canvasWidth);
                            const y_mm = yMm + ((topCanvas - sliceStart) * (imageWidthMm / canvasWidth));
                            const h_mm = (bottomCanvas - topCanvas) * (imageWidthMm / canvasWidth);
                            try {
                                pdf.link(x_mm, y_mm, w_mm, h_mm, {
                                    url: a.getAttribute('href')
                                });
                            } catch (e) {}
                        }
                    });
                } catch (e) {
                    /* ignore link overlay if complex */
                }

                sliceStart = sliceStart + sliceHeight;
                pageIndex++;
            }

            pdf.save(`${idStr}_${project}_${surveyDate}.pdf`);

            // Restore hidden elements' display
            hiddenEls.forEach(h => h.el.style.display = h.display || '');
        });
    </script>

    <!-- Initialize Map Preview -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mapElement = document.getElementById('report-map');
            if (!mapElement) return;
            // Get polygon coordinates injected by PHP as raw JSON (safe since it's stored as JSON in DB)
            const coords = (function() {
                try {
                    // REPORT_POLYGON_COORDS is injected by PHP on window (null or JSON array)
                    return (window.REPORT_POLYGON_COORDS && window.REPORT_POLYGON_COORDS !== 'null') ? window.REPORT_POLYGON_COORDS : null;
                } catch (e) {
                    return null;
                }
            })();
            // Read GPS attribute (if present) as fallback; parse lat/lng
            const gpsEl = document.querySelector('[data-gps]');
            const gpsVal = gpsEl && gpsEl.getAttribute('data-gps') ? gpsEl.getAttribute('data-gps') : null;
            let gpsCoords = null;
            if (gpsVal) {
                const parts = gpsVal.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
                if (parts.length >= 2) {
                    const la = parseFloat(parts[0]);
                    const lo = parseFloat(parts[1]);
                    if (!isNaN(la) && !isNaN(lo)) gpsCoords = [la, lo];
                }
            }
            try {
                console.log('REPORT_POLYGON_COORDS', typeof REPORT_POLYGON_COORDS !== 'undefined' ? REPORT_POLYGON_COORDS : null);
                console.log('gpsVal', gpsVal);
                window.REPORT_MAP_INITIALIZED = false;
                if ((!Array.isArray(coords) || coords.length === 0) && !gpsCoords) return;

                // Initialize map
                const map = L.map('report-map', {
                    zoomControl: false,
                    attributionControl: false
                }).setView((gpsCoords || (coords && coords.length) ? (gpsCoords || coords[0]) : [39.5, -8.0]), 16);

                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add polygon
                // coords should be an array of arrays like [[lat, lng], ...]
                // Add polygon if coordinates exist
                if (coords && Array.isArray(coords) && coords.length) {
                    const polygon = L.polygon(coords, {
                        color: '#007bff',
                        weight: 2,
                        opacity: 0.8,
                        fillColor: '#007bff',
                        fillOpacity: 0.2
                    }).addTo(map);

                    // Fit map to polygon bounds
                    map.fitBounds(polygon.getBounds(), {
                        padding: [20, 20]
                    });
                }

                // Add GPS marker if available
                const gpsField = document.querySelector('[data-gps]');
                if (gpsField && gpsField.getAttribute('data-gps')) {
                    const gpsValue = gpsField.getAttribute('data-gps');
                    const parts = gpsValue.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
                    if (parts.length >= 2) {
                        const lat = parseFloat(parts[0]);
                        const lng = parseFloat(parts[1]);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            L.marker([lat, lng], {
                                icon: L.divIcon({
                                    className: 'gps-marker',
                                    html: '<i class="fas fa-map-marker-alt" style="color: #dc3545; font-size: 24px;"></i>',
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 24]
                                })
                            }).addTo(map);
                        }
                    }
                }

                console.log('Report map initialized successfully');
                window.REPORT_MAP_INITIALIZED = true;

            } catch (e) {
                console.error('Error initializing report map:', e);
                mapElement.innerHTML = '<div style="text-align: center; color: #666;"><i class="fas fa-exclamation-triangle"></i><br>Map preview unavailable</div>';
            }
        });
    </script>
</body>

</html>