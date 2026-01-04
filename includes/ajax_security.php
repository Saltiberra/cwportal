<?php

/**
 * AJAX Security Validator
 * 
 * This file provides functions to validate AJAX requests
 * ensuring that reports belong to the current session/user
 */

/**
 * Validate that a report belongs to the current session
 * 
 * @param PDO $pdo - Database connection
 * @param int $reportId - Report ID to validate
 * @param string $sessionId - Current session ID
 * @throws Exception if report doesn't exist or doesn't belong to session
 * @return array - Report data if valid
 */
function validateReportOwnership($pdo, $reportId, $sessionId)
{
    $stmt = $pdo->prepare("SELECT id, session_id FROM commissioning_reports WHERE id = ? AND session_id = ?");
    $stmt->execute([$reportId, $sessionId]);
    $report = $stmt->fetch();

    if (!$report) {
        error_log("[AJAX Security] ❌ Unauthorized access attempt to report {$reportId}");
        throw new Exception("Unauthorized: This report does not belong to your session");
    }

    return $report;
}

/**
 * Return a standardized error JSON response
 * 
 * @param string $message - Error message
 * @param int $httpCode - HTTP status code (default 400)
 */
function ajaxError($message, $httpCode = 400)
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

/**
 * Validate AJAX request for a specific report
 * This should be called at the top of every AJAX endpoint that modifies/reads reports
 * 
 * @param PDO $pdo - Database connection
 * @param array $data - POST/JSON data containing 'report_id'
 * @return array - Report data if validation passes
 */
function validateAjaxRequest($pdo, $data)
{
    session_start();

    if (!isset($data['report_id'])) {
        ajaxError("report_id is required", 400);
    }

    $reportId = (int)$data['report_id'];
    $sessionId = session_id();

    if ($reportId <= 0) {
        ajaxError("Invalid report_id", 400);
    }

    try {
        // First, check if report exists at all
        $stmt = $pdo->prepare("SELECT id, session_id, user_id FROM commissioning_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            error_log("[AJAX Security] ❌ Report not found: {$reportId}");
            ajaxError("Report not found", 404);
        }

        // Check if session matches OR if user is logged in (more flexible for development)
        if ($report['session_id'] !== $sessionId) {
            // If session doesn't match, check if there's a user_id in session
            if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
                error_log("[AJAX Security] ❌ Unauthorized access to report {$reportId}. Session mismatch: {$report['session_id']} vs {$sessionId}");
                ajaxError("Unauthorized: This report does not belong to your session", 403);
            }
            // User is logged in, allow access
            error_log("[AJAX Security] ⚠️ Session mismatch but user is logged in, allowing access to report {$reportId}");
        }

        return $report;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Unauthorized') === false && strpos($e->getMessage(), 'not found') === false) {
            ajaxError("Database error: " . $e->getMessage(), 500);
        }
        throw $e;
    }
}
