<?php
// ajax/manage_procedures_credentials.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure_key.php';
require_once __DIR__ . '/../includes/audit.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'operador';
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
if (!in_array($role, ['admin', 'manager', 'gestor', 'tecnico', 'operador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function jsonError($m, $c = 400)
{
    http_response_code($c);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

function pc_encrypt($plain)
{
    $keyDef = CREDENTIAL_KEY ?? '';
    if (strpos($keyDef, 'base64:') === 0) {
        $key = base64_decode(substr($keyDef, 7));
    } else {
        $key = $keyDef;
    }
    if (!$key || strlen($key) < 16) throw new Exception('Credential key not configured');
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}
function pc_decrypt($blob)
{
    $keyDef = CREDENTIAL_KEY ?? '';
    if (strpos($keyDef, 'base64:') === 0) {
        $key = base64_decode(substr($keyDef, 7));
    } else {
        $key = $keyDef;
    }
    if (!$key || strlen($key) < 16) throw new Exception('Credential key not configured');
    $data = base64_decode($blob);
    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

$action = $_REQUEST['action'] ?? 'list_categories';

try {
    switch ($action) {
        // Categories
        case 'list_categories': {
                $stmt = $pdo->query("SELECT id,name,description,created_at FROM proc_category ORDER BY name");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'create_category': {
                $name = trim($_POST['name'] ?? '');
                if ($name === '') jsonError('name required');
                $desc = trim($_POST['description'] ?? '');
                $stmt = $pdo->prepare("INSERT INTO proc_category (name,description) VALUES (?,?)");
                $stmt->execute([$name, $desc ?: null]);
                $cid = $pdo->lastInsertId();
                try {
                    logAction('category_created', 'proc_category', $cid, 'Category created: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Category created', 'id' => $cid]);
                break;
            }
        case 'update_category': {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if ($id <= 0 || $name === '') jsonError('id,name required');
                $desc = trim($_POST['description'] ?? '');
                $stmt = $pdo->prepare("UPDATE proc_category SET name=?, description=? WHERE id=?");
                $stmt->execute([$name, $desc ?: null, $id]);
                try {
                    logAction('category_updated', 'proc_category', $id, 'Category updated: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Category updated']);
                break;
            }
        case 'delete_category': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("DELETE FROM proc_category WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('category_deleted', 'proc_category', $id, 'Category deleted', 'Category ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Category deleted']);
                break;
            }

            // Procedures (docs)
        case 'list_procedures': {
                $cat = intval($_GET['category_id'] ?? 0);
                $q = trim($_GET['q'] ?? '');
                $params = [];
                $sql = "SELECT p.*, c.name AS category_name FROM procedure_doc p LEFT JOIN proc_category c ON c.id=p.category_id WHERE 1=1";
                if ($cat > 0) {
                    $sql .= " AND p.category_id=?";
                    $params[] = $cat;
                }
                if ($q !== '') {
                    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q + '%';
                }
                $sql .= " ORDER BY p.uploaded_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'upload_procedure': {
                // Placeholder: integrate with actual file upload handling later
                jsonError('upload_procedure not implemented yet', 501);
                break;
            }
        case 'update_procedure': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $version = trim($_POST['version'] ?? '');
                $cat = intval($_POST['category_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE procedure_doc SET title=?, description=?, version=?, category_id=? WHERE id=?");
                $stmt->execute([$title ?: null, $desc ?: null, $version ?: null, $cat ?: null, $id]);
                try {
                    logAction('procedure_updated', 'procedure_doc', $id, 'Procedure updated: ' . $title, $title);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Procedure updated']);
                break;
            }
        case 'toggle_procedure_active': {
                $id = intval($_POST['id'] ?? 0);
                $active = intval($_POST['is_active'] ?? 1);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("UPDATE procedure_doc SET is_active=? WHERE id=?");
                $stmt->execute([$active ? 1 : 0, $id]);
                try {
                    logAction('procedure_status_updated', 'procedure_doc', $id, 'Procedure status updated', 'Procedure ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Status updated']);
                break;
            }
        case 'delete_procedure': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("DELETE FROM procedure_doc WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('procedure_deleted', 'procedure_doc', $id, 'Procedure deleted', 'Procedure ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Procedure deleted']);
                break;
            }

            // Credentials
        case 'list_credentials': {
                $cat = intval($_GET['category_id'] ?? 0);
                $roleF = trim($_GET['role'] ?? '');
                $q = trim($_GET['q'] ?? '');
                $params = [];
                $sql = "SELECT cs.id, cs.category_id, cs.name, cs.username, cs.device_ip, cs.secret_hint, cs.access_role, cs.created_at, cs.updated_at, pc.name AS category_name FROM credential_store cs LEFT JOIN proc_category pc ON cs.category_id = pc.id WHERE 1=1";
                if ($cat > 0) {
                    $sql .= " AND cs.category_id=?";
                    $params[] = $cat;
                }
                if ($roleF !== '') {
                    $sql .= " AND cs.access_role=?";
                    $params[] = $roleF;
                }
                if ($q !== '') {
                    $sql .= " AND (cs.name LIKE ? OR cs.username LIKE ? OR cs.secret_hint LIKE ? OR cs.device_ip LIKE ? )";
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }
                $sql .= " ORDER BY cs.name";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    // Fallback for older DBs that don't have device_ip column
                    if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'device_ip') !== false) {
                        $params = [];
                        // Fallback query without device_ip column
                        $sql = "SELECT cs.id, cs.category_id, cs.name, cs.username, cs.secret_hint, cs.access_role, cs.created_at, cs.updated_at, pc.name AS category_name FROM credential_store cs LEFT JOIN proc_category pc ON cs.category_id = pc.id WHERE 1=1";
                        if ($cat > 0) {
                            $sql .= " AND category_id=?";
                            $params[] = $cat;
                        }
                        if ($roleF !== '') {
                            $sql .= " AND access_role=?";
                            $params[] = $roleF;
                        }
                        if ($q !== '') {
                            $sql .= " AND (name LIKE ? OR username LIKE ? OR secret_hint LIKE ?)";
                            $params[] = '%' . $q . '%';
                            $params[] = '%' . $q . '%';
                            $params[] = '%' . $q . '%';
                        }
                        $sql .= " ORDER BY name";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    } else {
                        throw $e;
                    }
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'get_credential': {
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("SELECT * FROM credential_store WHERE id=?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Not found', 404);
                // Only admins/managers can decrypt by default
                $canDecrypt = in_array($role, ['admin', 'manager', 'gestor']);
                if ($canDecrypt && !empty($row['encrypted_secret'])) {
                    try {
                        $row['secret'] = pc_decrypt($row['encrypted_secret']);
                        // include a hash of the decrypted secret to help debug without leaking plaintext elsewhere
                        if ($row['secret'] !== null) {
                            $row['secret_hash'] = hash('sha256', $row['secret']);
                        } else {
                            $row['secret_hash'] = null;
                        }
                    } catch (Exception $e) {
                        $row['secret'] = null;
                        $row['secret_hash'] = null;
                    }
                } else {
                    $row['secret'] = null;
                    $row['secret_hash'] = null;
                }
                echo json_encode(['success' => true, 'data' => $row]);
                break;
            }
        case 'create_credential': {
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $secret = trim($_POST['secret'] ?? '');
                $hint = trim($_POST['secret_hint'] ?? '');
                $deviceIp = trim($_POST['device_ip'] ?? '');
                $catRaw = trim($_POST['category_id'] ?? '');
                $accRole = trim($_POST['access_role'] ?? 'admin');
                // Resolve category: if numeric id provided, use it; if name provided, create or find it
                $cat = 0;
                if ($catRaw !== '') {
                    if (ctype_digit($catRaw)) {
                        $cat = intval($catRaw);
                    } else {
                        // find existing category by name (case-insensitive)
                        $stmt = $pdo->prepare("SELECT id FROM proc_category WHERE LOWER(name)=LOWER(?) LIMIT 1");
                        $stmt->execute([$catRaw]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['id'])) {
                            $cat = intval($row['id']);
                        } else {
                            // insert new category
                            $stmt = $pdo->prepare("INSERT INTO proc_category (name) VALUES (?)");
                            $stmt->execute([$catRaw]);
                            $cat = $pdo->lastInsertId();
                        }
                    }
                }
                if ($name === '' || $secret === '') jsonError('name,secret required');
                // Validate IP server-side if provided
                if ($deviceIp !== '' && !filter_var($deviceIp, FILTER_VALIDATE_IP)) jsonError('Invalid device_ip');
                $enc = pc_encrypt($secret);
                // Try inserting including device_ip; if column missing, retry without it
                try {
                    $stmt = $pdo->prepare("INSERT INTO credential_store (category_id,name,username,encrypted_secret,secret_hint,device_ip,access_role,created_by) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$cat ?: null, $name, $username ?: null, $enc, $hint ?: null, $deviceIp ?: null, $accRole, $userId]);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'device_ip') !== false) {
                        // retry without device_ip
                        $stmt = $pdo->prepare("INSERT INTO credential_store (category_id,name,username,encrypted_secret,secret_hint,access_role,created_by) VALUES (?,?,?,?,?,?,?)");
                        $stmt->execute([$cat ?: null, $name, $username ?: null, $enc, $hint ?: null, $accRole, $userId]);
                    } else {
                        throw $e;
                    }
                }
                $cid = $pdo->lastInsertId();
                try {
                    logAction('credential_created', 'credential_store', $cid, 'Credential created: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Credential created', 'id' => $cid]);
                break;
            }
        case 'update_credential': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $hint = trim($_POST['secret_hint'] ?? '');
                $deviceIp = trim($_POST['device_ip'] ?? '');
                $catRaw = trim($_POST['category_id'] ?? '');
                $accRole = trim($_POST['access_role'] ?? '');
                // Resolve category for update: if numeric id provided, use it; if name provided, create/find it
                $cat = 0;
                if ($catRaw !== '') {
                    if (ctype_digit($catRaw)) {
                        $cat = intval($catRaw);
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM proc_category WHERE LOWER(name)=LOWER(?) LIMIT 1");
                        $stmt->execute([$catRaw]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['id'])) {
                            $cat = intval($row['id']);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO proc_category (name) VALUES (?)");
                            $stmt->execute([$catRaw]);
                            $cat = $pdo->lastInsertId();
                        }
                    }
                }
                // Determine whether device_ip was provided in the POST (so we don't overwrite when absent)
                $deviceIpPresent = array_key_exists('device_ip', $_POST);
                // Validate IP server-side if provided and non-empty
                if ($deviceIpPresent && $deviceIp !== '' && !filter_var($deviceIp, FILTER_VALIDATE_IP)) jsonError('Invalid device_ip');
                // Capture existing encrypted_secret before update (debug snapshot)
                $old_enc = '';
                try {
                    $s_old = $pdo->prepare("SELECT encrypted_secret FROM credential_store WHERE id=?");
                    $s_old->execute([$id]);
                    $row_old = $s_old->fetch(PDO::FETCH_ASSOC);
                    $old_enc = $row_old['encrypted_secret'] ?? '';
                } catch (Exception $e) {
                    $old_enc = '';
                }
                // Try update: build dynamic SET clause to only include device_ip when the client provided it
                $secret = trim($_POST['secret'] ?? '');
                try {
                    // base sets common to both branches
                    $sets = [];
                    $params = [];
                    $sets[] = 'category_id=?';
                    $params[] = $cat ?: null;
                    $sets[] = 'name=?';
                    $params[] = $name ?: null;
                    $sets[] = 'username=?';
                    $params[] = $username ?: null;
                    $sets[] = 'secret_hint=?';
                    $params[] = $hint ?: null;
                    if ($deviceIpPresent) {
                        // use null when empty to clear explicitly
                        $sets[] = 'device_ip=?';
                        $params[] = ($deviceIp !== '' ? $deviceIp : null);
                    }
                    if ($secret !== '') {
                        $enc = pc_encrypt($secret);
                        $sets[] = 'encrypted_secret=?';
                        $params[] = $enc;
                    }
                    $sets[] = 'access_role=?';
                    $params[] = $accRole ?: 'admin';
                    $params[] = $id;

                    $sql = 'UPDATE credential_store SET ' . implode(', ', $sets) . ' WHERE id=?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $affected = $stmt->rowCount();
                    error_log("[CRED] UPDATE affected rows: $affected for id $id");
                } catch (PDOException $e) {
                    // Fallback for older DBs that don't have device_ip column
                    if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'device_ip') !== false) {
                        // rebuild without device_ip
                        $sets = [];
                        $params = [];
                        $sets[] = 'category_id=?';
                        $params[] = $cat ?: null;
                        $sets[] = 'name=?';
                        $params[] = $name ?: null;
                        $sets[] = 'username=?';
                        $params[] = $username ?: null;
                        $sets[] = 'secret_hint=?';
                        $params[] = $hint ?: null;
                        if ($secret !== '') {
                            $sets[] = 'encrypted_secret=?';
                            $params[] = $enc;
                        }
                        $sets[] = 'access_role=?';
                        $params[] = $accRole ?: 'admin';
                        $params[] = $id;
                        $sql = 'UPDATE credential_store SET ' . implode(', ', $sets) . ' WHERE id=?';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $affected = $stmt->rowCount();
                        error_log("[CRED] UPDATE affected rows fallback: $affected for id $id");
                    } else {
                        throw $e;
                    }
                }
                // SELECT ao valor real gravado
                $stmt = $pdo->prepare("SELECT encrypted_secret FROM credential_store WHERE id=?");
                $stmt->execute([$id]);
                $real_enc = $stmt->fetch(PDO::FETCH_ASSOC)['encrypted_secret'] ?? '';
                $real_len = strlen($real_enc);
                $real_prefix = $real_len ? substr($real_enc, 0, 40) : '';
                $old_len = strlen($old_enc);
                $old_prefix = $old_len ? substr($old_enc, 0, 40) : '';
                $enc_equal = ($old_enc === $real_enc);
                echo json_encode([
                    'success' => true,
                    'message' => 'Credential updated',
                    'secret_updated' => true,
                    'supplied_hash' => hash('sha256', $secret),
                    'old_len' => $old_len,
                    'old_prefix' => $old_prefix,
                    'new_len' => $real_len,
                    'new_prefix' => $real_prefix,
                    'enc_equal' => $enc_equal,
                    'debug_enc_generated' => $enc, // <-- valor encriptado gerado (completo)
                    'real_enc_full' => $real_enc, // <-- valor real lido após update (completo)
                    'affected_rows' => $affected,
                    'db_name' => defined('DB_NAME') ? DB_NAME : null,
                    'db_host' => defined('DB_HOST') ? DB_HOST : null,
                    'db_user' => defined('DB_USER') ? DB_USER : null
                ]);
                break;
            }

        case 'record_access': {
                $cred = intval($_POST['credential_id'] ?? 0);
                // prefer explicit access_action (from client) but fall back to action for backward compatibility
                $actionNote = trim($_POST['access_action'] ?? $_POST['action'] ?? 'view');
                $notes = trim($_POST['notes'] ?? '');
                if ($cred <= 0) jsonError('credential_id required');
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $stmt = $pdo->prepare("INSERT INTO credential_access_log (credential_id,user_id,action,notes,ip_address,user_agent) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$cred, $userId, $actionNote, $notes ?: null, $ip, $ua]);
                echo json_encode(['success' => true, 'message' => 'Access recorded']);
                break;
            }
        case 'delete_credential': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("DELETE FROM credential_store WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('credential_deleted', 'credential_store', $id, 'Credential deleted', 'Credential ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Credential deleted']);
                break;
            }
        case 'access_log': {
                $cred = intval($_GET['credential_id'] ?? 0);
                if ($cred <= 0) jsonError('credential_id required');
                $stmt = $pdo->prepare("SELECT id,user_id,accessed_at,action,notes,ip_address,user_agent FROM credential_access_log WHERE credential_id=? ORDER BY accessed_at DESC");
                $stmt->execute([$cred]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        default:
            jsonError('Unknown action', 404);
    }
} catch (Exception $e) {
    jsonError($e->getMessage());
}
