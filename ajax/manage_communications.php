<?php
// ajax/manage_communications.php
// Admin-only endpoint for Communications Models management

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Database connection (use same include and $pdo as other endpoints)
require_once __DIR__ . '/../config/database.php';

// Auth: require logged-in admin (align with manage_cables.php)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$role = $_SESSION['role'] ?? 'operador';
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: admin only']);
    exit;
}

// Ensure schema exists (defensive)
try {
    // Brands table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `communications_brands` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand_name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Models table (base)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `communications_models` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `model_name` varchar(255) NOT NULL,
        `equipment_type` varchar(100) NOT NULL,
        `manufacturer` varchar(255) DEFAULT NULL,
        `characteristics` text DEFAULT NULL,
        `communication_protocols` varchar(255) DEFAULT NULL,
        `power_supply` varchar(100) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Add brand_id column if missing
    $colExists = false;
    $chk = $pdo->query("SHOW COLUMNS FROM communications_models LIKE 'brand_id'");
    if ($chk && $chk->fetch()) {
        $colExists = true;
    }
    if (!$colExists) {
        // add nullable brand_id, index and FK (ignore FK error if engine/version not supported)
        $pdo->exec("ALTER TABLE communications_models ADD COLUMN `brand_id` INT NULL AFTER `id`");
        try {
            $pdo->exec("ALTER TABLE communications_models ADD INDEX (`brand_id`)");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE communications_models ADD CONSTRAINT `fk_comm_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `communications_brands`(`id`) ON DELETE SET NULL");
        } catch (Exception $e) {
        }
    }
} catch (Exception $e) {
    // ignore
}

$action = $_REQUEST['action'] ?? 'list_brands';

try {
    if ($action === 'list_brands') {
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $stmt = $pdo->prepare("SELECT id, brand_name FROM communications_brands WHERE brand_name LIKE ? ORDER BY brand_name");
            $stmt->execute(['%' . $q . '%']);
        } else {
            $stmt = $pdo->query("SELECT id, brand_name FROM communications_brands ORDER BY brand_name");
        }
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($action === 'create_brand') {
        $name = trim($_POST['brand_name'] ?? '');
        if ($name === '') throw new Exception('Brand name required');
        $dup = $pdo->prepare("SELECT id FROM communications_brands WHERE brand_name = ?");
        $dup->execute([$name]);
        if ($dup->fetch()) throw new Exception('Brand already exists');
        $stmt = $pdo->prepare("INSERT INTO communications_brands (brand_name) VALUES (?)");
        $stmt->execute([$name]);
        echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'update_brand') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['brand_name'] ?? '');
        if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
        $stmt = $pdo->prepare("UPDATE communications_brands SET brand_name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        echo json_encode(['success' => true, 'message' => 'Brand updated']);
    } elseif ($action === 'delete_brand') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid brand id');
        $stmt = $pdo->prepare("DELETE FROM communications_brands WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Brand deleted']);
    } elseif ($action === 'list_models') {
        $search = trim($_GET['search'] ?? '');
        $equip = trim($_GET['equipment'] ?? '');
        if ($equip !== '') {
            if ($search !== '') {
                $stmt = $pdo->prepare("SELECT m.* FROM communications_models m WHERE m.equipment_type = ? AND (m.model_name LIKE ? OR m.manufacturer LIKE ? OR m.communication_protocols LIKE ?) ORDER BY m.model_name");
                $term = '%' . $search . '%';
                $stmt->execute([$equip, $term, $term, $term]);
            } else {
                $stmt = $pdo->prepare("SELECT m.* FROM communications_models m WHERE m.equipment_type = ? ORDER BY m.model_name");
                $stmt->execute([$equip]);
            }
        } else {
            if ($search !== '') {
                $stmt = $pdo->prepare("SELECT m.* FROM communications_models m WHERE m.model_name LIKE ? OR m.manufacturer LIKE ? OR m.communication_protocols LIKE ? OR m.equipment_type LIKE ? ORDER BY m.equipment_type, m.model_name");
                $term = '%' . $search . '%';
                $stmt->execute([$term, $term, $term, $term]);
            } else {
                $stmt = $pdo->query("SELECT m.* FROM communications_models m ORDER BY m.equipment_type, m.model_name");
            }
        }
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($action === 'create_model') {
        $model_name = trim($_POST['model_name'] ?? '');
        $equipment_type = trim($_POST['equipment_type'] ?? '');
        $brand_id = intval($_POST['brand_id'] ?? 0);
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $characteristics = trim($_POST['characteristics'] ?? '');
        $communication_protocols = trim($_POST['communication_protocols'] ?? '');
        $power_supply = trim($_POST['power_supply'] ?? '');

        if ($model_name === '' || $equipment_type === '') {
            throw new Exception('Model name and equipment type are required');
        }

        $stmt = $pdo->prepare("INSERT INTO communications_models (brand_id, model_name, equipment_type, manufacturer, characteristics, communication_protocols, power_supply) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$brand_id ?: null, $model_name, $equipment_type, $manufacturer ?: null, $characteristics ?: null, $communication_protocols ?: null, $power_supply ?: null]);
        echo json_encode(['success' => true, 'message' => 'Model created successfully', 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'update_model') {
        $id = intval($_POST['id'] ?? 0);
        $model_name = trim($_POST['model_name'] ?? '');
        $equipment_type = trim($_POST['equipment_type'] ?? '');
        $brand_id = intval($_POST['brand_id'] ?? 0);
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $characteristics = trim($_POST['characteristics'] ?? '');
        $communication_protocols = trim($_POST['communication_protocols'] ?? '');
        $power_supply = trim($_POST['power_supply'] ?? '');

        if ($id <= 0 || $model_name === '' || $equipment_type === '') {
            throw new Exception('ID, model name and equipment type are required');
        }

        $stmt = $pdo->prepare("UPDATE communications_models SET brand_id = ?, model_name = ?, equipment_type = ?, manufacturer = ?, characteristics = ?, communication_protocols = ?, power_supply = ? WHERE id = ?");
        $stmt->execute([$brand_id ?: null, $model_name, $equipment_type, $manufacturer ?: null, $characteristics ?: null, $communication_protocols ?: null, $power_supply ?: null, $id]);
        echo json_encode(['success' => true, 'message' => 'Model updated successfully']);
    } elseif ($action === 'delete_model') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID is required');
        $stmt = $pdo->prepare("DELETE FROM communications_models WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Model deleted successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
