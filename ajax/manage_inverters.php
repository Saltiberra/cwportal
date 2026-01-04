<?php

/**
 * Manage Inverters (Brands and Models) - Admin only
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

function ensureInverterSchema(PDO $pdo)
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `inverter_brands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_name` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `inverter_models` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_id` INT NOT NULL,
            `model_name` VARCHAR(255) NOT NULL,
            `nominal_power` DECIMAL(10,2) DEFAULT NULL,
            `max_output_current` DECIMAL(10,2) DEFAULT NULL,
            `mppts` INT DEFAULT NULL,
            `strings_per_mppt` INT DEFAULT NULL,
            `datasheet_path` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(`brand_id`),
            CONSTRAINT `fk_inv_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `inverter_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        // ignore
    }
}

ensureInverterSchema($pdo);

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
            error_log("[INVERTERS] insertWithIdFallback detected duplicate 0 on {$table}, retrying with id={$nextId}");

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
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM inverter_brands WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM inverter_brands ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM inverter_brands WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $brandId = insertWithIdFallback($pdo, 'inverter_brands', ['brand_name'], [$name]);
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $brandId]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE inverter_brands SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM inverter_brands WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Brand deleted']);
                break;
            }
        case 'list_models': {
                $brandId = intval($_GET['brand_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                if ($brandId > 0) {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, nominal_power, max_output_current, mppts, strings_per_mppt, datasheet_path FROM inverter_models WHERE brand_id = ? AND (model_name LIKE ? OR nominal_power LIKE ?) ORDER BY model_name");
                        $stmt->execute([$brandId, '%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, nominal_power, max_output_current, mppts, strings_per_mppt, datasheet_path FROM inverter_models WHERE brand_id = ? ORDER BY model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.nominal_power, m.max_output_current, m.mppts, m.strings_per_mppt, m.datasheet_path FROM inverter_models m JOIN inverter_brands b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? OR m.nominal_power LIKE ? ORDER BY b.brand_name, m.model_name");
                        $stmt->execute(['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->query("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.nominal_power, m.max_output_current, m.mppts, m.strings_per_mppt, m.datasheet_path FROM inverter_models m JOIN inverter_brands b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $nominalPower = trim($_POST['nominal_power'] ?? '');
                $maxCurrent = trim($_POST['max_output_current'] ?? '');
                $mppts = intval($_POST['mppts'] ?? 0);
                $stringsPerMppt = intval($_POST['strings_per_mppt'] ?? 0);
                $datasheet = trim($_POST['datasheet_path'] ?? '');
                if ($brandId <= 0 || $modelName === '') throw new Exception('brand_id and model_name required');
                $modelId = insertWithIdFallback($pdo, 'inverter_models', ['brand_id', 'model_name', 'nominal_power', 'max_output_current', 'mppts', 'strings_per_mppt', 'datasheet_path'], [$brandId, $modelName, $nominalPower ?: null, $maxCurrent ?: null, $mppts ?: null, $stringsPerMppt ?: null, $datasheet ?: null]);
                echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $modelId]);
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $nominalPower = trim($_POST['nominal_power'] ?? '');
                $maxCurrent = trim($_POST['max_output_current'] ?? '');
                $mppts = intval($_POST['mppts'] ?? 0);
                $stringsPerMppt = intval($_POST['strings_per_mppt'] ?? 0);
                $datasheet = trim($_POST['datasheet_path'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $modelName === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE inverter_models SET brand_id = ?, model_name = ?, nominal_power = ?, max_output_current = ?, mppts = ?, strings_per_mppt = ?, datasheet_path = ? WHERE id = ?");
                $stmt->execute([$brandId, $modelName, $nominalPower ?: null, $maxCurrent ?: null, $mppts ?: null, $stringsPerMppt ?: null, $datasheet ?: null, $id]);
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid model id');
                $stmt = $pdo->prepare("DELETE FROM inverter_models WHERE id = ?");
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
