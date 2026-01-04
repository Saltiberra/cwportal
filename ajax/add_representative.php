<?php

/**
 * Add Representative
 * 
 * This script handles the addition of new representatives to the database
 */

// Include database connection
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$epcId = isset($_POST['epc_id']) ? intval($_POST['epc_id']) : 0;
// Support JSON body when client sends application/json (some hosts or clients may prefer JSON)
if (empty($_POST)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $name = isset($json['name']) ? trim($json['name']) : $name;
        $phone = isset($json['phone']) ? trim($json['phone']) : $phone;
        $email = isset($json['email']) ? trim($json['email']) : $email;
        $epcId = isset($json['epc_id']) ? intval($json['epc_id']) : $epcId;
    }
}

// Debug logging of incoming request for troubleshooting (avoid logging raw personal data in production)
error_log("[ADD_REP] Received POST - name_present=" . (!empty($name) ? '1' : '0') . ", phone_present=" . (!empty($phone) ? '1' : '0') . ", email_present=" . (!empty($email) ? '1' : '0') . ", epc_id=" . $epcId);

// Validate inputs (EPC is optional)
if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['error' => 'Name and phone are required']);
    exit;
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

try {
    // If an EPC was provided, validate it
    $epcValue = null;
    if ($epcId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM epcs WHERE id = ?");
        $stmt->execute([$epcId]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Selected EPC company does not exist']);
            exit;
        }
        $epcValue = $epcId;
    }

    // Check if representative already exists for this EPC (or without EPC)
    // Some hosts/databases represent "no EPC" as 0 instead of NULL; use 0 for checks when epc is omitted
    $epcForCheck = $epcValue !== null ? $epcValue : 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM representatives WHERE name = ? AND COALESCE(epc_id, 0) = ?");
    $stmt->execute([$name, $epcForCheck]);

    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Representative already exists']);
        exit;
    }

    // Insert new representative (epc_id may be NULL). Some DBs reject NULL for epc_id; handle that case.
    try {
        $stmt = $pdo->prepare("INSERT INTO representatives (name, phone, email, epc_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email ?: null, $epcValue]);
    } catch (PDOException $e) {
        // If the DB refuses NULL for epc_id (SQLSTATE 23000 / error 1048), retry using 0
        $msg = $e->getMessage();
        error_log("[ADD_REP] Insert failed, trying fallback (epc_id=0): " . $msg);
        if (strpos($msg, "epc_id") !== false && (strpos($msg, "cannot be null") !== false || strpos($msg, "1048") !== false)) {
            // Retry with epc_id = 0
            $stmt = $pdo->prepare("INSERT INTO representatives (name, phone, email, epc_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email ?: null, 0]);
            // Set epcValue to 0 so response reflects actual stored value
            $epcValue = 0;
        } else {
            // Re-throw if it's a different error
            throw $e;
        }
    }

    // Get the new representative ID
    $repId = $pdo->lastInsertId();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Representative added successfully',
        'representative' => [
            'id' => $repId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'epc_id' => $epcValue
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
