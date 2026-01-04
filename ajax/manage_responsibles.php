<?php

/**
 * AJAX Handler for Managing Commissioning Responsibles
 * Operations: Get list, Create, Update, Delete
 * 🔒 ONLY ACCESSIBLE TO ADMIN USERS
 */

// Set JSON response header FIRST
header('Content-Type: application/json');

// Start session without redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in (without redirect)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Get user data from session
$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'] ?? 'operador'
];

// Check if user is admin
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only admins can manage responsibles']);
    exit;
}

// Get action from POST or GET
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'list') {
        // Get all commissioning responsibles
        $stmt = $pdo->query("
            SELECT id, name, email, phone, department, active, created_at 
            FROM commissioning_responsibles 
            ORDER BY name ASC
        ");
        $responsibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $responsibles,
            'count' => count($responsibles)
        ]);
    } elseif ($action === 'create') {
        // Create new responsible
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');

        // Validate
        if (empty($name)) {
            throw new Exception('Name is required');
        }

        $stmt = $pdo->prepare("
            INSERT INTO commissioning_responsibles (name, email, phone, department, active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$name, $email ?: null, $phone ?: null, $department ?: null]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'message' => 'Responsible created successfully'
        ]);
    } elseif ($action === 'update') {
        // Update responsible
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $active = intval($_POST['active'] ?? 1);

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }
        if (empty($name)) {
            throw new Exception('Name is required');
        }

        $stmt = $pdo->prepare("
            UPDATE commissioning_responsibles 
            SET name = ?, email = ?, phone = ?, department = ?, active = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email ?: null, $phone ?: null, $department ?: null, $active, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Responsible updated successfully'
        ]);
    } elseif ($action === 'delete') {
        // Delete responsible
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }

        $stmt = $pdo->prepare("DELETE FROM commissioning_responsibles WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Responsible deleted successfully'
        ]);
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
