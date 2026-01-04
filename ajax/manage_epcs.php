<?php

/**
 * AJAX Handler for Managing EPC Companies
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only admins can manage EPC companies']);
    exit;
}

// Get action from POST or GET
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Ensure table has expected columns (auto-migration for older databases)
function ensureEpcsSchema(PDO $pdo)
{
    try {
        $cols = [];
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epcs'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $cols[strtolower($c)] = true;
        }

        $alterSql = [];
        if (!isset($cols['address'])) {
            $alterSql[] = "ADD COLUMN `address` varchar(500) DEFAULT NULL AFTER `name`";
        }
        if (!isset($cols['phone'])) {
            $alterSql[] = "ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `address`";
        }
        if (!isset($cols['email'])) {
            $alterSql[] = "ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `phone`";
        }
        if (!isset($cols['website'])) {
            $alterSql[] = "ADD COLUMN `website` varchar(255) DEFAULT NULL AFTER `email`";
        }
        if (count($alterSql) > 0) {
            $sql = "ALTER TABLE `epcs` " . implode(', ', $alterSql);
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        // Swallow schema check errors, handled later by main try/catch
    }
}

try {
    if ($action === 'list') {
        // Get all EPC companies
        try {
            // Auto-migrate epcs table if needed
            ensureEpcsSchema($pdo);
            $stmt = $pdo->query("
                SELECT id, name, address, phone, email, website, created_at 
                FROM epcs 
                ORDER BY name ASC
            ");
            $epcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $epcs,
                'count' => count($epcs)
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    } elseif ($action === 'create') {
        // Create new EPC company
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');

        // Validate
        if (empty($name)) {
            throw new Exception('Company name is required');
        }

        $stmt = $pdo->prepare("
            INSERT INTO epcs (name, address, phone, email, website, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $address ?: null, $phone ?: null, $email ?: null, $website ?: null]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'message' => 'EPC Company created successfully'
        ]);
    } elseif ($action === 'update') {
        // Update EPC company
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }
        if (empty($name)) {
            throw new Exception('Company name is required');
        }

        $stmt = $pdo->prepare("
            UPDATE epcs 
            SET name = ?, address = ?, phone = ?, email = ?, website = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $address ?: null, $phone ?: null, $email ?: null, $website ?: null, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'EPC Company updated successfully'
        ]);
    } elseif ($action === 'delete') {
        // Delete EPC company
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }

        $stmt = $pdo->prepare("DELETE FROM epcs WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'EPC Company deleted successfully'
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
