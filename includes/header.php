<?php

/**
 * Header Include File
 * 
 * Contains the standard header, navigation and meta tags
 * for the Commissioning System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce UTF-8 app-wide
@ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
if (function_exists('mb_http_output')) mb_http_output('UTF-8');
// Default Content-Type for HTML responses
header('Content-Type: text/html; charset=utf-8');

// Get current user if logged in
$loggedInUser = null;
$userRole = null;
if (isset($_SESSION['user_id'])) {
    $loggedInUser = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
        'role' => $_SESSION['role'] ?? 'operador'
    ];
    $userRole = $loggedInUser['role'];
}

// Define a reliable BASE_URL for all JS fetches
$baseUrl = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
if ($baseUrl !== '/') {
    $baseUrl = rtrim($baseUrl, '/') . '/';
}
?>
<script>
    window.BASE_URL = '<?php echo $baseUrl; ?>';
    console.log('[Header] Global BASE_URL set to:', window.BASE_URL);
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanwatts Portal — Reports</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/favicon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/clear_button.css">
    <link rel="stylesheet" href="assets/css/branding.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <!-- Mobile responsiveness helpers -->
    <link rel="stylesheet" href="assets/css/responsive.css">
    <?php if (basename($_SERVER['PHP_SELF']) === 'comissionamento.php'): ?>
        <link rel="stylesheet" href="assets/css/loading_overlay.css">
    <?php endif; ?>

    <!-- Anime.js for smooth animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.2/anime.min.js"></script>

    <!-- GSAP for professional animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/ScrollTrigger.min.js"></script>

    <!-- Animation controllers -->
    <script src="assets/js/animations.js" defer></script>
    <script src="assets/js/gsap-animations.js" defer></script>

</head>

<body>
    <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
        <div class="container mt-3">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <!-- Injetar script de dropdowns direto no HTML apenas para página de comissionamento -->
    <?php
    if (basename($_SERVER['PHP_SELF']) === 'comissionamento.php') {
        include 'includes/inject_module_dropdowns.php';
    }
    ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/img/favicon.png" alt="Cleanwatts Logo" height="32" class="me-2">
                <span class="fw-semibold">Cleanwatts Portal</span>
                <span class="ms-2 small text-white-50">Reports</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Commissioning Navigation (matching Site Survey style) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="commissioningDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-clipboard-list me-1"></i>Commissioning
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="commissioningDropdown">
                            <li><a class="dropdown-item" href="commissioning_dashboard.php"><i class="fas fa-gauge me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="comissionamento.php?new=1"><i class="fas fa-plus me-2"></i>New Report</a></li>
                        </ul>
                    </li>
                    <!-- Site Survey Navigation -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="surveyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-clipboard-list me-1"></i>Site Survey
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="surveyDropdown">
                            <li><a class="dropdown-item" href="survey_index.php"><i class="fas fa-gauge me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="site_survey.php"><i class="fas fa-plus me-2"></i>New Survey</a></li>
                        </ul>
                    </li>
                    <!-- Field Supervision Navigation -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="fieldSupervisionDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-hard-hat me-1"></i>Field Supervision
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="fieldSupervisionDropdown">
                            <li><a class="dropdown-item" href="field_supervision.php"><i class="fas fa-gauge me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="field_supervision.php#new" onclick="sessionStorage.setItem('fsOpenNew','1');"><i class="fas fa-plus me-2"></i>New Entry</a></li>
                        </ul>
                    </li>
                    <!-- Procedures & Credentials Navigation -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="proceduresDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-key me-1"></i>Procedures & Credentials
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="proceduresDropdown">
                            <li><a class="dropdown-item" href="procedures_credentials.php"><i class="fas fa-folder-open me-2"></i>Library</a></li>
                            <li><a class="dropdown-item" href="procedures_credentials.php#new-cred" onclick="sessionStorage.setItem('pcOpenNewCred','1');"><i class="fas fa-plus me-2"></i>New Credential</a></li>
                        </ul>
                    </li>
                    <!-- Primary quick links placed after the two dropdowns -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Home</a>
                    </li>
                    <?php if ($userRole === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog me-1"></i>Admin
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <li><a class="dropdown-item" href="admin_trash.php"><i class="fas fa-trash me-2"></i>Trash</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if ($loggedInUser): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php if ($userRole === 'admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif ($userRole === 'supervisor'): ?>
                                    <span class="badge bg-warning">Supervisor</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($loggedInUser['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><span class="dropdown-item-text"><small class="text-muted">Logged in as</small><br><strong><?php echo htmlspecialchars($loggedInUser['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong><br><small class="badge bg-secondary mt-2"><?php echo htmlspecialchars(ucfirst($userRole), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small></span></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid mt-4 main-content-container">