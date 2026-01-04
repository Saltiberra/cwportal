<?php

/**
 * Manage Circuit Protection Devices (Circuit Breakers and Differentials) - Admin only
 * Actions:
 *  - list_brands, create_brand, update_brand, delete_brand
 *  - list_models, create_model, update_model, delete_model
 * Requires 'device' param: 'circuit_breaker' | 'differential'
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

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
$device = $_REQUEST['device'] ?? '';

$validDevices = ['circuit_breaker', 'differential'];
if (!in_array($device, $validDevices, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid device']);
    exit;
}

function ensureProtectionSchema(PDO $pdo)
{
    try {
        // Circuit Breakers
        $pdo->exec("CREATE TABLE IF NOT EXISTS `circuit_breaker_brands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_name` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `circuit_breaker_models` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `model_name` VARCHAR(255) NOT NULL,
            `brand_id` INT NOT NULL,
            `characteristics` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(`brand_id`),
            CONSTRAINT `fk_cb_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `circuit_breaker_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Differentials
        $pdo->exec("CREATE TABLE IF NOT EXISTS `differential_brands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_name` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `differential_models` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `model_name` VARCHAR(255) NOT NULL,
            `brand_id` INT NOT NULL,
            `characteristics` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(`brand_id`),
            CONSTRAINT `fk_df_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `differential_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {
        // ignore
    }
}

ensureProtectionSchema($pdo);

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
            error_log("[CIRCUIT_PROTECTION] insertWithIdFallback detected duplicate 0 on {$table}, retrying with id={$nextId}");

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

// Resolve table names based on device
$brandTable = $device === 'circuit_breaker' ? 'circuit_breaker_brands' : 'differential_brands';
$modelTable = $device === 'circuit_breaker' ? 'circuit_breaker_models' : 'differential_models';

try {
    switch ($action) {
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM {$brandTable} WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM {$brandTable} ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM {$brandTable} WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $bid = insertWithIdFallback($pdo, $brandTable, ['brand_name'], [$name]);
                try {
                    logAction('brand_created', $brandTable, $bid, 'Brand created: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $bid]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE {$brandTable} SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                try {
                    logAction('brand_updated', $brandTable, $id, 'Brand updated: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM {$brandTable} WHERE id = ?");
                $stmt->execute([$id]);
                try {
                    logAction('brand_deleted', $brandTable, $id, 'Brand deleted', 'Brand ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Brand deleted']);
                break;
            }
        case 'list_models': {
                $brandId = intval($_GET['brand_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                if ($brandId > 0) {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, characteristics FROM {$modelTable} WHERE brand_id = ? AND model_name LIKE ? ORDER BY model_name");
                        $stmt->execute([$brandId, '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, characteristics FROM {$modelTable} WHERE brand_id = ? ORDER BY model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.characteristics FROM {$modelTable} m JOIN {$brandTable} b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? ORDER BY b.brand_name, m.model_name");
                        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->query("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.characteristics FROM {$modelTable} m JOIN {$brandTable} b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                if ($brandId <= 0 || $modelName === '') throw new Exception('brand_id and model_name required');
                $mid = insertWithIdFallback($pdo, $modelTable, ['brand_id', 'model_name', 'characteristics'], [$brandId, $modelName, $characteristics ?: null]);
                try {
                    logAction('model_created', $modelTable, $mid, 'Model created: ' . $modelName, $modelName);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $mid]);
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $modelName === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE {$modelTable} SET brand_id = ?, model_name = ?, characteristics = ? WHERE id = ?");
                $stmt->execute([$brandId, $modelName, $characteristics ?: null, $id]);
                try {
                    logAction('model_updated', $modelTable, $id, 'Model updated: ' . $modelName, $modelName);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid model id');
                $stmt = $pdo->prepare("DELETE FROM {$modelTable} WHERE id = ?");
                $stmt->execute([$id]);
                try {
                    logAction('model_deleted', $modelTable, $id, 'Model deleted', 'Model ' . $id);
                } catch (Exception $e) {
                }
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
