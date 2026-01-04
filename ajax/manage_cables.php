<?php

/**
 * Manage Cables (PV Board / Point of Injection) - Admin only
 * Actions:
 *  - list_brands, create_brand, update_brand, delete_brand
 *  - list_models, create_model, update_model, delete_model
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

// Auth: require admin
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

$action = $_REQUEST['action'] ?? 'list_brands';

function ensureCableSchema(PDO $pdo)
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cable_brands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_name` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `cable_models` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `model_name` VARCHAR(255) NOT NULL,
            `brand_id` INT NOT NULL,
            `cable_section` VARCHAR(50) DEFAULT NULL,
            `conductor_material` VARCHAR(100) DEFAULT NULL,
            `insulation_type` VARCHAR(100) DEFAULT NULL,
            `voltage_rating` VARCHAR(50) DEFAULT NULL,
            `temperature_rating` VARCHAR(50) DEFAULT NULL,
            `characteristics` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(`brand_id`),
            CONSTRAINT `fk_cable_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `cable_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {
        // ignore
    }
}

ensureCableSchema($pdo);

try {
    switch ($action) {
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM cable_brands WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM cable_brands ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM cable_brands WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $stmt = $pdo->prepare("INSERT INTO cable_brands (brand_name) VALUES (?)");
                $stmt->execute([$name]);
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $pdo->lastInsertId()]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE cable_brands SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM cable_brands WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Brand deleted']);
                break;
            }
        case 'list_models': {
                $brandId = intval($_GET['brand_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                if ($brandId > 0) {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, cable_section, conductor_material, insulation_type, voltage_rating, temperature_rating, characteristics FROM cable_models WHERE brand_id = ? AND (model_name LIKE ? OR cable_section LIKE ?) ORDER BY model_name");
                        $stmt->execute([$brandId, '%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, cable_section, conductor_material, insulation_type, voltage_rating, temperature_rating, characteristics FROM cable_models WHERE brand_id = ? ORDER BY model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.cable_section, m.conductor_material, m.insulation_type, m.voltage_rating, m.temperature_rating, m.characteristics FROM cable_models m JOIN cable_brands b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? OR m.cable_section LIKE ? ORDER BY b.brand_name, m.model_name");
                        $stmt->execute(['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->query("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.cable_section, m.conductor_material, m.insulation_type, m.voltage_rating, m.temperature_rating, m.characteristics FROM cable_models m JOIN cable_brands b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $section = trim($_POST['cable_section'] ?? '');
                $material = trim($_POST['conductor_material'] ?? '');
                $insulation = trim($_POST['insulation_type'] ?? '');
                $voltage = trim($_POST['voltage_rating'] ?? '');
                $temp = trim($_POST['temperature_rating'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                if ($brandId <= 0 || $modelName === '') throw new Exception('brand_id and model_name required');
                $stmt = $pdo->prepare("INSERT INTO cable_models (brand_id, model_name, cable_section, conductor_material, insulation_type, voltage_rating, temperature_rating, characteristics) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$brandId, $modelName, $section ?: null, $material ?: null, $insulation ?: null, $voltage ?: null, $temp ?: null, $characteristics ?: null]);
                echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $pdo->lastInsertId()]);
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $section = trim($_POST['cable_section'] ?? '');
                $material = trim($_POST['conductor_material'] ?? '');
                $insulation = trim($_POST['insulation_type'] ?? '');
                $voltage = trim($_POST['voltage_rating'] ?? '');
                $temp = trim($_POST['temperature_rating'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $modelName === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE cable_models SET brand_id = ?, model_name = ?, cable_section = ?, conductor_material = ?, insulation_type = ?, voltage_rating = ?, temperature_rating = ?, characteristics = ? WHERE id = ?");
                $stmt->execute([$brandId, $modelName, $section ?: null, $material ?: null, $insulation ?: null, $voltage ?: null, $temp ?: null, $characteristics ?: null, $id]);
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid model id');
                $stmt = $pdo->prepare("DELETE FROM cable_models WHERE id = ?");
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
