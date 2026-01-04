<?php
// ajax/manage_smart_meters.php
// Admin-only endpoint for Smart Meters Brands & Models management

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

// Ensure schema (align with legacy meter_* tables used by commissioning form)
try {
    // Brands table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `meter_brands` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand_name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Models table (legacy table extended with optional columns)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `meter_models` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand_id` INT NOT NULL,
        `model_name` VARCHAR(255) NOT NULL,
        `characteristics` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(`brand_id`),
        CONSTRAINT `fk_meter_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `meter_brands`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add new optional columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE `meter_models` ADD COLUMN `communication_protocols` VARCHAR(255) NULL");
    } catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE `meter_models` ADD COLUMN `power_supply` VARCHAR(100) NULL");
    } catch (Exception $e) {
    }
} catch (Exception $e) {
    // ignore
}

$action = $_REQUEST['action'] ?? 'list_brands';

/**
 * Insert helper with fallback for hosts that may return duplicate entry '0' for PRIMARY
 * Tries normal INSERT then on duplicate '0' error computes MAX(id)+1 and retries with explicit id
 */
function insertWithIdFallback(PDO $pdo, $table, $columns, $values)
{
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $colList = implode(', ', $columns);
    $sql = "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, "Duplicate entry '0' for key 'PRIMARY'") !== false || (strpos($msg, "Duplicate entry '0'") !== false && strpos($msg, 'PRIMARY') !== false)) {
            // compute next id
            $nextStmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM {$table}");
            $nextId = (int)$nextStmt->fetchColumn();
            error_log("[SMART_METERS] insertWithIdFallback detected duplicate 0 on {$table}, retrying with id={$nextId}");

            array_unshift($columns, 'id');
            array_unshift($values, $nextId);

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $colList = implode(', ', $columns);
            $sql2 = "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})";

            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute($values);
            return $nextId;
        }
        throw $e;
    }
}

try {
    switch ($action) {
        // Brands
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM meter_brands WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM meter_brands ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM meter_brands WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $bid = insertWithIdFallback($pdo, 'meter_brands', ['brand_name'], [$name]);
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $bid]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE meter_brands SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM meter_brands WHERE id = ?");
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
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM meter_models m JOIN meter_brands b ON b.id = m.brand_id WHERE m.brand_id = ? AND (m.model_name LIKE ? OR b.brand_name LIKE ? OR m.communication_protocols LIKE ?) ORDER BY m.model_name");
                        $term = '%' . $q . '%';
                        $stmt->execute([$brandId, $term, $term, $term]);
                    } else {
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM meter_models m JOIN meter_brands b ON b.id = m.brand_id WHERE m.brand_id = ? ORDER BY m.model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.*, b.brand_name FROM meter_models m JOIN meter_brands b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? OR m.communication_protocols LIKE ? ORDER BY b.brand_name, m.model_name");
                        $term = '%' . $q . '%';
                        $stmt->execute([$term, $term, $term]);
                    } else {
                        $stmt = $pdo->query("SELECT m.*, b.brand_name FROM meter_models m JOIN meter_brands b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $name = trim($_POST['model_name'] ?? '');
                $protocols = trim($_POST['communication_protocols'] ?? '');
                $power = trim($_POST['power_supply'] ?? '');
                $char = trim($_POST['characteristics'] ?? '');
                if ($brandId <= 0 || $name === '') throw new Exception('brand_id and model_name required');
                $mid = insertWithIdFallback($pdo, 'meter_models', ['brand_id', 'model_name', 'communication_protocols', 'power_supply', 'characteristics'], [$brandId, $name, $protocols ?: null, $power ?: null, $char ?: null]);
                echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $mid]);
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $name = trim($_POST['model_name'] ?? '');
                $protocols = trim($_POST['communication_protocols'] ?? '');
                $power = trim($_POST['power_supply'] ?? '');
                $char = trim($_POST['characteristics'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $name === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE meter_models SET brand_id = ?, model_name = ?, communication_protocols = ?, power_supply = ?, characteristics = ? WHERE id = ?");
                $stmt->execute([$brandId, $name, $protocols ?: null, $power ?: null, $char ?: null, $id]);
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid model id');
                $stmt = $pdo->prepare("DELETE FROM meter_models WHERE id = ?");
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
