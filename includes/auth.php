<?php

/**
 * Authentication Middleware
 * 
 * This file provides authentication functions and session management.
 * Include this at the top of any page that requires login.
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/audit.php';

/**
 * Check if user is logged in
 * If not, redirect to login page
 */
function requireLogin()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

// Session timeout (minutes). Change if you want longer sessions.
if (!defined('SESSION_TIMEOUT_MINUTES')) {
    define('SESSION_TIMEOUT_MINUTES', 24);
}

/**
 * Refresh sliding session expiration by updating login_time
 */
function refreshSession()
{
    if (isset($_SESSION['user_id'])) {
        $_SESSION['login_time'] = time();
    }
}

/**
 * Get current logged-in user
 * @return array|null - User data or null if not logged in
 */
function getCurrentUser()
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'operador'
    ];
}

/**
 * Check if user has a specific role
 * @param string $role - Role to check (e.g., 'admin', 'supervisor', 'technician')
 * @return bool
 */
function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Logout current user
 */
function logout()
{
    // 📝 Log logout action
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        try {
            logAction('logout', 'users', $_SESSION['user_id'], 'User logged out', $_SESSION['username']);
        } catch (Exception $e) {
            // Log error silently
        }
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Authenticate user with credentials
 * @param PDO $pdo - Database connection
 * @param string $username - Username or email
 * @param string $password - Plain text password
 * @return array|null - User data if authentication successful, null otherwise
 */
function authenticateUser($pdo, $username, $password)
{
    try {
        // Search by username or email
        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, full_name, role, is_active 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Update last_login timestamp
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'] ?? 'operador'
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Create a session for authenticated user
 * @param array $user - User data from authenticateUser()
 */
function createSession($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // 📝 Log login action
    try {
        logAction('login', 'users', $user['id'], 'User logged in (' . $user['role'] . ')', $user['username']);
    } catch (Exception $e) {
        // Log error silently
    }
}

/**
 * Check if session is still valid (optional: check for timeout)
 * @param int $timeoutMinutes - Session timeout in minutes (default: 0 = no timeout)
 * @return bool
 */
function isSessionValid($timeoutMinutes = null)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Use default session timeout if not provided
    if ($timeoutMinutes === null) {
        $timeoutMinutes = defined('SESSION_TIMEOUT_MINUTES') ? SESSION_TIMEOUT_MINUTES : 0;
    }

    // Check timeout if specified (>0)
    if ($timeoutMinutes > 0) {
        if (!isset($_SESSION['login_time'])) {
            return false;
        }

        $elapsedSeconds = time() - $_SESSION['login_time'];
        $timeoutSeconds = $timeoutMinutes * 60;

        if ($elapsedSeconds > $timeoutSeconds) {
            session_destroy();
            return false;
        }
    }

    return true;
}
