<?php

/**
 * Logout Handler
 * 
 * Destroys the user session and redirects to login
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout
if (isset($_SESSION['username'])) {
    error_log("[AUTH] User logged out - " . $_SESSION['username']);
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
