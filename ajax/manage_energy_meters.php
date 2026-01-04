<?php
// ajax/manage_energy_meters.php
// Admin-only endpoint for Energy Meters Brands & Models management

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

// Auth: require logged-in admin (align with other endpoints)
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

// Ensure schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `energy_meter_brands` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand_name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `energy_meter_models` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand_id` INT NOT NULL,
        `model_name` VARCHAR(255) NOT NULL,
        `characteristics` TEXT DEFAULT NULL,
        `communication_protocol` VARCHAR(100) DEFAULT NULL,
        `voltage_range` VARCHAR(100) DEFAULT NULL,
        `current_range` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(`brand_id`),
        CONSTRAINT `fk_em_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `energy_meter_brands`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // ignore
}

$action = $_REQUEST['action'] ?? 'list_brands';

try {
    switch ($action) {
        // Brands
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM energy_meter_brands WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM energy_meter_brands ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM energy_meter_brands WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $stmt = $pdo->prepare("INSERT INTO energy_meter_brands (brand_name) VALUES (?)");
                $stmt->execute([$name]);
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $pdo->lastInsertId()]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE energy_meter_brands SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM energy_meter_brands WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Brand deleted']);
                break;
            }

            // Models
        case 'list_models': {
                $brandId = intval($_GET['brand_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                if ($brandId > 0) {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM energy_meter_models m JOIN energy_meter_brands b ON b.id = m.brand_id WHERE m.brand_id = ? AND (m.model_name LIKE ? OR b.brand_name LIKE ? OR m.communication_protocol LIKE ?) ORDER BY m.model_name");
                        $term = '%' . $q . '%';
                        $stmt->execute([$brandId, $term, $term, $term]);
                    } else {
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM energy_meter_models m JOIN energy_meter_brands b ON b.id = m.brand_id WHERE m.brand_id = ? ORDER BY m.model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM energy_meter_models m JOIN energy_meter_brands b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? OR m.communication_protocol LIKE ? ORDER BY b.brand_name, m.model_name");
                        $term = '%' . $q . '%';
                        $stmt->execute([$term, $term, $term]);
                    } else {
                        $stmt = $pdo->query("SELECT m.*, b.brand_name FROM energy_meter_models m JOIN energy_meter_brands b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $name = trim($_POST['model_name'] ?? '');
                $char = trim($_POST['characteristics'] ?? '');
                $proto = trim($_POST['communication_protocol'] ?? '');
                $v = trim($_POST['voltage_range'] ?? '');
                $c = trim($_POST['current_range'] ?? '');
                if ($brandId <= 0 || $name === '') throw new Exception('brand_id and model_name required');
                $stmt = $pdo->prepare("INSERT INTO energy_meter_models (brand_id, model_name, characteristics, communication_protocol, voltage_range, current_range) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$brandId, $name, $char ?: null, $proto ?: null, $v ?: null, $c ?: null]);
                echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $pdo->lastInsertId()]);
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $name = trim($_POST['model_name'] ?? '');
                $char = trim($_POST['characteristics'] ?? '');
                $proto = trim($_POST['communication_protocol'] ?? '');
                $v = trim($_POST['voltage_range'] ?? '');
                $c = trim($_POST['current_range'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $name === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE energy_meter_models SET brand_id = ?, model_name = ?, characteristics = ?, communication_protocol = ?, voltage_range = ?, current_range = ? WHERE id = ?");
                $stmt->execute([$brandId, $name, $char ?: null, $proto ?: null, $v ?: null, $c ?: null, $id]);
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid model id');
                $stmt = $pdo->prepare("DELETE FROM energy_meter_models WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Model deleted']);
                break;
            }
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
