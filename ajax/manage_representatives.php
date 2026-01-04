<?php

/**
 * AJAX Handler for Managing Representatives
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only admins can manage representatives']);
    exit;
}

// Get action from POST or GET
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'list') {
        // Get all representatives with EPC info
        try {
            $stmt = $pdo->query("
                SELECT r.id, r.name, r.phone, r.email, r.epc_id, e.name as epc_name, r.created_at 
                FROM representatives r
                LEFT JOIN epcs e ON r.epc_id = e.id
                ORDER BY r.name ASC
            ");
            $reps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $reps,
                'count' => count($reps)
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    } elseif ($action === 'list_epcs') {
        // Get all EPCs for dropdown
        try {
            $stmt = $pdo->query("SELECT id, name FROM epcs ORDER BY name ASC");
            $epcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $epcs
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    } elseif ($action === 'create') {
        // Create new representative
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $epc_id = intval($_POST['epc_id'] ?? 0);

        // Validate
        if (empty($name)) {
            throw new Exception('Name is required');
        }
        if (empty($phone)) {
            throw new Exception('Phone is required');
        }
        // EPC is optional

        $stmt = $pdo->prepare("
            INSERT INTO representatives (name, phone, email, epc_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $phone, $email ?: null, $epc_id]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'message' => 'Representative created successfully'
        ]);
    } elseif ($action === 'update') {
        // Update representative
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $epc_id = intval($_POST['epc_id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }
        if (empty($name)) {
            throw new Exception('Name is required');
        }
        if (empty($phone)) {
            throw new Exception('Phone is required');
        }
        // EPC is optional

        $stmt = $pdo->prepare("
            UPDATE representatives 
            SET name = ?, phone = ?, email = ?, epc_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $phone, $email ?: null, $epc_id, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Representative updated successfully'
        ]);
    } elseif ($action === 'delete') {
        // Delete representative
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }

        $stmt = $pdo->prepare("DELETE FROM representatives WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Representative deleted successfully'
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
