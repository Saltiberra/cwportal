<?php

/**
 * Site Survey Report Form - PV System
 * 
 * Similar to Commissioning Form but adapted for Site Survey
 * Design and structure identical to comissionamento.php
 */

// 🔒 Require login to access site survey form
require_once 'includes/auth.php';
requireLogin();

// Include database connection
require_once 'config/database.php';
require_once 'includes/audit.php';

// Load existing survey if provided
$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : null;
$survey = null;
$checklistItems = [];
$photoChecklistChecked = [];
$buildingNames = [];
$roofDetailsMap = [];
$shadingMap = [];
// Site assessment now integrated per roof row; keep legacy array only to migrate old items if needed
$legacySiteAssessment = [
    'roof_condition' => null,
    'structure_visual' => '',
    'structure_weight_load' => '',
    'structure_wind_coverage' => '',
    'requires_expert_assessment' => ''
];

if ($surveyId) {
    $stmt = $pdo->prepare("SELECT * FROM site_survey_reports WHERE id = ? LIMIT 1");
    $stmt->execute([$surveyId]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($survey) {
        $itemsStmt = $pdo->prepare("SELECT item_key, label, status, note FROM site_survey_items WHERE report_id = ? AND item_type = 'Survey - Checklist' ORDER BY id ASC");
        $itemsStmt->execute([$surveyId]);
        $checklistItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Load photo checklist (checked items only)
        $photoStmt = $pdo->prepare("SELECT item_key FROM site_survey_items WHERE report_id = ? AND item_type = 'Survey - Photo Checklist' ORDER BY id ASC");
        $photoStmt->execute([$surveyId]);
        $photoChecklistChecked = array_map(function ($r) {
            return $r['item_key'];
        }, $photoStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        // Load Photos Repository link if present
        $photosLink = '';
        $pstmt = $pdo->prepare("SELECT value FROM site_survey_items WHERE report_id = ? AND item_type = 'Survey - Photos Link' LIMIT 1");
        $pstmt->execute([$surveyId]);
        $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
        if ($prow && isset($prow['value'])) {
            $photosLink = $prow['value'];
        }

        // Load Site Assessment items
        // Load legacy site assessment items (for migration only)
        $assStmt = $pdo->prepare("SELECT item_key, status, value FROM site_survey_items WHERE report_id = ? AND item_type = 'Site Assessment' ORDER BY id ASC");
        $assStmt->execute([$surveyId]);
        foreach ($assStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $k = $row['item_key'];
            if ($k === 'roof_condition') {
                $legacySiteAssessment['roof_condition'] = is_null($row['value']) ? null : (int)$row['value'];
            } elseif ($k === 'requires_expert_assessment') {
                $legacySiteAssessment['requires_expert_assessment'] = (string)($row['status'] ?? '');
            } elseif (isset($legacySiteAssessment[$k])) {
                $legacySiteAssessment[$k] = (string)($row['status'] ?? '');
            }
        }

        // Load existing buildings
        $bstmt = $pdo->prepare("SELECT name, parapet_height_m, mount_location_type, roof_type, support_structure FROM site_survey_buildings WHERE report_id = ? ORDER BY id ASC");
        $bstmt->execute([$surveyId]);
        $buildingNames = array_map(function ($r) {
            return [
                'name' => $r['name'],
                'parapet_height_m' => $r['parapet_height_m'],
                'mount_location_type' => $r['mount_location_type'],
                'roof_type' => $r['roof_type'],
                'support_structure' => $r['support_structure']
            ];
        }, $bstmt->fetchAll(PDO::FETCH_ASSOC));

        // Load roofs grouped by building name
        $rstmt = $pdo->prepare("SELECT building_name, identification, angle_pitch, orientation, roof_condition, structure_visual, structure_weight_load, structure_wind_coverage, requires_expert_assessment FROM site_survey_roofs WHERE report_id = ? ORDER BY id ASC");
        $rstmt->execute([$surveyId]);
        $rows = $rstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $bn = $row['building_name'];
            if (!isset($roofDetailsMap[$bn])) $roofDetailsMap[$bn] = [];
            $roofDetailsMap[$bn][] = [
                'identification' => $row['identification'],
                'angle_pitch' => $row['angle_pitch'],
                'orientation' => $row['orientation'],
                'roof_condition' => $row['roof_condition'],
                'structure_visual' => $row['structure_visual'],
                'structure_weight_load' => $row['structure_weight_load'],
                'structure_wind_coverage' => $row['structure_wind_coverage'],
                'requires_expert_assessment' => $row['requires_expert_assessment']
            ];
        }

        // Load shading summary and objects grouped by building
        // alias 'installation_viable' as 'viable' to match existing DB schema (setup_database uses installation_viable)
        $sstmt = $pdo->prepare("SELECT building_name, shading_status, installation_viable AS viable, notes FROM site_survey_shading WHERE report_id = ? ORDER BY id ASC");
        $sstmt->execute([$surveyId]);
        $shadingRows = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($shadingRows as $srow) {
            $bn = $srow['building_name'];
            if (!isset($shadingMap[$bn])) $shadingMap[$bn] = [
                'status' => $srow['shading_status'],
                'viable' => is_null($srow['viable']) ? null : (int)$srow['viable'],
                'notes' => $srow['notes'],
                'objects' => []
            ];
        }
        $oStmt = $pdo->prepare("SELECT building_name, object_type, cause, height_m, quantity, notes FROM site_survey_shading_objects WHERE report_id = ? ORDER BY id ASC");
        $oStmt->execute([$surveyId]);
        foreach ($oStmt->fetchAll(PDO::FETCH_ASSOC) as $orow) {
            $bn = $orow['building_name'];
            if (!isset($shadingMap[$bn])) $shadingMap[$bn] = ['status' => '', 'viable' => null, 'notes' => '', 'objects' => []];
            $shadingMap[$bn]['objects'][] = [
                'object_type' => $orow['object_type'],
                'cause' => $orow['cause'],
                'height_m' => $orow['height_m'],
                'quantity' => $orow['quantity'],
                'notes' => $orow['notes']
            ];
        }
    }
}

// Note: Representative/CPE and EPC fields removed per request; no related queries needed

// Get current user
$user = getCurrentUser();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="fas fa-map-location-dot me-2"></i>
        Site Survey Report
    </h1>
    <div>
        <?php if ($surveyId): ?>
            <a href="generate_survey_report_new.php?id=<?php echo $surveyId; ?>" class="btn btn-secondary me-2" target="_blank">
                <i class="fas fa-file-pdf me-2"></i>View Report
            </a>
        <?php endif; ?>
        <a href="survey_index.php" class="btn btn-outline-primary me-2">
            <i class="fas fa-home me-2"></i>Back to Home
        </a>
    </div>
</div>

<?php if ($surveyId && $survey): ?>
    <!-- Edit Mode Banner for Site Survey -->
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-edit fa-2x me-3"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1">
                    <i class="fas fa-pen-to-square me-2"></i>Editing Survey
                </h5>
                <p class="mb-0">
                    <strong>Survey ID:</strong> SUR-<?php echo str_pad($surveyId, 5, '0', STR_PAD_LEFT); ?> |
                    <strong>Project:</strong> <?php echo htmlspecialchars($survey['project_name'] ?? 'Untitled'); ?> |
                    <strong>Date:</strong> <?php echo date('Y-m-d', strtotime($survey['date'] ?? 'now')); ?>
                </p>
            </div>
            <div>
                <a href="generate_survey_report_new.php?id=<?php echo $surveyId; ?>" class="btn btn-sm btn-primary me-2" target="_blank">
                    <i class="fas fa-file-alt me-1"></i>View Report
                </a>
                <a href="site_survey.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-plus me-1"></i>New Survey
                </a>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">
            <i class="fas fa-clipboard-list me-2"></i>Survey Information Form
        </h2>
        <span class="badge bg-primary">Auto-saving</span>
    </div>
    <div class="card-body">
        <input type="hidden" name="survey_id" value="<?php echo $surveyId ? htmlspecialchars($surveyId) : ''; ?>">

        <!-- Match Commissioning: use buttons + switchTab() to toggle panes -->
        <script>
            window.switchTab = function(buttonElement) {
                const targetId = buttonElement.getAttribute('data-bs-target');
                document.querySelectorAll('.nav-link').forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                });
                buttonElement.classList.add('active');
                buttonElement.setAttribute('aria-selected', 'true');
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
                const pane = document.querySelector(targetId);
                if (pane) pane.classList.add('show', 'active');
                // Update Next/Create button label when switching tabs
                if (typeof updateNextButtonLabel === 'function') {
                    updateNextButtonLabel();
                }
                return false;
            };
        </script>

        <ul class="nav nav-tabs nav-fill mb-4" id="formTabs" role="tablist" style="display: flex !important;">
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link<?php echo !$surveyId ? ' active' : ''; ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="<?php echo !$surveyId ? 'true' : 'false'; ?>" onclick="return switchTab(this);">
                    <i class="fas fa-info-circle me-1"></i> General
                </button>
            </li>
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link<?php echo $surveyId ? ' active' : ''; ?>" id="site-tab" data-bs-toggle="tab" data-bs-target="#site" type="button" role="tab" aria-controls="site" aria-selected="<?php echo $surveyId ? 'true' : 'false'; ?>" onclick="return switchTab(this);">
                    <i class="fas fa-building me-1"></i> Installation Site
                </button>
            </li>
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link" id="shading-tab" data-bs-toggle="tab" data-bs-target="#shading" type="button" role="tab" aria-controls="shading" aria-selected="false" onclick="return switchTab(this);">
                    <i class="fas fa-cloud-sun-rain me-1"></i> Shading
                </button>
            </li>
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link" id="electrical-tab" data-bs-toggle="tab" data-bs-target="#electrical" type="button" role="tab" aria-controls="electrical" aria-selected="false" onclick="return switchTab(this);">
                    <i class="fas fa-bolt me-1"></i> Electrical
                </button>
            </li>
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link" id="communications-tab" data-bs-toggle="tab" data-bs-target="#communications" type="button" role="tab" aria-controls="communications" aria-selected="false" onclick="return switchTab(this);">
                    <i class="fas fa-wifi me-1"></i> Communications
                </button>
            </li>

            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link" id="checklist-tab" data-bs-toggle="tab" data-bs-target="#checklist" type="button" role="tab" aria-controls="checklist" aria-selected="false" onclick="return switchTab(this);">
                    <i class="fas fa-list-check me-1"></i> Checklist
                </button>
            </li>
            <li class="nav-item" role="presentation" style="display: inline-block !important;">
                <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false" onclick="return switchTab(this);">
                    <i class="fas fa-sticky-note me-1"></i> Notes
                </button>
            </li>
        </ul>

        <div class="tab-content mt-2">
            <!-- General Tab -->
            <div class="tab-pane fade<?php echo $surveyId ? '' : ' show active'; ?>" id="general">
                <h3 class="section-heading">Project Information</h3>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Project Name</label>
                        <input type="text" class="form-control" id="project_name"
                            value="<?php echo htmlspecialchars($survey['project_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Survey Date</label>
                        <input type="date" class="form-control" id="survey_date"
                            value="<?php echo htmlspecialchars(($survey['date'] ?? '') === '0000-00-00' ? '' : ($survey['date'] ?? '')); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" id="location"
                            value="<?php echo htmlspecialchars($survey['location'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">GPS Coordinates</label>
                        <input type="text" class="form-control" id="gps" placeholder="Lat, Long"
                            value="<?php echo htmlspecialchars($survey['gps'] ?? ''); ?>">
                    </div>
                </div>

                <h3 class="section-heading">Power Information</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Power to Install (kWp)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" class="form-control" id="power_to_install" step="0.01" min="0"
                            value="<?php echo htmlspecialchars($survey['power_to_install'] ?? '0.00'); ?>">
                        <small class="text-muted">Proposed system capacity</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Certified Power (kWp)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" class="form-control" id="certified_power" step="0.01" min="0"
                            value="<?php echo htmlspecialchars($survey['certified_power'] ?? '0.00'); ?>">
                    </div>
                </div>

                <h3 class="section-heading">Responsible Party</h3>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="site_survey_responsible_id" class="form-label">Site Survey Responsible</label>
                        <div class="input-group">
                            <select class="form-select" id="site_survey_responsible_id" name="site_survey_responsible_id"
                                <?php if (!empty($survey['site_survey_responsible_id'])): ?>
                                data-selected="<?php echo htmlspecialchars($survey['site_survey_responsible_id']); ?>"
                                <?php endif; ?>>
                                <option value="">Select Responsible...</option>
                            </select>
                            <button class="btn btn-outline-success" type="button" id="add-site-survey-responsible-btn" title="Add New Site Survey Responsible">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Accompanied By (On-site)</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-7">
                                <input type="text" class="form-control" id="accompanied_by_name"
                                    placeholder="Name"
                                    value="<?php echo htmlspecialchars($survey['accompanied_by_name'] ?? ''); ?>">
                            </div>
                            <div class="col-12 col-md-5">
                                <input type="text" class="form-control" id="accompanied_by_phone"
                                    placeholder="Phone"
                                    value="<?php echo htmlspecialchars($survey['accompanied_by_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Installation Site Tab -->
            <div class="tab-pane fade<?php echo $surveyId ? ' show active' : ''; ?>" id="site">
                <?php if ($surveyId): ?>
                    <script>
                        console.log('Site tab is active for survey_id: <?php echo $surveyId; ?>');
                    </script>
                <?php endif; ?>
                <h3 class="section-heading">Installation Site</h3>

                <!-- Map: Interactive location picker, satellite toggle, area measurement -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Map</strong>
                                <small class="text-muted">Click to set GPS, draw area or measure azimuth.</small>
                            </div>
                            <div class="btn-group" role="group" aria-label="map-controls">
                                <button class="btn btn-sm btn-outline-secondary" id="survey_map_toggle_sat">Satellite</button>
                                <button class="btn btn-sm btn-outline-secondary" id="survey_map_measure_area">Measure Area</button>
                                <button class="btn btn-sm btn-outline-danger" id="survey_map_clear">Clear</button>
                            </div>
                        </div>

                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />

                        <style>
                            #survey_map {
                                width: 100% !important;
                                height: 420px !important;
                                min-height: 420px !important;
                                border: 1px solid #ddd !important;
                                border-radius: 6px !important;
                                display: block !important;
                                visibility: visible !important;
                            }

                            .tab-pane:not(.active) #survey_map {
                                display: none !important;
                            }

                            .tab-pane.active #survey_map {
                                display: block !important;
                            }
                        </style>

                        <div id="survey_map" style="width:100%;height:420px;border:1px solid #ddd;border-radius:6px;min-height:420px;"></div>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <label class="form-label">Selected GPS</label>
                                <input type="text" id="survey_map_gps" class="form-control" placeholder="Lat, Lon" value="<?php echo htmlspecialchars($survey['gps'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Area (m²)</label>
                                <input type="text" id="survey_map_area_m2" class="form-control" value="<?php echo htmlspecialchars($survey['map_area_m2'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Azimuth (°)</label>
                                <input type="text" id="survey_map_azimuth_deg" class="form-control" value="<?php echo htmlspecialchars($survey['map_azimuth_deg'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        <!-- Hidden field to pass existing polygon coordinates to JavaScript -->
                        <input type="hidden" id="existing_polygon_coords" value="<?php echo htmlspecialchars($survey['map_polygon_coords'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Building Details Input -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Building Name</label>
                                <input type="text" class="form-control" id="building_name_input" placeholder="Type a building name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Parapet Height (m)</label>
                                <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" class="form-control" id="building_parapet_input" step="0.1" min="0" placeholder="e.g., 0.8">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mounting Location Type</label>
                                <select class="form-select" id="building_mount_input">
                                    <option value="">Select...</option>
                                    <option value="Pitched Roof">Pitched Roof</option>
                                    <option value="Flat Roof">Flat Roof</option>
                                    <option value="Ground/Terrain">Ground/Terrain</option>
                                    <option value="Facade">Facade</option>
                                    <option value="Self-supporting Canopy">Self-supporting Canopy</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Roof Type</label>
                                <select class="form-select" id="building_roof_input">
                                    <option value="">Select...</option>
                                    <option value="Ceramic Tile - Lusa">Ceramic Tile - Lusa</option>
                                    <option value="Ceramic Tile - Marseille">Ceramic Tile - Marseille</option>
                                    <option value="Portuguese Half-round (Meia Cana)">Portuguese Half-round (Meia Cana)</option>
                                    <option value="Sandwich Panel">Sandwich Panel</option>
                                    <option value="Metal Sheet">Metal Sheet</option>
                                    <option value="Ground/Terrain">Ground/Terrain</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Support Structure</label>
                                <select class="form-select" id="building_support_input">
                                    <option value="">Select...</option>
                                    <option value="Concrete Beams">Concrete Beams</option>
                                    <option value="Metal Beams">Metal Beams</option>
                                    <option value="Self-supporting">Self-supporting</option>
                                    <option value="Wooden Beams">Wooden Beams</option>
                                    <option value="Concrete Slab">Concrete Slab</option>
                                    <option value="Ground/Terrain">Ground/Terrain</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-outline-primary" type="button" id="add_building_btn">
                                    <i class="fas fa-plus"></i> Add Building
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rendered Building Cards -->
                <div id="buildings_list"></div>
                <input type="hidden" id="building_details" value='<?php echo htmlspecialchars(json_encode($buildingNames)); ?>'>

                <!-- Site Access and Ladder Feasibility -->
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Is there access to the roof?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="roof_access_available" id="roof_access_yes" value="YES" <?php echo isset($survey['roof_access_available']) && (int)$survey['roof_access_available'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="roof_access_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="roof_access_available" id="roof_access_no" value="NO" <?php echo isset($survey['roof_access_available']) && (int)$survey['roof_access_available'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="roof_access_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Is it feasible to install a permanent ladder?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permanent_ladder_feasible" id="ladder_feasible_yes" value="YES" <?php echo isset($survey['permanent_ladder_feasible']) && (int)$survey['permanent_ladder_feasible'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ladder_feasible_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permanent_ladder_feasible" id="ladder_feasible_no" value="NO" <?php echo isset($survey['permanent_ladder_feasible']) && (int)$survey['permanent_ladder_feasible'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ladder_feasible_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Installation Site Observations</label>
                    <textarea class="form-control" id="installation_site_notes" name="installation_site_notes" rows="6" placeholder="Write here observations about the installation site..."><?php echo htmlspecialchars($survey['installation_site_notes'] ?? ''); ?></textarea>
                    <small class="text-muted">Use this area for general site notes (access, risks, structural condition, etc.).</small>
                </div>
                <hr class="my-4">
                <h3 class="section-heading">Roof Segments & Assessment</h3>
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Building</label>
                        <select class="form-select" id="roof_building_select"></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Identification</label>
                        <input type="text" class="form-control" id="roof_identification" placeholder="Roof 1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Angle / Pitch (°)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" min="0" max="90" class="form-control" id="roof_angle" placeholder="e.g., 15">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Orientation (° Azimuth)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*" autocomplete="off" step="1" min="0" max="360" class="form-control" id="roof_orientation_deg" placeholder="0-360">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Roof Condition (1–5)</label>
                        <div class="d-flex gap-1" id="roof_condition_buttons">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" class="btn-check" name="roof_condition_single" id="roof_cond_<?php echo $i; ?>" value="<?php echo $i; ?>" autocomplete="off">
                                <label class="btn btn-outline-secondary" style="min-width:38px" for="roof_cond_<?php echo $i; ?>"><?php echo $i; ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Structure Visual</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_visual_single" id="sv_ok" value="OK">
                                <label class="form-check-label" for="sv_ok">OK</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_visual_single" id="sv_nok" value="NOT OK">
                                <label class="form-check-label" for="sv_nok">NOT OK</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Weight Load</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_weight_load_single" id="sw_ok" value="OK">
                                <label class="form-check-label" for="sw_ok">OK</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_weight_load_single" id="sw_nok" value="NOT OK">
                                <label class="form-check-label" for="sw_nok">NOT OK</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Wind Coverage</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_wind_coverage_single" id="wc_ok" value="OK">
                                <label class="form-check-label" for="wc_ok">OK</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="structure_wind_coverage_single" id="wc_nok" value="NOT OK">
                                <label class="form-check-label" for="wc_nok">NOT OK</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expert Assessment?</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="requires_expert_single" id="exp_yes" value="YES">
                                <label class="form-check-label" for="exp_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="requires_expert_single" id="exp_no" value="NO">
                                <label class="form-check-label" for="exp_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="button" class="btn btn-outline-primary" id="add_roof_btn"><i class="fas fa-plus"></i> Add Roof</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered" id="roofs_table">
                        <thead class="table-light">
                            <tr>
                                <th>Building</th>
                                <th>Identification</th>
                                <th>Angle (°)</th>
                                <th>Azimuth (°)</th>
                                <th>Cond.</th>
                                <th>Visual</th>
                                <th>Weight</th>
                                <th>Wind</th>
                                <th>Expert?</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="roofs_tbody"></tbody>
                    </table>
                </div>
                <input type="hidden" id="roof_details" value='<?php echo htmlspecialchars(empty($roofDetailsMap) ? json_encode(new stdClass()) : json_encode($roofDetailsMap)); ?>'>
            </div>

            <!-- Power Tab -->


            <!-- Shading Tab -->
            <div class="tab-pane fade" id="shading">
                <h3 class="section-heading">Shading Assessment</h3>
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Building</label>
                        <select class="form-select" id="shading_building_select"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shading Status</label>
                        <div class="d-flex gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shading_status" id="shade_none" value="NONE">
                                <label class="form-check-label" for="shade_none">No Shading</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shading_status" id="shade_partial" value="PARTIAL">
                                <label class="form-check-label" for="shade_partial">Partial</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shading_status" id="shade_heavy" value="HEAVY">
                                <label class="form-check-label" for="shade_heavy">Heavy</label>
                            </div>
                        </div>
                        <div id="shading_viability" class="mt-2"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shading Notes (building)</label>
                        <input type="text" class="form-control" id="shading_notes" placeholder="e.g., Seasonal trees on south side">
                    </div>
                </div>

                <h5 class="mt-3">Shading Objects</h5>
                <div class="row g-3 align-items-end mb-2">
                    <div class="col-md-3">
                        <label class="form-label">Object Type</label>
                        <select class="form-select" id="shade_object_type">
                            <option value="">Select...</option>
                            <option value="Tree">Tree</option>
                            <option value="Chimney">Chimney</option>
                            <option value="Nearby Building">Nearby Building</option>
                            <option value="Parapet">Parapet</option>
                            <option value="Antenna/Mast">Antenna/Mast</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cause / Description</label>
                        <input type="text" class="form-control" id="shade_cause" placeholder="What causes the shadow?">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Height (m)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" min="0" class="form-control" id="shade_height">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*" autocomplete="off" min="1" step="1" class="form-control" id="shade_quantity" value="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" id="shade_notes" placeholder="Optional">
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="button" class="btn btn-outline-primary" id="add_shade_obj_btn"><i class="fas fa-plus"></i> Add</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered" id="shading_table">
                        <thead class="table-light">
                            <tr>
                                <th>Building</th>
                                <th>Type</th>
                                <th>Cause</th>
                                <th>Height (m)</th>
                                <th>Qty</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="shading_tbody"></tbody>
                    </table>
                </div>
                <input type="hidden" id="shading_details" value='<?php echo htmlspecialchars(empty($shadingMap) ? json_encode(new stdClass()) : json_encode($shadingMap)); ?>'>
            </div>
            <!-- Electrical Injection Point Tab -->
            <div class="tab-pane fade" id="electrical">
                <h3 class="section-heading">Point of Injection / Electrical Installation</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Injection Point Location</label>
                        <select class="form-select" id="injection_point_type">
                            <option value="">Select...</option>
                            <option value="Quadro Auxiliar PT" <?php echo isset($survey['injection_point_type']) && $survey['injection_point_type'] === 'Quadro Auxiliar PT' ? 'selected' : ''; ?>>PT Auxiliary Panel (MV/LV Substation)</option>
                            <option value="Quadro Geral" <?php echo isset($survey['injection_point_type']) && $survey['injection_point_type'] === 'Quadro Geral' ? 'selected' : ''; ?>>Main Switchboard</option>
                            <option value="Quadro Parcial" <?php echo isset($survey['injection_point_type']) && $survey['injection_point_type'] === 'Quadro Parcial' ? 'selected' : ''; ?>>Sub-board</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Circuit Type</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="circuit_type" id="circuit_trifasico" value="Trifasico" <?php echo isset($survey['circuit_type']) && $survey['circuit_type'] === 'Trifasico' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="circuit_trifasico">Three-phase</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="circuit_type" id="circuit_monofasico" value="Monofasico" <?php echo isset($survey['circuit_type']) && $survey['circuit_type'] === 'Monofasico' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="circuit_monofasico">Single-phase</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Inverter Location</label>
                        <input type="text" class="form-control" id="inverter_location" placeholder="Location description" value="<?php echo htmlspecialchars($survey['inverter_location'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PV Protection Board Location</label>
                        <input type="text" class="form-control" id="pv_protection_board_location" placeholder="Location description" value="<?php echo htmlspecialchars($survey['pv_protection_board_location'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cable Distance PV Board → Point of Injection (m)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" min="0" class="form-control" id="pv_board_to_injection_distance_m" value="<?php echo htmlspecialchars($survey['pv_board_to_injection_distance_m'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Space available for isolator/breaker?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="injection_has_space_for_switch" id="inj_switch_yes" value="YES" <?php echo isset($survey['injection_has_space_for_switch']) && (int)$survey['injection_has_space_for_switch'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="inj_switch_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="injection_has_space_for_switch" id="inj_switch_no" value="NO" <?php echo isset($survey['injection_has_space_for_switch']) && (int)$survey['injection_has_space_for_switch'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="inj_switch_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Busbar space available for connection?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="injection_has_busbar_space" id="inj_busbar_yes" value="YES" <?php echo isset($survey['injection_has_busbar_space']) && (int)$survey['injection_has_busbar_space'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="inj_busbar_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="injection_has_busbar_space" id="inj_busbar_no" value="NO" <?php echo isset($survey['injection_has_busbar_space']) && (int)$survey['injection_has_busbar_space'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="inj_busbar_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <h4 class="mt-2">Switchboard Details</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">External Cable Gauge → Main Switchboard</label>
                        <input type="text" class="form-control" id="panel_cable_exterior_to_main_gauge" placeholder="e.g., 4x95mm²" value="<?php echo htmlspecialchars($survey['panel_cable_exterior_to_main_gauge'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Switchboard Brand / Model</label>
                        <input type="text" class="form-control" id="panel_brand_model" placeholder="e.g., Schneider XYZ" value="<?php echo htmlspecialchars($survey['panel_brand_model'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circuit Breaker Brand / Model</label>
                        <input type="text" class="form-control" id="breaker_brand_model" placeholder="e.g., ABB Tmax" value="<?php echo htmlspecialchars($survey['breaker_brand_model'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circuit Breaker Rated Current (A)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="1" min="0" class="form-control" id="breaker_rated_current_a" value="<?php echo htmlspecialchars($survey['breaker_rated_current_a'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circuit Breaker Short‑circuit Current (kA)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" min="0" class="form-control" id="breaker_short_circuit_current_ka" value="<?php echo htmlspecialchars($survey['breaker_short_circuit_current_ka'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Residual Current (mA)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*" autocomplete="off" step="1" min="0" class="form-control" id="residual_current_ma" value="<?php echo htmlspecialchars($survey['residual_current_ma'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Earth Measurement (Ω)</label>
                        <input type="number" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" step="0.1" min="0" class="form-control" id="earth_measurement_ohms" value="<?php echo htmlspecialchars($survey['earth_measurement_ohms'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">E‑Redes Bidirectional Meter?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_bidirectional_meter" id="bid_meter_yes" value="YES" <?php echo isset($survey['is_bidirectional_meter']) && (int)$survey['is_bidirectional_meter'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bid_meter_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_bidirectional_meter" id="bid_meter_no" value="NO" <?php echo isset($survey['is_bidirectional_meter']) && (int)$survey['is_bidirectional_meter'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bid_meter_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <h4 class="mt-2">Generator</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Generator present?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_exists" id="generator_yes" value="YES" <?php echo isset($survey['generator_exists']) && (int)$survey['generator_exists'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="generator_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_exists" id="generator_no" value="NO" <?php echo isset($survey['generator_exists']) && (int)$survey['generator_exists'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="generator_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mode of Operation</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_mode" id="gen_mode_auto" value="Automatico" <?php echo isset($survey['generator_mode']) && $survey['generator_mode'] === 'Automatico' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="gen_mode_auto">Automatic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_mode" id="gen_mode_manual" value="Manual" <?php echo isset($survey['generator_mode']) && $survey['generator_mode'] === 'Manual' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="gen_mode_manual">Manual</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Feeds</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_scope" id="gen_scope_total" value="Toda Instalacao" <?php echo isset($survey['generator_scope']) && $survey['generator_scope'] === 'Toda Instalacao' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="gen_scope_total">Entire Installation</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="generator_scope" id="gen_scope_emergencia" value="Quadro Emergencia" <?php echo isset($survey['generator_scope']) && $survey['generator_scope'] === 'Quadro Emergencia' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="gen_scope_emergencia">Emergency Board</label>
                            </div>
                        </div>
                    </div>
                </div>
                <small class="text-muted d-block mt-3">Record here all relevant details for electrical connection, switchboard and generator.</small>
            </div>
            <!-- Communications Tab -->
            <div class="tab-pane fade" id="communications">
                <h3 class="section-heading">Communications</h3>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Is there Wi‑Fi near the PV equipment?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_wifi_near_pv" id="comm_wifi_yes" value="YES" <?php echo isset($survey['comm_wifi_near_pv']) && (int)$survey['comm_wifi_near_pv'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_wifi_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_wifi_near_pv" id="comm_wifi_no" value="NO" <?php echo isset($survey['comm_wifi_near_pv']) && (int)$survey['comm_wifi_near_pv'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_wifi_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Is there Ethernet near the PV equipment?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_ethernet_near_pv" id="comm_eth_yes" value="YES" <?php echo isset($survey['comm_ethernet_near_pv']) && (int)$survey['comm_ethernet_near_pv'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_eth_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_ethernet_near_pv" id="comm_eth_no" value="NO" <?php echo isset($survey['comm_ethernet_near_pv']) && (int)$survey['comm_ethernet_near_pv'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_eth_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Is UTP cabling required?</label>
                        <div class="d-flex gap-3 mt-1 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_utp_requirement" id="comm_utp_sim" value="Sim" <?php echo isset($survey['comm_utp_requirement']) && $survey['comm_utp_requirement'] === 'Sim' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_utp_sim">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_utp_requirement" id="comm_utp_nao" value="Nao" <?php echo isset($survey['comm_utp_requirement']) && $survey['comm_utp_requirement'] === 'Nao' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_utp_nao">No</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_utp_requirement" id="comm_utp_inv" value="Instalacao Inviavel" <?php echo isset($survey['comm_utp_requirement']) && $survey['comm_utp_requirement'] === 'Instalacao Inviavel' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_utp_inv">Installation not feasible</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UTP cable length (m)</label>
                        <input type="number" step="0.1" min="0" class="form-control" id="comm_utp_length_m" value="<?php echo htmlspecialchars($survey['comm_utp_length_m'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Client willing to open router ports?</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_router_port_open_available" id="comm_port_open_yes" value="YES" <?php echo isset($survey['comm_router_port_open_available']) && (int)$survey['comm_router_port_open_available'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_port_open_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comm_router_port_open_available" id="comm_port_open_no" value="NO" <?php echo isset($survey['comm_router_port_open_available']) && (int)$survey['comm_router_port_open_available'] === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="comm_port_open_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port number</label>
                        <input type="number" step="1" min="0" class="form-control" id="comm_router_port_number" value="<?php echo htmlspecialchars($survey['comm_router_port_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mobile network coverage (1–5)</label>
                        <select class="form-select" id="comm_mobile_coverage_level">
                            <option value=""></option>
                            <?php
                            $cov = $survey['comm_mobile_coverage_level'] ?? '';
                            for ($i = 1; $i <= 5; $i++) {
                                $sel = ((string)$cov === (string)$i) ? 'selected' : '';
                                echo "<option value=\"$i\" $sel>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <!-- Checklist Tab -->
            <div class="tab-pane fade" id="checklist">
                <h3 class="section-heading">Mandatory Photos</h3>
                <?php
                $photoDefaults = [
                    ['key' => 'photo_permanent_ladder_location', 'label' => 'Permanent ladder location'],
                    ['key' => 'photo_building', 'label' => 'Building'],
                    ['key' => 'photo_roof', 'label' => 'Roof'],
                    ['key' => 'photo_support_structures', 'label' => 'Support structures'],
                    ['key' => 'photo_roof_type', 'label' => 'Roof type'],
                    ['key' => 'photo_shading', 'label' => 'Shading'],
                    ['key' => 'photo_pv_switchboard_location', 'label' => 'PV Switchboard location'],
                    ['key' => 'photo_main_switchboard', 'label' => 'Main Switchboard'],
                    ['key' => 'photo_current_differential_protection_ma', 'label' => 'Current differential protection (mA)'],
                    ['key' => 'photo_circuit_breaker_scc', 'label' => 'Circuit Breaker (Short circuit breaker)'],
                    ['key' => 'photo_ground_resistance', 'label' => 'Ground resistance'],
                    ['key' => 'photo_energy_box', 'label' => 'Energy Box'],
                    ['key' => 'photo_generator_switchboard', 'label' => 'Generator (Generator switchboard)'],
                ];
                ?>
                <div class="row g-2" id="photo-checklist">
                    <?php foreach ($photoDefaults as $p):
                        $checked = in_array($p['key'], $photoChecklistChecked) ? 'checked' : '';
                    ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input photo-check" type="checkbox" id="<?php echo htmlspecialchars($p['key']); ?>" data-key="<?php echo htmlspecialchars($p['key']); ?>" data-label="<?php echo htmlspecialchars($p['label']); ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="<?php echo htmlspecialchars($p['key']); ?>"><?php echo htmlspecialchars($p['label']); ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted d-block mt-2">Tick each photo once it’s captured.</small>
            </div>

            <!-- Notes Tab -->
            <div class="tab-pane fade" id="notes">
                <h3 class="section-heading">Additional Notes</h3>

                <div class="mb-3">
                    <label class="form-label">Survey Findings and Observations</label>
                    <textarea class="form-control" id="survey_notes" name="survey_notes" rows="10" placeholder="Document any findings..."><?php echo htmlspecialchars($survey['survey_notes'] ?? ''); ?></textarea>
                    <small class="text-muted">These findings appear on the generated report.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Challenges or Constraints</label>
                    <textarea class="form-control" id="challenges" name="challenges" rows="8" placeholder="Document any challenges..."><?php echo htmlspecialchars($survey['challenges'] ?? ''); ?></textarea>
                    <small class="text-muted">Document constraints, mitigations, or related notes.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">OneDrive / Photos Repository Link</label>
                    <input type="url" class="form-control" id="photos_link" name="photos_link" placeholder="https://..." value="<?php echo htmlspecialchars($photosLink ?? ''); ?>">
                    <small class="text-muted">Optional link to SharePoint / photos repository. Appears on report.</small>
                </div>
            </div>
        </div>
        <!-- Next/Report button aligned bottom-right -->
        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-primary" type="button" id="nextTabBtn">Next</button>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="button" id="saveSurveyBtn">
                <i class="fas fa-save"></i> Save Survey
            </button>
            <!-- Generate Report button removed (redundant with Header 'View Report') -->
        </div>
    </div>
</div>

<script>
    let surveyId = <?php echo $surveyId ? $surveyId : 'null'; ?>;

    // Building details cards management
    function getBuildingDetails() {
        try {
            return JSON.parse(document.getElementById('building_details').value || '[]');
        } catch {
            return [];
        }
    }

    function setBuildingDetails(list) {
        document.getElementById('building_details').value = JSON.stringify(list);
        renderBuildingCards();
        refreshRoofBuildingOptions();
        purgeRoofsForMissingBuildings();
        renderRoofsTable();
        refreshShadingBuildingOptions();
        purgeShadingForMissingBuildings();
        renderShadingTable();
        refreshShadingStatusUI();
    }

    function addBuildingFromInputs(editIndex = null) {
        const name = (document.getElementById('building_name_input')?.value || '').trim();
        const parapet = document.getElementById('building_parapet_input')?.value || '';
        const mount = document.getElementById('building_mount_input')?.value || '';
        const roof = document.getElementById('building_roof_input')?.value || '';
        const support = document.getElementById('building_support_input')?.value || '';
        if (!name) {
            return; // Skip validation in dev mode
        }
        const list = getBuildingDetails();
        const obj = {
            name,
            parapet_height_m: parapet,
            mount_location_type: mount,
            roof_type: roof,
            support_structure: support
        };
        if (editIndex !== null && editIndex >= 0 && editIndex < list.length) {
            list[editIndex] = obj;
        } else {
            list.push(obj);
        }
        setBuildingDetails(list);
        // Clear inputs
        document.getElementById('building_name_input').value = '';
        document.getElementById('building_parapet_input').value = '';
        document.getElementById('building_mount_input').value = '';
        document.getElementById('building_roof_input').value = '';
        document.getElementById('building_support_input').value = '';
        // Reset any edit index flag
        delete document.getElementById('add_building_btn').dataset.editIndex;
        document.getElementById('add_building_btn').innerHTML = '<i class="fas fa-plus"></i> Add Building';
        document.getElementById('add_building_btn').classList.remove('btn-success');
        document.getElementById('add_building_btn').classList.add('btn-outline-primary');
    }

    function onEditBuilding(index) {
        const list = getBuildingDetails();
        const b = list[index];
        if (!b) return;
        document.getElementById('building_name_input').value = b.name || '';
        document.getElementById('building_parapet_input').value = b.parapet_height_m || '';
        document.getElementById('building_mount_input').value = b.mount_location_type || '';
        document.getElementById('building_roof_input').value = b.roof_type || '';
        document.getElementById('building_support_input').value = b.support_structure || '';
        const addBtn = document.getElementById('add_building_btn');
        addBtn.dataset.editIndex = String(index);
        addBtn.innerHTML = '<i class="fas fa-check"></i> Update Building';
        addBtn.classList.remove('btn-outline-primary');
        addBtn.classList.add('btn-success');
    }

    function onRemoveBuilding(index) {
        const list = getBuildingDetails();
        list.splice(index, 1);
        setBuildingDetails(list);
    }

    function renderBuildingCards() {
        const wrap = document.getElementById('buildings_list');
        if (!wrap) return;
        const list = getBuildingDetails();
        wrap.innerHTML = '';
        if (!list.length) {
            wrap.innerHTML = '<div class="text-muted">No buildings added yet.</div>';
            return;
        }
        list.forEach((b, idx) => {
            const el = document.createElement('div');
            el.className = 'card mb-2';
            el.innerHTML = `
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Building Name: ${b.name || ''}</strong>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-action="edit" data-index="${idx}"><i class="fas fa-pen"></i> Edit</button>
                        <button type="button" class="btn btn-outline-danger" data-action="remove" data-index="${idx}"><i class="fas fa-trash"></i> Remove</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><small class="text-muted">Parapet</small><div>${b.parapet_height_m || '-'}</div></div>
                        <div class="col-md-3"><small class="text-muted">Mounting</small><div>${b.mount_location_type || '-'}</div></div>
                        <div class="col-md-3"><small class="text-muted">Roof</small><div>${b.roof_type || '-'}</div></div>
                        <div class="col-md-3"><small class="text-muted">Support</small><div>${b.support_structure || '-'}</div></div>
                    </div>
                </div>
            `;
            el.querySelector('[data-action="edit"]').addEventListener('click', () => onEditBuilding(idx));
            el.querySelector('[data-action="remove"]').addEventListener('click', () => onRemoveBuilding(idx));
            wrap.appendChild(el);
        });
    }

    // Roofs: map of building_name -> array of {identification, angle_pitch, orientation}
    function getRoofDetails() {
        try {
            const raw = document.getElementById('roof_details').value || '{}';
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) return {}; // normalize legacy [] -> {}
            return parsed || {};
        } catch {
            return {};
        }
    }

    function setRoofDetails(obj) {
        document.getElementById('roof_details').value = JSON.stringify(obj);
        renderRoofsTable();
    }

    function refreshRoofBuildingOptions() {
        const sel = document.getElementById('roof_building_select');
        if (!sel) return;
        const list = getBuildingDetails();
        const prev = sel.value;
        sel.innerHTML = '';
        list.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.name;
            opt.textContent = b.name;
            sel.appendChild(opt);
        });
        if (list.length && !sel.value) sel.value = list[0].name;
        if (prev && list.some(b => b.name === prev)) sel.value = prev;
    }

    function purgeRoofsForMissingBuildings() {
        const roofs = getRoofDetails();
        const names = new Set(getBuildingDetails().map(b => b.name));
        Object.keys(roofs).forEach(k => {
            if (!names.has(k)) delete roofs[k];
        });
        setRoofDetails(roofs);
    }

    // Shading helpers
    function getShadingDetails() {
        try {
            const raw = document.getElementById('shading_details').value || '{}';
            const parsed = JSON.parse(raw);
            // normalize: if an array was stored (legacy/empty), return an empty object
            if (Array.isArray(parsed)) return {};
            if (parsed === null || typeof parsed !== 'object') return {};
            return parsed || {};
        } catch {
            return {};
        }
    }

    function setShadingDetails(obj) {
        console.log('[Shading] setShadingDetails called with obj:', obj);
        // Ensure we store a plain object. If an array with named props was passed,
        // JSON.stringify will not include non-index properties — convert to object.
        let out = {};
        if (Array.isArray(obj)) {
            Object.keys(obj).forEach(k => {
                out[k] = obj[k];
            });
        } else if (obj && typeof obj === 'object') {
            out = obj;
        }
        document.getElementById('shading_details').value = JSON.stringify(out);
        console.log('[Shading] Hidden input set to:', document.getElementById('shading_details').value);
        renderShadingTable();
        refreshShadingStatusUI();
    }

    function refreshShadingBuildingOptions() {
        const sel = document.getElementById('shading_building_select');
        if (!sel) return;
        const list = getBuildingDetails();
        const prev = sel.value;
        sel.innerHTML = '';
        list.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.name;
            opt.textContent = b.name;
            sel.appendChild(opt);
        });
        if (list.length && !sel.value) sel.value = list[0].name;
        if (prev && list.some(b => b.name === prev)) sel.value = prev;
    }

    function purgeShadingForMissingBuildings() {
        const map = getShadingDetails();
        const names = new Set(getBuildingDetails().map(b => b.name));
        Object.keys(map).forEach(k => {
            if (!names.has(k)) delete map[k];
        });
        setShadingDetails(map);
    }

    function enableShadingControls() {
        const ids = ['shade_object_type', 'shade_cause', 'shade_height', 'shade_quantity', 'shade_notes', 'add_shade_obj_btn', 'shading_building_select', 'shade_none', 'shade_partial', 'shade_heavy', 'shading_notes'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.disabled = false;
                el.readOnly = false;
                console.log('[Shading] Enabled control:', id, 'disabled:', el.disabled, 'readonly:', el.readOnly);
            } else {
                console.warn('[Shading] Control not found:', id);
            }
        });
        console.log('[Shading] Controls enabled');
    }

    function shadingStatusFor(building) {
        const map = getShadingDetails();
        const status = (map[building]?.status) || '';
        console.log('[Shading] shadingStatusFor for building:', building, 'map:', map, 'status:', status);
        return status;
    }

    function refreshShadingStatusUI() {
        const b = document.getElementById('shading_building_select')?.value || '';
        const status = shadingStatusFor(b);
        console.log('[Shading] refreshShadingStatusUI for building:', b, 'status:', status);
        const viabEl = document.getElementById('shading_viability');
        // set radios
        ['NONE', 'PARTIAL', 'HEAVY'].forEach(v => {
            const el = document.getElementById('shade_' + v.toLowerCase());
            if (el) {
                const shouldCheck = (status === v);
                el.checked = shouldCheck;
                el.disabled = !b; // disable if no building selected
                console.log('[Shading] Setting', el.id, 'checked:', shouldCheck, 'disabled:', el.disabled);
            }
        });
        const notesEl = document.getElementById('shading_notes');
        if (notesEl) {
            notesEl.disabled = !b;
            notesEl.readOnly = !b;
        }
        const map = getShadingDetails();
        const notesVal = map[b]?.notes || '';
        if (notesEl) notesEl.value = notesVal;
        console.log('[Shading] Set notes to:', notesVal, 'disabled:', notesEl?.disabled);
        if (viabEl) {
            if (status === 'HEAVY') {
                viabEl.innerHTML = '<span class="badge bg-danger">Installation not viable due to heavy shading</span>';
            } else if (status === 'PARTIAL') {
                viabEl.innerHTML = '<span class="badge bg-warning text-dark">Partial shading</span>';
            } else if (status === 'NONE') {
                viabEl.innerHTML = '<span class="badge bg-success">No significant shading</span>';
            } else {
                viabEl.innerHTML = '';
            }
        }
    }

    function onChangeShadingStatus(value) {
        console.log('[Shading] onChangeShadingStatus called with value:', value);
        const b = document.getElementById('shading_building_select')?.value || '';
        console.log('[Shading] Building selected:', b);
        if (!b) {
            console.warn('[Shading] No building selected, ignoring status change');
            return;
        }
        const map = getShadingDetails();
        console.log('[Shading] Map before update:', map);
        map[b] = map[b] || {
            status: '',
            viable: null,
            notes: '',
            objects: []
        };
        map[b].status = value;
        map[b].viable = (value === 'HEAVY') ? 0 : 1;
        console.log('[Shading] Map after update:', map);
        setShadingDetails(map);
        console.log('[Shading] Status updated to:', value, 'for building:', b);
    }

    function onChangeShadingNotes() {
        const b = document.getElementById('shading_building_select')?.value || '';
        const txt = document.getElementById('shading_notes')?.value || '';
        if (!b) return;
        const map = getShadingDetails();
        map[b] = map[b] || {
            status: '',
            viable: null,
            notes: '',
            objects: []
        };
        map[b].notes = txt;
        setShadingDetails(map);
    }

    function addShadingObject(editIndex = null) {
        console.log('[Shading] addShadingObject called, editIndex=', editIndex);
        try {
            const b = document.getElementById('shading_building_select')?.value || '';
            const type = document.getElementById('shade_object_type')?.value || '';
            const cause = document.getElementById('shade_cause')?.value || '';
            const h = document.getElementById('shade_height')?.value || '';
            const qty = document.getElementById('shade_quantity')?.value || '';
            const notes = document.getElementById('shade_notes')?.value || '';
            if (!b) {
                alert('Select a building');
                return;
            }
            if (!type && !cause) {
                alert('Provide object type or cause');
                return;
            }
            const map = getShadingDetails();
            map[b] = map[b] || {
                status: '',
                viable: null,
                notes: '',
                objects: []
            };
            const row = {
                object_type: type || null,
                cause: cause || null,
                height_m: h !== '' ? h : null,
                quantity: qty !== '' ? qty : null,
                notes: notes || null
            };
            if (editIndex !== null && editIndex >= 0 && editIndex < map[b].objects.length) {
                map[b].objects[editIndex] = row;
            } else {
                map[b].objects.push(row);
            }
            setShadingDetails(map);
            // reset inputs
            document.getElementById('shade_object_type').value = '';
            document.getElementById('shade_cause').value = '';
            document.getElementById('shade_height').value = '';
            document.getElementById('shade_quantity').value = '1';
            document.getElementById('shade_notes').value = '';
            const btn = document.getElementById('add_shade_obj_btn');
            delete btn.dataset.editIndex;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = '<i class="fas fa-plus"></i> Add';
        } catch (e) {
            console.error('[Shading] addShadingObject error', e);
            alert('Error adding shading object: ' + e.message);
        }
    }

    function editShadingObject(index) {
        const b = document.getElementById('shading_building_select')?.value || '';
        const map = getShadingDetails();
        if (!b || !map[b] || !map[b].objects[index]) return;
        const r = map[b].objects[index];
        document.getElementById('shade_object_type').value = r.object_type || '';
        document.getElementById('shade_cause').value = r.cause || '';
        document.getElementById('shade_height').value = r.height_m || '';
        document.getElementById('shade_quantity').value = r.quantity || '1';
        document.getElementById('shade_notes').value = r.notes || '';
        const btn = document.getElementById('add_shade_obj_btn');
        btn.dataset.editIndex = String(index);
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="fas fa-check"></i> Update';
    }

    function removeShadingObject(index) {
        const b = document.getElementById('shading_building_select')?.value || '';
        const map = getShadingDetails();
        if (!b || !map[b]) return;
        map[b].objects.splice(index, 1);
        setShadingDetails(map);
    }

    function renderShadingTable() {
        const tbody = document.getElementById('shading_tbody');
        if (!tbody) return;
        console.log('[Shading] renderShadingTable called');
        const selB = document.getElementById('shading_building_select')?.value || '';
        const map = getShadingDetails();
        tbody.innerHTML = '';
        Object.keys(map).forEach(b => {
            if (selB && b !== selB) return;
            (map[b].objects || []).forEach((r, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${b}</td>
                    <td>${r.object_type || ''}</td>
                    <td>${r.cause || ''}</td>
                    <td>${r.height_m || ''}</td>
                    <td>${r.quantity || ''}</td>
                    <td>${r.notes || ''}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-action="edit" data-index="${idx}"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" data-index="${idx}"><i class="fas fa-trash"></i></button>
                    </td>`;
                tbody.appendChild(tr);
            });
        });
        tbody.querySelectorAll('[data-action="edit"]').forEach(btn => btn.addEventListener('click', (e) => editShadingObject(parseInt(e.currentTarget.getAttribute('data-index'), 10))));
        tbody.querySelectorAll('[data-action="remove"]').forEach(btn => btn.addEventListener('click', (e) => removeShadingObject(parseInt(e.currentTarget.getAttribute('data-index'), 10))));
    }

    function addRoofFromInputs(editIndex = null) {
        const building = document.getElementById('roof_building_select')?.value || '';
        const idf = (document.getElementById('roof_identification')?.value || '').trim();
        const angle = document.getElementById('roof_angle')?.value || '';
        const orient = document.getElementById('roof_orientation_deg')?.value || '';
        const cond = document.querySelector('input[name="roof_condition_single"]:checked')?.value || '';
        const sv = document.querySelector('input[name="structure_visual_single"]:checked')?.value || '';
        const sw = document.querySelector('input[name="structure_weight_load_single"]:checked')?.value || '';
        const wc = document.querySelector('input[name="structure_wind_coverage_single"]:checked')?.value || '';
        const exp = document.querySelector('input[name="requires_expert_single"]:checked')?.value || '';
        // Dev mode: skip validation
        if (!building && !idf) {
            return; // Skip if both missing
        }
        const roofs = getRoofDetails();
        roofs[building] = roofs[building] || [];
        const row = {
            identification: idf,
            angle_pitch: angle,
            orientation: orient,
            roof_condition: cond || null,
            structure_visual: sv || '',
            structure_weight_load: sw || '',
            structure_wind_coverage: wc || '',
            requires_expert_assessment: exp || ''
        };
        console.debug('[Roof] Adding/Updating row', {
            building,
            editIndex,
            row
        });
        if (editIndex !== null && editIndex >= 0 && editIndex < roofs[building].length) {
            roofs[building][editIndex] = row;
        } else {
            roofs[building].push(row);
        }
        // ensure dropdown reflects building we just added to
        const sel = document.getElementById('roof_building_select');
        if (sel) sel.value = building;
        setRoofDetails(roofs);
        console.debug('[Roof] Current details', getRoofDetails());
        // reset inputs
        document.getElementById('roof_identification').value = '';
        document.getElementById('roof_angle').value = '';
        document.getElementById('roof_orientation_deg').value = '';
        document.querySelectorAll('input[name="roof_condition_single"],input[name="structure_visual_single"],input[name="structure_weight_load_single"],input[name="structure_wind_coverage_single"],input[name="requires_expert_single"]').forEach(el => el.checked = false);
        const btn = document.getElementById('add_roof_btn');
        delete btn.dataset.editIndex;
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Roof';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-primary');
    }

    function editRoof(index) {
        const building = document.getElementById('roof_building_select')?.value || '';
        const roofs = getRoofDetails();
        if (!building || !roofs[building] || !roofs[building][index]) return;
        const r = roofs[building][index];
        document.getElementById('roof_identification').value = r.identification || '';
        document.getElementById('roof_angle').value = r.angle_pitch || '';
        document.getElementById('roof_orientation_deg').value = r.orientation || '';
        // restore assessment radios
        if (r.roof_condition) {
            const rc = document.getElementById('roof_cond_' + r.roof_condition);
            if (rc) rc.checked = true;
        }
        const setRadio = (name, val) => {
            if (!val) return;
            const el = document.querySelector(`input[name="${name}"][value="${val}"]`);
            if (el) el.checked = true;
        };
        setRadio('structure_visual_single', r.structure_visual);
        setRadio('structure_weight_load_single', r.structure_weight_load);
        setRadio('structure_wind_coverage_single', r.structure_wind_coverage);
        setRadio('requires_expert_single', r.requires_expert_assessment);
        const btn = document.getElementById('add_roof_btn');
        btn.dataset.editIndex = String(index);
        btn.innerHTML = '<i class="fas fa-check"></i> Update Roof';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
    }

    function removeRoof(index) {
        const building = document.getElementById('roof_building_select')?.value || '';
        const roofs = getRoofDetails();
        if (!building || !roofs[building]) return;
        roofs[building].splice(index, 1);
        setRoofDetails(roofs);
    }

    function renderRoofsTable() {
        const tbody = document.getElementById('roofs_tbody');
        if (!tbody) return;
        const selBuilding = document.getElementById('roof_building_select')?.value || '';
        const roofsMap = getRoofDetails();
        tbody.innerHTML = '';
        // Flatten rows; if a building is selected show only that building; else show all
        const buildingKeys = Object.keys(roofsMap);
        buildingKeys.forEach(b => {
            if (selBuilding && b !== selBuilding) return;
            (roofsMap[b] || []).forEach((r, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${b}</td>
                    <td>${r.identification || ''}</td>
                    <td>${r.angle_pitch || ''}</td>
                    <td>${r.orientation !== undefined && r.orientation !== '' ? r.orientation + '°' : ''}</td>
                    <td>${r.roof_condition !== undefined && r.roof_condition !== null && r.roof_condition !== '' ? r.roof_condition : ''}</td>
                    <td>${r.structure_visual || ''}</td>
                    <td>${r.structure_weight_load || ''}</td>
                    <td>${r.structure_wind_coverage || ''}</td>
                    <td>${r.requires_expert_assessment || ''}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-action="edit" data-building="${b}" data-index="${idx}"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" data-building="${b}" data-index="${idx}"><i class="fas fa-trash"></i></button>
                    </td>`;
                tbody.appendChild(tr);
            });
        });
        // wire actions
        tbody.querySelectorAll('[data-action="edit"]').forEach(btn => btn.addEventListener('click', (e) => {
            const b = e.currentTarget.getAttribute('data-building');
            const i = parseInt(e.currentTarget.getAttribute('data-index'), 10);
            const sel = document.getElementById('roof_building_select');
            if (sel) sel.value = b;
            editRoof(i);
        }));
        tbody.querySelectorAll('[data-action="remove"]').forEach(btn => btn.addEventListener('click', (e) => {
            const b = e.currentTarget.getAttribute('data-building');
            const i = parseInt(e.currentTarget.getAttribute('data-index'), 10);
            const sel = document.getElementById('roof_building_select');
            if (sel) sel.value = b;
            removeRoof(i);
        }));
    }

    function buildSurveyFormData() {
        const formData = {
            survey_id: surveyId || null,
            project_name: document.getElementById('project_name').value,
            date: document.getElementById('survey_date').value,
            location: document.getElementById('location').value,
            gps: document.getElementById('gps').value,
            map_area_m2: document.getElementById('survey_map_area_m2').value,
            map_azimuth_deg: document.getElementById('survey_map_azimuth_deg').value,
            map_polygon_coords: window.currentMapPolygonCoords || null,
            site_survey_responsible_id: document.getElementById('site_survey_responsible_id')?.value || '',
            responsible: (function() {
                const sel = document.getElementById('site_survey_responsible_id');
                return sel && sel.value ? sel.options[sel.selectedIndex].text : '';
            })(),
            accompanied_by_name: document.getElementById('accompanied_by_name')?.value || '',
            accompanied_by_phone: document.getElementById('accompanied_by_phone')?.value || '',
            power_to_install: document.getElementById('power_to_install').value,
            certified_power: document.getElementById('certified_power').value,
            survey_notes: document.getElementById('survey_notes').value,
            challenges: document.getElementById('challenges').value,
            checklist_data: collectSurveyChecklist(),
            photo_checklist: collectPhotoChecklist(),
            building_details: getBuildingDetails(),
            roof_details: getRoofDetails(),
            installation_site_notes: document.getElementById('installation_site_notes')?.value || '',
            shading_details: getShadingDetails(),
            // installation site extras
            roof_access_available: (function() {
                const v = document.querySelector('input[name="roof_access_available"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            permanent_ladder_feasible: (function() {
                const v = document.querySelector('input[name="permanent_ladder_feasible"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            photos_link: document.getElementById('photos_link')?.value || '',
            injection_point_type: document.getElementById('injection_point_type')?.value || '',
            circuit_type: (document.querySelector('input[name="circuit_type"]:checked')?.value || ''),
            inverter_location: document.getElementById('inverter_location')?.value || '',
            pv_protection_board_location: document.getElementById('pv_protection_board_location')?.value || '',
            pv_board_to_injection_distance_m: document.getElementById('pv_board_to_injection_distance_m')?.value || '',
            injection_has_space_for_switch: (function() {
                const v = document.querySelector('input[name="injection_has_space_for_switch"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            injection_has_busbar_space: (function() {
                const v = document.querySelector('input[name="injection_has_busbar_space"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            // painel elétrico (novo)
            panel_cable_exterior_to_main_gauge: document.getElementById('panel_cable_exterior_to_main_gauge')?.value || '',
            panel_brand_model: document.getElementById('panel_brand_model')?.value || '',
            breaker_brand_model: document.getElementById('breaker_brand_model')?.value || '',
            breaker_rated_current_a: document.getElementById('breaker_rated_current_a')?.value || '',
            breaker_short_circuit_current_ka: document.getElementById('breaker_short_circuit_current_ka')?.value || '',
            residual_current_ma: document.getElementById('residual_current_ma')?.value || '',
            earth_measurement_ohms: document.getElementById('earth_measurement_ohms')?.value || '',
            is_bidirectional_meter: (function() {
                const v = document.querySelector('input[name="is_bidirectional_meter"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            // gerador (novo)
            generator_exists: (function() {
                const v = document.querySelector('input[name="generator_exists"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            generator_mode: document.querySelector('input[name="generator_mode"]:checked')?.value || '',
            generator_scope: document.querySelector('input[name="generator_scope"]:checked')?.value || '',
            // comunicações (novo)
            comm_wifi_near_pv: (function() {
                const v = document.querySelector('input[name="comm_wifi_near_pv"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            comm_ethernet_near_pv: (function() {
                const v = document.querySelector('input[name="comm_ethernet_near_pv"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            comm_utp_requirement: document.querySelector('input[name="comm_utp_requirement"]:checked')?.value || '',
            comm_utp_length_m: document.getElementById('comm_utp_length_m')?.value || '',
            comm_router_port_open_available: (function() {
                const v = document.querySelector('input[name="comm_router_port_open_available"]:checked')?.value;
                return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            comm_router_port_number: document.getElementById('comm_router_port_number')?.value || '',
            comm_mobile_coverage_level: document.getElementById('comm_mobile_coverage_level')?.value || '',
            // site assessment now merged into roof rows
        };
        return formData;
    }
    // Expose builder globally so autosave JS can use it
    if (typeof window !== 'undefined') window.buildSurveyFormData = buildSurveyFormData;

    // Non-blocking notification helper (simple toast)
    function ensureNoticeContainer() {
        if (document.getElementById('site-survey-notice-container')) return;
        const css = `#site-survey-notice-container{position:fixed;top:16px;right:16px;z-index:1060;display:flex;flex-direction:column;gap:8px}.site-survey-notice{min-width:220px;padding:10px 14px;border-radius:8px;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.12);font-weight:600;opacity:0;transform:translateY(-6px);transition:opacity .25s,transform .25s}.site-survey-notice.show{opacity:1;transform:none}.site-survey-notice.success{background:#2ecc71}.site-survey-notice.error{background:#e74c3c}`;
        const style = document.createElement('style');
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
        const container = document.createElement('div');
        container.id = 'site-survey-notice-container';
        document.body.appendChild(container);
    }

    function showNotice(message, type = 'success', timeout = 3000) {
        ensureNoticeContainer();
        const container = document.getElementById('site-survey-notice-container');
        const el = document.createElement('div');
        el.className = 'site-survey-notice ' + type;
        el.textContent = message;
        container.appendChild(el);
        // force reflow then show
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, timeout);
    }

    // Enable/disable generator mode/scope radios depending on generator presence
    function setGeneratorControlsState() {
        try {
            const existsVal = document.querySelector('input[name="generator_exists"]:checked')?.value;
            const disable = !(existsVal === 'YES');
            const controls = document.querySelectorAll('input[name="generator_mode"], input[name="generator_scope"]');
            controls.forEach(i => {
                i.disabled = disable;
                if (disable) i.checked = false;
            });
        } catch (e) {
            // ignore
        }
    }

    // Attach listeners and initialize
    try {
        document.querySelectorAll('input[name="generator_exists"]').forEach(r => r.addEventListener('change', setGeneratorControlsState));
        // expose for autosave or external callers
        window.setGeneratorControlsState = setGeneratorControlsState;
        // initialize on immediate load
        setTimeout(setGeneratorControlsState, 10);
    } catch (e) {
        // ignore
    }

    function saveSurvey({
        quiet = false
    } = {}) {
        const formData = buildSurveyFormData();
        // Dev mode: skip validation
        return fetch('save_site_survey.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: surveyId ? 'update' : 'create',
                    survey_id: surveyId,
                    ...formData
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (!quiet) showNotice('Survey saved successfully!', 'success');
                    if (!surveyId && data.survey_id) {
                        surveyId = data.survey_id;
                        const surveyIdField = document.querySelector('input[name="survey_id"]');
                        if (surveyIdField && !surveyIdField.value) surveyIdField.value = surveyId;
                        // keep page as is; caller may redirect or open report
                    }
                    try {
                        localStorage.removeItem('site_survey_last_id');
                    } catch (e) {}
                    try {
                        localStorage.removeItem('site_survey_last_saved_ts');
                    } catch (e) {}
                    try {
                        const badge = document.getElementById('autosave-last');
                        if (badge) {
                            const d = new Date();
                            const hh = String(d.getHours()).padStart(2, '0');
                            const mm = String(d.getMinutes()).padStart(2, '0');
                            const ss = String(d.getSeconds()).padStart(2, '0');
                            badge.textContent = 'Saved final ' + hh + ':' + mm + ':' + ss;
                            try {
                                localStorage.setItem('site_survey_last_saved_ts', d.toISOString());
                            } catch (e) {}
                        }
                    } catch (e) {}
                    return data;
                } else {
                    throw new Error(data.message || 'Failed to save');
                }
            })
            .catch(err => {
                if (!quiet) showNotice('Error: ' + err.message, 'error');
                throw err;
            });
    }

    document.getElementById('saveSurveyBtn').addEventListener('click', function() {
        saveSurvey();
    });

    function updateNextButtonLabel() {
        const btn = document.getElementById('nextTabBtn');
        if (!btn) return;
        const links = Array.from(document.querySelectorAll('#formTabs .nav-link'));
        const activeIndex = links.findIndex(l => l.classList.contains('active'));
        if (activeIndex === links.length - 1) {
            btn.textContent = 'Create Report';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
        } else {
            btn.textContent = 'Next';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-primary');
        }
    }

    function goNextOrCreate() {
        const links = Array.from(document.querySelectorAll('#formTabs .nav-link'));
        const activeIndex = links.findIndex(l => l.classList.contains('active'));
        if (activeIndex < links.length - 1) {
            links[activeIndex + 1].click();
            updateNextButtonLabel();
        } else {
            // Last tab -> save and open report
            saveSurvey({
                quiet: true
            }).then((data) => {
                console.log('Save response data:', data);
                const id = surveyId || data.survey_id;
                if (id) {
                    // open the new professional generator to include all fields
                    window.open('generate_survey_report_new.php?id=' + id, '_blank');
                } else {
                    alert('Unable to generate report: missing survey ID. id = ' + id + ', surveyId = ' + surveyId + ', data.survey_id = ' + data.survey_id);
                }
            });
        }
    }

    // Load Site Survey Responsibles into dropdown
    document.addEventListener('DOMContentLoaded', function() {
        const appRoot = window.APP_ROOT || (window.BASE_URL ? new URL(window.BASE_URL).pathname : (window.location.pathname.includes('cleanwattsportal') ? '/cleanwattsportal/' : (window.location.pathname.includes('comissionamentov2') ? '/ComissionamentoV2/' : '/')));
        fetch(`${appRoot}ajax/get_site_survey_responsibles.php`)
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('site_survey_responsible_id');
                if (!select) return;
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    select.appendChild(opt);
                });
                const selectedId = select.dataset.selected;
                if (selectedId) select.value = selectedId;
            })
            .catch((err) => {
                console.error('[Site Survey] Failed to load responsibles:', err);
            });

        const addBtn = document.getElementById('add-site-survey-responsible-btn');
        if (addBtn) {
            addBtn.addEventListener('click', showAddSiteSurveyResponsibleModal);
        }
        // Buildings UI
        renderBuildingCards();
        const addBuildingBtn = document.getElementById('add_building_btn');
        if (addBuildingBtn) addBuildingBtn.addEventListener('click', () => {
            const idx = addBuildingBtn.dataset.editIndex ? parseInt(addBuildingBtn.dataset.editIndex, 10) : null;
            addBuildingFromInputs(idx);
        });

        // Roofs UI
        refreshRoofBuildingOptions();
        renderRoofsTable();
        document.getElementById('roof_building_select')?.addEventListener('change', renderRoofsTable);
        const addRoofBtn = document.getElementById('add_roof_btn');
        if (addRoofBtn) addRoofBtn.addEventListener('click', () => {
            const idx = addRoofBtn.dataset.editIndex ? parseInt(addRoofBtn.dataset.editIndex, 10) : null;
            addRoofFromInputs(idx);
        });

        // Shading UI
        refreshShadingBuildingOptions();
        renderShadingTable();
        refreshShadingStatusUI();
        // Ensure shading controls are enabled
        try {
            enableShadingControls();
        } catch (e) {
            console.warn('[Shading] enableShadingControls not available', e);
        }
        document.getElementById('shading_building_select')?.addEventListener('change', () => {
            refreshShadingStatusUI();
            renderShadingTable();
        });
        // radios
        const shadeRadios = ['shade_none', 'shade_partial', 'shade_heavy'];
        shadeRadios.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', (e) => onChangeShadingStatus(e.target.value));
                el.addEventListener('click', () => console.log('[Shading] Radio clicked:', id));
            }
        });
        document.getElementById('shading_notes')?.addEventListener('input', onChangeShadingNotes);
        document.getElementById('shading_notes')?.addEventListener('keydown', () => console.log('[Shading] Notes keydown'));
        const addShadeBtn = document.getElementById('add_shade_obj_btn');
        if (addShadeBtn) addShadeBtn.addEventListener('click', () => {
            const idx = addShadeBtn.dataset.editIndex ? parseInt(addShadeBtn.dataset.editIndex, 10) : null;
            addShadingObject(idx);
        });
        if (addShadeBtn) console.log('[Site Survey] add_shade_obj_btn listener attached');

        // Legacy migration: if no roofs and legacySiteAssessment has values, prefill inputs once
        try {
            const legacy = <?php echo json_encode($legacySiteAssessment); ?>;
            const hasRoofs = Object.keys(getRoofDetails()).some(k => (getRoofDetails()[k] || []).length);
            if (!hasRoofs && legacy && (legacy.roof_condition || legacy.structure_visual || legacy.structure_weight_load || legacy.structure_wind_coverage || legacy.requires_expert_assessment)) {
                if (legacy.roof_condition) {
                    const rc = document.getElementById('roof_cond_' + legacy.roof_condition);
                    if (rc) rc.checked = true;
                }
                const setRadio = (name, val) => {
                    if (!val) return;
                    const el = document.querySelector(`input[name="${name}"][value="${val}"]`);
                    if (el) el.checked = true;
                };
                setRadio('structure_visual_single', legacy.structure_visual);
                setRadio('structure_weight_load_single', legacy.structure_weight_load);
                setRadio('structure_wind_coverage_single', legacy.structure_wind_coverage);
                setRadio('requires_expert_single', legacy.requires_expert_assessment);
            }
        } catch {}
    });

    // Wire Next/Report button
    document.getElementById('nextTabBtn')?.addEventListener('click', goNextOrCreate);
    document.addEventListener('DOMContentLoaded', updateNextButtonLabel);

    function showAddSiteSurveyResponsibleModal() {
        const modalHtml = `
<div class="modal fade" id="addSiteSurveyResponsibleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Site Survey Responsible</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSiteSurveyResponsibleForm">
                    <div class="mb-3">
                        <label for="ssr_name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="ssr_name" name="name" placeholder="Full name">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
                <button type="button" class="btn btn-success" id="addSiteSurveyResponsibleSubmitBtn"><i class="fas fa-plus me-1"></i>Add Responsible</button>
            </div>
        </div>
    </div>
</div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('addSiteSurveyResponsibleModal'));
        const form = document.getElementById('addSiteSurveyResponsibleForm');
        const submitBtn = document.getElementById('addSiteSurveyResponsibleSubmitBtn');
        modal.show();

        const handleSubmit = () => {
            const formData = new FormData(form);
            const name = formData.get('name').trim();
            if (!name) {
                alert('Please enter a name.');
                return;
            }
            fetch(window.getAjaxUrl('ajax/add_site_survey_responsible.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('site_survey_responsible_id');
                        const opt = document.createElement('option');
                        opt.value = data.responsible.id;
                        opt.textContent = data.responsible.name;
                        select.appendChild(opt);
                        select.value = data.responsible.id;
                        modal.hide();
                    } else {
                        alert(data.error || 'Failed to add responsible');
                    }
                })
                .catch(() => alert('Error adding responsible'));
        };
        submitBtn.addEventListener('click', handleSubmit);
        form.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });
        document.getElementById('addSiteSurveyResponsibleModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    function collectSurveyChecklist() {
        const rows = Array.from(document.querySelectorAll('#survey-checklist-body tr.survey-row'));
        return rows.map(row => ({
            key: row.getAttribute('data-key'),
            label: row.querySelector('.survey-label').textContent.trim(),
            status: row.querySelector('input.survey-status:checked')?.value || '',
            note: row.querySelector('.survey-note').value.trim()
        }));
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('survey-status') || e.target.classList.contains('survey-note')) {
            collectSurveyChecklist();
        }
    });

    function collectPhotoChecklist() {
        const boxes = Array.from(document.querySelectorAll('.photo-check'));
        return boxes.filter(b => b.checked).map(b => ({
            key: b.dataset.key,
            label: b.dataset.label || ''
        }));
    }

    // Removed standalone site assessment functions (integrated into roof rows)
</script>

<?php
// Expose MAPBOX_API_KEY from environment to JS if available (optional)
$mapboxKey = getenv('MAPBOX_API_KEY') ?: '';
?>
<script>
    window.MAPBOX_API_KEY = '<?php echo addslashes($mapboxKey); ?>';
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="https://unpkg.com/@turf/turf/turf.min.js"></script>
<script src="assets/js/site_survey_map.js"></script>
<script src="assets/js/autosave_site_survey.js"></script>

<?php include 'includes/footer.php'; ?>