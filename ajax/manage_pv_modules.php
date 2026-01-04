<?php

/**
 * Manage PV Modules (Brands and Models) - Admin only
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

function ensurePvModuleSchema(PDO $pdo)
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `pv_module_brands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_name` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `pv_module_models` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `brand_id` INT NOT NULL,
            `model_name` VARCHAR(255) NOT NULL,
            `characteristics` TEXT DEFAULT NULL,
            `power_options` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(`brand_id`),
            CONSTRAINT `fk_pv_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `pv_module_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        // ignore
    }
}

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
            error_log("[PV_MODULES] insertWithIdFallback detected duplicate 0 on {$table}, retrying with id={$nextId}");

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

ensurePvModuleSchema($pdo);

try {
    switch ($action) {
        case 'list_brands': {
                $q = trim($_GET['q'] ?? '');
                if ($q !== '') {
                    $stmt = $pdo->prepare("SELECT id, brand_name FROM pv_module_brands WHERE brand_name LIKE ? ORDER BY brand_name");
                    $stmt->execute(['%' . $q . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, brand_name FROM pv_module_brands ORDER BY brand_name");
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_brand': {
                $name = trim($_POST['brand_name'] ?? '');
                if ($name === '') throw new Exception('Brand name required');
                $dup = $pdo->prepare("SELECT id FROM pv_module_brands WHERE brand_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) throw new Exception('Brand already exists');
                $stmt = $pdo->prepare("INSERT INTO pv_module_brands (brand_name) VALUES (?)");
                $stmt->execute([$name]);
                echo json_encode(['success' => true, 'message' => 'Brand created', 'id' => $pdo->lastInsertId()]);
                break;
            }
        case 'update_brand': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['brand_name'] ?? '');
                if ($id <= 0 || $name === '') throw new Exception('Invalid brand update');
                $stmt = $pdo->prepare("UPDATE pv_module_brands SET brand_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Brand updated']);
                break;
            }
        case 'delete_brand': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid brand id');
                $stmt = $pdo->prepare("DELETE FROM pv_module_brands WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Brand deleted']);
                break;
            }
        case 'list_models': {
                $brandId = intval($_GET['brand_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                if ($brandId > 0) {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, power_options, characteristics FROM pv_module_models WHERE brand_id = ? AND model_name LIKE ? ORDER BY model_name");
                        $stmt->execute([$brandId, '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->prepare("SELECT id, brand_id, model_name, power_options, characteristics FROM pv_module_models WHERE brand_id = ? ORDER BY model_name");
                        $stmt->execute([$brandId]);
                    }
                } else {
                    if ($q !== '') {
                        $stmt = $pdo->prepare("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.power_options, m.characteristics FROM pv_module_models m JOIN pv_module_brands b ON b.id = m.brand_id WHERE m.model_name LIKE ? OR b.brand_name LIKE ? ORDER BY b.brand_name, m.model_name");
                        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
                    } else {
                        $stmt = $pdo->query("SELECT m.id, m.brand_id, b.brand_name, m.model_name, m.power_options, m.characteristics FROM pv_module_models m JOIN pv_module_brands b ON b.id = m.brand_id ORDER BY b.brand_name, m.model_name");
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_model': {
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                $powerOptions = trim($_POST['power_options'] ?? '');
                if ($brandId <= 0 || $modelName === '' || $powerOptions === '') throw new Exception('brand_id, model_name and power_options required');
                // Use fallback insertion helper to avoid duplicate 0 primary errors
                try {
                    error_log("[PV_MODULES] create_model attempt: brand_id={$brandId}, model_name={$modelName}");
                    $modelId = insertWithIdFallback($pdo, 'pv_module_models', ['brand_id', 'model_name', 'characteristics', 'power_options'], [$brandId, $modelName, $characteristics, $powerOptions]);
                    echo json_encode(['success' => true, 'message' => 'Model created', 'id' => $modelId]);
                } catch (PDOException $e) {
                    error_log('[PV_MODULES] create_model PDOException: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }
                break;
            }
        case 'update_model': {
                $id = intval($_POST['id'] ?? 0);
                $brandId = intval($_POST['brand_id'] ?? 0);
                $modelName = trim($_POST['model_name'] ?? '');
                $characteristics = trim($_POST['characteristics'] ?? '');
                $powerOptions = trim($_POST['power_options'] ?? '');
                if ($id <= 0 || $brandId <= 0 || $modelName === '') throw new Exception('Invalid model update');
                $stmt = $pdo->prepare("UPDATE pv_module_models SET brand_id = ?, model_name = ?, characteristics = ?, power_options = ? WHERE id = ?");
                $stmt->execute([$brandId, $modelName, $characteristics, $powerOptions, $id]);
                echo json_encode(['success' => true, 'message' => 'Model updated']);
                break;
            }
        case 'delete_model': {
                $id = intval($_POST['id'] ?? 0);
                // If id is not valid, attempt to delete by brand_id + model_name as a fallback
                if ($id <= 0) {
                    $brandId = intval($_POST['brand_id'] ?? 0);
                    $modelName = trim($_POST['model_name'] ?? '');
                    if ($brandId > 0 && $modelName !== '') {
                        // Attempt to delete any row matching brand+model
                        error_log("[PV_MODULES] delete_model fallback: brand_id={$brandId}, model_name={$modelName}");
                        $stmt = $pdo->prepare("DELETE FROM pv_module_models WHERE brand_id = ? AND model_name = ?");
                        $stmt->execute([$brandId, $modelName]);
                        if ($stmt->rowCount() > 0) {
                            echo json_encode(['success' => true, 'message' => 'Model deleted (matched by brand and name)']);
                            break;
                        } else {
                            throw new Exception('No model found matching brand and name');
                        }
                    }
                    throw new Exception('Invalid model id');
                }

                $stmt = $pdo->prepare("DELETE FROM pv_module_models WHERE id = ?");
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
