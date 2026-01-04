<?php
// ajax/manage_field_supervision.php
// Admin / authorized endpoint for Field Supervision module
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'operador';
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
// Authorization: technicians or admins. Adjust as needed.
if (!in_array($role, ['admin', 'tecnico', 'operador', 'gestor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized role']);
    exit;
}

$action = $_REQUEST['action'] ?? 'list_visits';

// NOTE: Schema expected from migration file. Here we do minimal existence check (no create).

if (!function_exists('jsonError')) {
    function jsonError($msg, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
}

// Helpers: check if table/column exists in the current DB
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table)
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('columnExists')) {
    function columnExists($pdo, $table, $column)
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Simple URL validation helper (allow empty values)
if (!function_exists('isValidUrl')) {
    function isValidUrl($url)
    {
        if ($url === null || trim($url) === '') return true;
        if (strlen($url) > 1024) return false;
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

try {
    switch ($action) {
        case 'list_visits': {
                $type = trim($_GET['type'] ?? '');
                $status = trim($_GET['status'] ?? '');
                $q = trim($_GET['q'] ?? '');
                $projectId = trim($_GET['project_id'] ?? '');
                $params = [];
                // Use project join only if both project table and project_id column exist
                $hasProject = columnExists($pdo, 'field_visit', 'project_id') && tableExists($pdo, 'field_supervision_project');
                if ($hasProject) {
                    $sql = "SELECT fv.id,fv.type,fv.title,fv.supervisor_user_id,fv.project_id,fv.date_start,fv.date_end,fv.status,fv.severity,fv.created_at,fv.updated_at, p.title as project_title FROM field_visit fv LEFT JOIN field_supervision_project p ON fv.project_id = p.id WHERE 1=1";
                } else {
                    $sql = "SELECT id,type,title,supervisor_user_id,date_start,date_end,status,severity,created_at,updated_at FROM field_visit WHERE 1=1";
                }
                if ($type !== '') {
                    $sql .= " AND type = ?";
                    $params[] = $type;
                }
                if ($status !== '') {
                    $sql .= " AND status = ?";
                    $params[] = $status;
                }
                if ($projectId !== '' && $hasProject) {
                    $sql .= " AND fv.project_id = ?";
                    $params[] = $projectId;
                }
                if ($q !== '') {
                    $sql .= " AND (title LIKE ? OR description LIKE ?)";
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }
                $sql .= " ORDER BY date_start DESC";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    // fallback: project table may not exist, try a simpler query without join
                    $sql = "SELECT id,type,title,supervisor_user_id,project_id,date_start,date_end,status,severity,created_at,updated_at FROM field_visit WHERE 1=1";
                    if ($type !== '') {
                        $sql .= " AND type = ?";
                    }
                    if ($status !== '') {
                        $sql .= " AND status = ?";
                    }
                    if ($projectId !== '') {
                        $sql .= " AND project_id = ?";
                    }
                    if ($q !== '') {
                        $sql .= " AND (title LIKE ? OR description LIKE ?)";
                    }
                    $sql .= " ORDER BY date_start DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'get_visit': {
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                $stmt = $pdo->prepare("SELECT * FROM field_visit WHERE id = ?");
                $stmt->execute([$id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$v) jsonError('Not found', 404);
                echo json_encode(['success' => true, 'data' => $v]);
                break;
            }
        case 'create_visit': {
                $type = trim($_POST['type'] ?? '');
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $start = trim($_POST['date_start'] ?? '');
                if ($type === '' || $title === '' || $start === '') jsonError('type,title,date_start required');
                $severity = trim($_POST['severity'] ?? '');
                if ($severity === '') $severity = null;
                $stmt = $pdo->prepare("INSERT INTO field_visit (type,title,description,supervisor_user_id,date_start,status,severity) VALUES (?,?,?,?,?, 'open',?)");
                $stmt->execute([$type, $title, $desc ?: null, $userId, $start, $severity]);
                $vid = $pdo->lastInsertId();
                // Audit log
                try {
                    logAction('visit_created', 'field_visit', $vid, "Visit created: $title", $title);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Visit created', 'id' => $vid]);
                break;
            }
        case 'update_visit': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $status = trim($_POST['status'] ?? '');
                $severity = trim($_POST['severity'] ?? '');
                if ($severity === '') $severity = null;
                $stmt = $pdo->prepare("UPDATE field_visit SET title=?, description=?, status=?, severity=? WHERE id=?");
                $stmt->execute([$title ?: null, $desc ?: null, $status ?: 'open', $severity, $id]);
                try {
                    logAction('visit_edited', 'field_visit', $id, "Visit updated: $title", $title);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Visit updated']);
                break;
            }
        case 'add_note': {
                $visitId = intval($_POST['visit_id'] ?? 0);
                $text = trim($_POST['note_text'] ?? '');
                if ($visitId <= 0 || $text === '') jsonError('visit_id,note_text required');
                $stmt = $pdo->prepare("INSERT INTO field_visit_note (visit_id,note_text,author_user_id) VALUES (?,?,?)");
                $stmt->execute([$visitId, $text, $userId]);
                $nid = $pdo->lastInsertId();
                try {
                    logAction('visit_note_added', 'field_visit_note', $nid, "Note added to visit $visitId", 'Visit ' . $visitId);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Note added', 'id' => $nid]);
                break;
            }
        case 'update_note': {
                $id = intval($_POST['id'] ?? 0);
                $text = trim($_POST['note_text'] ?? '');
                if ($id <= 0 || $text === '') jsonError('id,note_text required');
                // Verificar se a nota existe e pertence ao utilizador (opcional: ou admin)
                $stmt = $pdo->prepare("SELECT author_user_id FROM field_visit_note WHERE id=?");
                $stmt->execute([$id]);
                $note = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$note) jsonError('Note not found');
                // Permitir edição apenas pelo autor ou admin
                if ($note['author_user_id'] != $userId && !in_array($role, ['admin', 'super_admin'])) {
                    jsonError('Not authorized to edit this note');
                }
                $stmt = $pdo->prepare("UPDATE field_visit_note SET note_text=? WHERE id=?");
                $stmt->execute([$text, $id]);
                try {
                    logAction('visit_note_updated', 'field_visit_note', $id, 'Visit note updated', 'Note ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Note updated']);
                break;
            }
        case 'list_notes': {
                $visitId = intval($_GET['visit_id'] ?? 0);
                if ($visitId <= 0) jsonError('visit_id required');
                $stmt = $pdo->prepare("SELECT id,note_text,author_user_id,created_at FROM field_visit_note WHERE visit_id=? ORDER BY created_at DESC");
                $stmt->execute([$visitId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'add_action_item': {
                $visitId = intval($_POST['visit_id'] ?? 0);
                $desc = trim($_POST['description'] ?? '');
                $resp = intval($_POST['responsible_user_id'] ?? 0);
                $due = trim($_POST['due_date'] ?? '');
                if ($visitId <= 0 || $desc === '' || $resp <= 0) jsonError('visit_id,description,responsible_user_id required');
                $stmt = $pdo->prepare("INSERT INTO field_visit_action_item (visit_id,description,responsible_user_id,due_date) VALUES (?,?,?,?)");
                $stmt->execute([$visitId, $desc, $resp, $due ?: null]);
                $aiId = $pdo->lastInsertId();
                try {
                    logAction('action_item_added', 'field_visit_action_item', $aiId, 'Action item added: ' . $desc, 'Action Item ' . $aiId);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Action item added', 'id' => $aiId]);
                break;
            }
        case 'list_action_items': {
                $visitId = intval($_GET['visit_id'] ?? 0);
                if ($visitId <= 0) jsonError('visit_id required');
                $stmt = $pdo->prepare("SELECT id,description,responsible_user_id,due_date,status,created_at,updated_at FROM field_visit_action_item WHERE visit_id=? ORDER BY created_at DESC");
                $stmt->execute([$visitId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'update_action_item_status': {
                $id = intval($_POST['id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                if ($id <= 0 || $status === '') jsonError('id,status required');
                $stmt = $pdo->prepare("UPDATE field_visit_action_item SET status=? WHERE id=?");
                $stmt->execute([$status, $id]);
                try {
                    logAction('action_item_status_updated', 'field_visit_action_item', $id, 'Action item status updated to ' . $status, 'Action Item ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Status updated']);
                break;
            }
        case 'delete_action_item': {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                $stmt = $pdo->prepare("DELETE FROM field_visit_action_item WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('action_item_deleted', 'field_visit_action_item', $id, 'Action item deleted', 'Action Item ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Deleted']);
                break;
            }

        case 'delete_visit': {
                if (!tableExists($pdo, 'field_visit')) {
                    jsonError('Visits feature is not available. Please run DB migration to enable Visits feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                // Check permission: admin/gestor OR supervisor_user_id
                $stmt = $pdo->prepare("SELECT id, supervisor_user_id FROM field_visit WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Not found', 404);
                $sup = intval($row['supervisor_user_id'] ?? 0);
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $sup) {
                    jsonError('Permission denied: only admin/gestor or visit supervisor can delete visit', 403);
                }
                // delete related attachments (if any)
                if (tableExists($pdo, 'field_visit_attachment')) {
                    try {
                        $stmtA = $pdo->prepare("SELECT file_path FROM field_visit_attachment WHERE visit_id = ?");
                        $stmtA->execute([$id]);
                        while ($a = $stmtA->fetch(PDO::FETCH_ASSOC)) {
                            if (!empty($a['file_path']) && file_exists($a['file_path'])) unlink($a['file_path']);
                        }
                        $stmt = $pdo->prepare("DELETE FROM field_visit_attachment WHERE visit_id = ?");
                        $stmt->execute([$id]);
                    } catch (Throwable $e) {
                        // ignore attachments delete errors
                    }
                }
                $stmt = $pdo->prepare("DELETE FROM field_visit WHERE id = ?");
                $stmt->execute([$id]);
                try {
                    logAction('visit_deleted', 'field_visit', $id, 'Visit deleted', 'Visit ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Visit deleted']);
                break;
            }

            /* ------------------------- Projects / timeline / problems ------------------------- */
        case 'list_projects': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                $q = trim($_GET['q'] ?? '');
                $params = [];
                $selectCols = "id,title,description,supervisor_user_id,start_date,end_date,status,phase,created_at,updated_at";
                if (columnExists($pdo, 'field_supervision_project', 'project_plan_url')) $selectCols .= ", project_plan_url";
                if (columnExists($pdo, 'field_supervision_project', 'pv_solution_url')) $selectCols .= ", pv_solution_url";
                if (columnExists($pdo, 'field_supervision_project', 'sld_url')) $selectCols .= ", sld_url";
                $sql = "SELECT $selectCols FROM field_supervision_project WHERE 1=1";
                if ($q !== '') {
                    $sql .= " AND (title LIKE ? OR description LIKE ?)";
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }
                $sql .= " ORDER BY created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'get_project_link_columns': {
                // Return existence of optional project link columns
                $tableExists = tableExists($pdo, 'field_supervision_project');
                if (!$tableExists) jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                $res = [
                    'project_plan_url' => columnExists($pdo, 'field_supervision_project', 'project_plan_url'),
                    'pv_solution_url' => columnExists($pdo, 'field_supervision_project', 'pv_solution_url'),
                    'sld_url' => columnExists($pdo, 'field_supervision_project', 'sld_url'),
                ];
                echo json_encode(['success' => true, 'data' => $res]);
                break;
            }
        case 'get_project': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                // include new columns if present
                $selectCols = "*";
                // Use * by default to keep backward compat, get_project returns all columns available
                $stmt = $pdo->prepare("SELECT $selectCols FROM field_supervision_project WHERE id = ?");
                $stmt->execute([$id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$p) jsonError('Not found', 404);
                echo json_encode(['success' => true, 'data' => $p]);
                break;
            }
        case 'create_project': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $start = trim($_POST['start_date'] ?? '');
                $end = trim($_POST['end_date'] ?? '');
                $phase = trim($_POST['phase'] ?? '');
                $status = trim($_POST['status'] ?? 'planned');
                // validate status value
                $allowedStatuses = ['planned', 'active', 'on_hold', 'completed', 'cancelled'];
                if (!in_array($status, $allowedStatuses)) jsonError('Invalid status value');
                if ($title === '') jsonError('title required');
                // Validate URL inputs (if provided)
                $postedPlan = isset($_POST['project_plan_url']) ? trim($_POST['project_plan_url']) : '';
                $postedPv = isset($_POST['pv_solution_url']) ? trim($_POST['pv_solution_url']) : '';
                $postedSld = isset($_POST['sld_url']) ? trim($_POST['sld_url']) : '';
                if ($postedPlan !== '' && !isValidUrl($postedPlan)) jsonError('Invalid Project Plan URL');
                if ($postedPv !== '' && !isValidUrl($postedPv)) jsonError('Invalid PV Solution URL');
                if ($postedSld !== '' && !isValidUrl($postedSld)) jsonError('Invalid SLD URL');

                // Build insert with optional URL columns if present
                $cols = ["title", "description", "supervisor_user_id", "start_date", "end_date", "status", "phase"];
                $vals = ["?", "?", "?", "?", "?", "?", "?"];
                $params = [$title, $desc ?: null, $userId, $start ?: null, $end ?: null, $status, $phase ?: null];
                $migrationNeeded = false;
                if (columnExists($pdo, 'field_supervision_project', 'project_plan_url')) {
                    $cols[] = 'project_plan_url';
                    $vals[] = '?';
                    $params[] = trim($_POST['project_plan_url'] ?? '') ?: null;
                }
                if (columnExists($pdo, 'field_supervision_project', 'pv_solution_url')) {
                    $cols[] = 'pv_solution_url';
                    $vals[] = '?';
                    $params[] = trim($_POST['pv_solution_url'] ?? '') ?: null;
                }
                if (columnExists($pdo, 'field_supervision_project', 'sld_url')) {
                    $cols[] = 'sld_url';
                    $vals[] = '?';
                    $params[] = trim($_POST['sld_url'] ?? '') ?: null;
                }
                $sqlIns = "INSERT INTO field_supervision_project (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $stmt = $pdo->prepare($sqlIns);
                $stmt->execute($params);
                $id = $pdo->lastInsertId();
                try {
                    logAction('project_created', 'field_supervision_project', $id, 'Project created: ' . $title, $title);
                } catch (Exception $e) {
                }
                // If user passed any links but columns were not present, return a migration hint
                if (($postedPlan !== '' && !columnExists($pdo, 'field_supervision_project', 'project_plan_url')) ||
                    ($postedPv !== '' && !columnExists($pdo, 'field_supervision_project', 'pv_solution_url')) ||
                    ($postedSld !== '' && !columnExists($pdo, 'field_supervision_project', 'sld_url'))
                ) {
                    $migrationNeeded = true;
                }
                echo json_encode(['success' => true, 'message' => 'Project created', 'id' => $id, 'migration_needed' => $migrationNeeded]);
                break;
            }
        case 'update_project': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $start = trim($_POST['start_date'] ?? '');
                $end = trim($_POST['end_date'] ?? '');
                $phase = trim($_POST['phase'] ?? '');
                $status = trim($_POST['status'] ?? 'planned');
                // validate status value
                $allowedStatuses = ['planned', 'active', 'on_hold', 'completed', 'cancelled'];
                if (!in_array($status, $allowedStatuses)) jsonError('Invalid status value');
                // permission check: only allow admins, gestores, or the project's supervisor
                if (!in_array($role, ['admin', 'gestor'])) {
                    // fetch project and ensure current user is its supervisor
                    $stmtCheck = $pdo->prepare("SELECT supervisor_user_id FROM field_supervision_project WHERE id = ?");
                    $stmtCheck->execute([$id]);
                    $projData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if (!$projData) jsonError('Not found', 404);
                    $supervisor = intval($projData['supervisor_user_id'] ?? 0);
                    if ($supervisor !== (int)$userId) {
                        jsonError('Permission denied: only project supervisor or admin/gestor can update project', 403);
                    }
                }
                // Validate URL inputs (if provided)
                $postedPlan = isset($_POST['project_plan_url']) ? trim($_POST['project_plan_url']) : '';
                $postedPv = isset($_POST['pv_solution_url']) ? trim($_POST['pv_solution_url']) : '';
                $postedSld = isset($_POST['sld_url']) ? trim($_POST['sld_url']) : '';
                if ($postedPlan !== '' && !isValidUrl($postedPlan)) jsonError('Invalid Project Plan URL');
                if ($postedPv !== '' && !isValidUrl($postedPv)) jsonError('Invalid PV Solution URL');
                if ($postedSld !== '' && !isValidUrl($postedSld)) jsonError('Invalid SLD URL');

                // Build update with optional URL columns
                $updates = ["title=?", "description=?", "start_date=?", "end_date=?", "status=?", "phase=?"];
                $params = [$title, $desc ?: null, $start ?: null, $end ?: null, $status, $phase ?: null];
                $migrationNeeded = false;
                if (columnExists($pdo, 'field_supervision_project', 'project_plan_url')) {
                    $updates[] = 'project_plan_url=?';
                    $params[] = trim($_POST['project_plan_url'] ?? '') ?: null;
                }
                if (columnExists($pdo, 'field_supervision_project', 'pv_solution_url')) {
                    $updates[] = 'pv_solution_url=?';
                    $params[] = trim($_POST['pv_solution_url'] ?? '') ?: null;
                }
                if (columnExists($pdo, 'field_supervision_project', 'sld_url')) {
                    $updates[] = 'sld_url=?';
                    $params[] = trim($_POST['sld_url'] ?? '') ?: null;
                }
                $params[] = $id;
                $sqlUp = "UPDATE field_supervision_project SET " . implode(', ', $updates) . " WHERE id=?";
                $stmt = $pdo->prepare($sqlUp);
                $stmt->execute($params);
                try {
                    logAction('project_updated', 'field_supervision_project', $id, 'Project updated: ' . $title, $title);
                } catch (Exception $e) {
                }
                if (($postedPlan !== '' && !columnExists($pdo, 'field_supervision_project', 'project_plan_url')) ||
                    ($postedPv !== '' && !columnExists($pdo, 'field_supervision_project', 'pv_solution_url')) ||
                    ($postedSld !== '' && !columnExists($pdo, 'field_supervision_project', 'sld_url'))
                ) {
                    $migrationNeeded = true;
                }
                echo json_encode(['success' => true, 'message' => 'Project updated', 'migration_needed' => $migrationNeeded]);
                break;
            }
        case 'update_project_status': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                if ($id <= 0 || $status === '') jsonError('id and status required');
                // validate status
                $allowedStatuses = ['planned', 'active', 'on_hold', 'completed', 'cancelled'];
                if (!in_array($status, $allowedStatuses)) jsonError('Invalid status value');
                // permission check: only admins, gestores or project supervisor
                if (!in_array($role, ['admin', 'gestor'])) {
                    $stmtCheck = $pdo->prepare("SELECT supervisor_user_id FROM field_supervision_project WHERE id = ?");
                    $stmtCheck->execute([$id]);
                    $projData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if (!$projData) jsonError('Not found', 404);
                    $supervisor = intval($projData['supervisor_user_id'] ?? 0);
                    if ($supervisor !== (int)$userId) jsonError('Permission denied: only project supervisor or admin/gestor can update project status', 403);
                }
                $stmt = $pdo->prepare("UPDATE field_supervision_project SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                try {
                    logAction('project_status_updated', 'field_supervision_project', $id, 'Project status updated to ' . $status, 'Project ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Project status updated']);
                break;
            }
        case 'delete_project': {
                if (!tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Projects feature.', 501);
                }
                // Only admins or managers can delete projects
                if (!in_array($role, ['admin', 'gestor'])) {
                    jsonError('Permission denied: Only admin or gestor can delete projects', 403);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                try {
                    $pdo->beginTransaction();

                    // Delete related visits, problems, timeline entries and contacts
                    if (tableExists($pdo, 'field_visit') && columnExists($pdo, 'field_visit', 'project_id')) {
                        $pdo->prepare("DELETE FROM field_visit WHERE project_id = ?")->execute([$id]);
                    }
                    if (tableExists($pdo, 'field_supervision_problem')) {
                        $pdo->prepare("DELETE FROM field_supervision_problem WHERE project_id = ?")->execute([$id]);
                    }
                    if (tableExists($pdo, 'field_supervision_timeline')) {
                        $pdo->prepare("DELETE FROM field_supervision_timeline WHERE project_id = ?")->execute([$id]);
                    }
                    if (tableExists($pdo, 'field_supervision_contact')) {
                        $pdo->prepare("DELETE FROM field_supervision_contact WHERE project_id = ?")->execute([$id]);
                    }

                    // Delete the project
                    // Fetch project title to remove any schedules that reference it
                    $stmtGet = $pdo->prepare("SELECT title FROM field_supervision_project WHERE id = ?");
                    $stmtGet->execute([$id]);
                    $projectTitle = $stmtGet->fetchColumn();

                    // Remove schedule entries that reference the project by title
                    if ($projectTitle) {
                        try {
                            $pdo->prepare("DELETE FROM schedules WHERE project_name = ? OR title LIKE ?")->execute([$projectTitle, '%' . $projectTitle . '%']);
                        } catch (Throwable $sdel) {
                            // Not fatal, but log for system admin
                            error_log('[manage_field_supervision.php] Failed to delete schedules referencing project: ' . $sdel->getMessage());
                        }
                    }

                    $stmt = $pdo->prepare("DELETE FROM field_supervision_project WHERE id=?");
                    $stmt->execute([$id]);

                    // Audit
                    try {
                        logAction('project_deleted', 'field_supervision_project', $id, 'Project deleted with related data and schedules removed', $projectTitle ?: 'Project ' . $id);
                    } catch (Exception $e) {
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Project and related data deleted']);
                } catch (Throwable $e) {
                    try {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                    } catch (Throwable $rb) {
                    }
                    error_log('[manage_field_supervision.php] Error deleting project: ' . $e->getMessage());
                    jsonError('Error deleting project');
                }
                break;
            }
        case 'list_project_timeline': {
                $projectId = intval($_GET['project_id'] ?? 0);
                if ($projectId <= 0) jsonError('project_id required');

                // Build a timeline that includes explicit timeline rows and entries derived from problems and visits
                $parts = [];
                $params = [];
                // timeline entries
                if (tableExists($pdo, 'field_supervision_timeline')) {
                    // Optional: include notes only if include_notes param is provided (default behavior excludes notes)
                    $includeNotes = isset($_GET['include_notes']) && ($_GET['include_notes'] === '1' || $_GET['include_notes'] === 'true');
                    // debug removed - includeNotes checked
                    if ($includeNotes) {
                        $parts[] = "SELECT CONCAT('t', id) AS id, entry_type, CONVERT(entry_text USING utf8mb4) AS entry_text, created_by, created_at, 'timeline' as origin FROM field_supervision_timeline WHERE project_id = ?";
                    } else {
                        $parts[] = "SELECT CONCAT('t', id) AS id, entry_type, CONVERT(entry_text USING utf8mb4) AS entry_text, created_by, created_at, 'timeline' as origin FROM field_supervision_timeline WHERE project_id = ? AND entry_type <> 'note'";
                    }
                    $params[] = $projectId;
                }
                // problem entries
                if (tableExists($pdo, 'field_supervision_problem')) {
                    // Use full problem description when available, otherwise fallback to title
                    $parts[] = "SELECT CONCAT('p', id) AS id, 'problem' AS entry_type, CONVERT(COALESCE(NULLIF(description,''), CONCAT('Problem reported: ', COALESCE(title,''))) USING utf8mb4) AS entry_text, reported_by AS created_by, created_at, 'problem' as origin FROM field_supervision_problem WHERE project_id = ?";
                    $params[] = $projectId;
                }
                // visits entries (if visit table exists and has project_id column)
                if (tableExists($pdo, 'field_visit') && columnExists($pdo, 'field_visit', 'project_id')) {
                    // Use visit description when available, otherwise fallback to title
                    $parts[] = "SELECT CONCAT('v', id) AS id, 'visit' AS entry_type, CONVERT(COALESCE(NULLIF(description,''), CONCAT('Visit: ', COALESCE(title,''))) USING utf8mb4) AS entry_text, supervisor_user_id AS created_by, date_start AS created_at, 'visit' as origin FROM field_visit WHERE project_id = ?";
                    $params[] = $projectId;
                }
                if (empty($parts)) {
                    jsonError('Timeline feature not available on this database', 501);
                }
                $sql = implode("\nUNION ALL\n", $parts) . "\nORDER BY created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'list_project_notes': {
                $projectId = intval($_GET['project_id'] ?? 0);
                if ($projectId <= 0) jsonError('project_id required');
                if (!tableExists($pdo, 'field_supervision_timeline')) jsonError('Field Supervision module not initialised. Please run the DB migration to enable Timeline feature.', 501);
                $stmt = $pdo->prepare("SELECT id, entry_text, created_by, created_at FROM field_supervision_timeline WHERE project_id = ? AND entry_type = 'note' ORDER BY created_at DESC");
                $stmt->execute([$projectId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'delete_project_timeline': {
                if (!tableExists($pdo, 'field_supervision_timeline')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Timeline feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                // Check permission: admin/gestor OR created_by OR project supervisor
                $stmt = $pdo->prepare("SELECT t.id, t.created_by, p.supervisor_user_id FROM field_supervision_timeline t LEFT JOIN field_supervision_project p ON t.project_id = p.id WHERE t.id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Not found', 404);
                $createdBy = intval($row['created_by'] ?? 0);
                $projSupervisor = intval($row['supervisor_user_id'] ?? 0);
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $createdBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied: only admin/gestor, project supervisor or author can delete timeline entries', 403);
                }
                $stmt = $pdo->prepare("DELETE FROM field_supervision_timeline WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('timeline_entry_deleted', 'field_supervision_timeline', $id, 'Timeline entry deleted', 'Timeline ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Timeline entry deleted']);
                break;
            }
        case 'add_project_timeline': {
                if (!tableExists($pdo, 'field_supervision_timeline')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Timeline feature.', 501);
                }
                $projectId = intval($_POST['project_id'] ?? 0);
                $type = trim($_POST['entry_type'] ?? 'note');
                $text = trim($_POST['entry_text'] ?? '');
                if ($projectId <= 0 || $text === '') jsonError('project_id,entry_text required');
                $stmt = $pdo->prepare("INSERT INTO field_supervision_timeline (project_id,entry_type,entry_text,created_by) VALUES (?,?,?,?)");
                $stmt->execute([$projectId, $type, $text, $userId]);
                $tid = $pdo->lastInsertId();
                try {
                    logAction('timeline_entry_added', 'field_supervision_timeline', $tid, 'Timeline entry added', 'Project ' . $projectId);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Timeline entry added', 'id' => $tid]);
                break;
            }
        case 'update_project_note': {
                if (!tableExists($pdo, 'field_supervision_timeline')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Timeline feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                $text = trim($_POST['entry_text'] ?? '');
                if ($id <= 0 || $text === '') jsonError('id,entry_text required');
                // Verificar se a nota existe e verificar permissões
                $stmt = $pdo->prepare("SELECT t.created_by, t.entry_type, p.supervisor_user_id FROM field_supervision_timeline t LEFT JOIN field_supervision_project p ON t.project_id = p.id WHERE t.id = ? AND t.entry_type = 'note'");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Note not found (id=' . $id . ')');
                $createdBy = intval($row['created_by'] ?? 0);
                $projSupervisor = intval($row['supervisor_user_id'] ?? 0);
                // Permitir edição pelo autor, supervisor do projeto ou admin/gestor
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $createdBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied: only admin/gestor, project supervisor or author can edit notes');
                }
                $stmt = $pdo->prepare("UPDATE field_supervision_timeline SET entry_text = ? WHERE id = ?");
                $stmt->execute([$text, $id]);
                try {
                    logAction('timeline_note_updated', 'field_supervision_timeline', $id, 'Timeline note updated', 'Timeline ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Note updated']);
                break;
            }
        case 'list_problems': {
                if (!tableExists($pdo, 'field_supervision_problem')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problems feature.', 501);
                }
                $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
                // show all problems if project_id not provided or zero; otherwise filter by project
                $params = [];
                $hasResponsible = columnExists($pdo, 'field_supervision_problem', 'responsible_user_id') && tableExists($pdo, 'users');
                $hasProject = columnExists($pdo, 'field_supervision_problem', 'project_id') && tableExists($pdo, 'field_supervision_project');

                $selectCols = "fp.id, fp.title, fp.description, fp.severity, fp.status, fp.reported_by, fp.project_id, fp.created_at, fp.updated_at";
                if ($hasResponsible) $selectCols .= ", fp.responsible_user_id, u.full_name as responsible_name";
                if ($hasProject) $selectCols .= ", p.title as project_title, p.supervisor_user_id as project_supervisor_user_id";

                $sql = "SELECT " . $selectCols . " FROM field_supervision_problem fp";
                if ($hasResponsible) $sql .= " LEFT JOIN users u ON fp.responsible_user_id = u.id";
                if ($hasProject) $sql .= " LEFT JOIN field_supervision_project p ON fp.project_id = p.id";
                $q = trim($_GET['q'] ?? '');
                $statusFilter = trim($_GET['status'] ?? '');
                $sql .= " WHERE 1=1";
                if ($statusFilter !== '') {
                    $sql .= " AND fp.status = ?";
                    $params[] = $statusFilter;
                }
                if ($q !== '') {
                    $sql .= " AND (fp.title LIKE ? OR fp.description LIKE ?)
";
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }
                if ($projectId > 0) {
                    $sql .= " AND fp.project_id = ?";
                    $params[] = $projectId;
                }
                $sql .= " ORDER BY fp.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'add_problem': {
                if (!tableExists($pdo, 'field_supervision_problem')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problems feature.', 501);
                }
                $projectId = intval($_POST['project_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $severity = trim($_POST['severity'] ?? 'minor');
                if ($projectId <= 0 || $title === '') jsonError('project_id,title required');
                // Check if the column exists, otherwise insert without the column
                if (columnExists($pdo, 'field_supervision_problem', 'responsible_user_id')) {
                    $responsible = intval($_POST['responsible_user_id'] ?? 0);
                    $stmt = $pdo->prepare("INSERT INTO field_supervision_problem (project_id,title,description,severity,status,reported_by,responsible_user_id) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$projectId, $title, $desc ?: null, $severity, 'open', $userId, $responsible ?: null]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO field_supervision_problem (project_id,title,description,severity,status,reported_by) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$projectId, $title, $desc ?: null, $severity, 'open', $userId]);
                }
                $pid = $pdo->lastInsertId();
                try {
                    logAction('problem_reported', 'field_supervision_problem', $pid, 'Problem reported: ' . $title, $title);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Problem reported', 'id' => $pid]);
                break;
            }
        case 'update_problem': {
                if (!tableExists($pdo, 'field_supervision_problem')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problems feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                $status = trim($_POST['status'] ?? 'open');
                if ($id <= 0) jsonError('Invalid id');
                // permission check: only admin/gestor, project supervisor, or reporter can update the problem
                $stmtCheck = $pdo->prepare("SELECT project_id, reported_by FROM field_supervision_problem WHERE id = ?");
                $stmtCheck->execute([$id]);
                $p = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if (!$p) jsonError('Not found', 404);
                $projectId = intval($p['project_id']);
                $reportedBy = intval($p['reported_by']);
                $projSupervisor = 0;
                if ($projectId && tableExists($pdo, 'field_supervision_project')) {
                    $stmt2 = $pdo->prepare("SELECT supervisor_user_id FROM field_supervision_project WHERE id = ?");
                    $stmt2->execute([$projectId]);
                    $projSupervisor = intval($stmt2->fetchColumn() ?? 0);
                }
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $reportedBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied: only admin/gestor, project supervisor or reporter can update problem', 403);
                }
                $stmt = $pdo->prepare("UPDATE field_supervision_problem SET status=? WHERE id=?");
                $stmt->execute([$status, $id]);
                try {
                    logAction('problem_status_updated', 'field_supervision_problem', $id, 'Problem status updated to ' . $status, 'Problem ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Problem status updated']);
                break;
            }
        case 'delete_problem': {
                if (!tableExists($pdo, 'field_supervision_problem')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problems feature.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('Invalid id');
                $stmt = $pdo->prepare("SELECT project_id, reported_by FROM field_supervision_problem WHERE id = ?");
                $stmt->execute([$id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$p) jsonError('Not found', 404);
                $projectId = intval($p['project_id']);
                $reportedBy = intval($p['reported_by']);
                $projSupervisor = 0;
                if ($projectId && tableExists($pdo, 'field_supervision_project')) {
                    $stmt2 = $pdo->prepare("SELECT supervisor_user_id FROM field_supervision_project WHERE id = ?");
                    $stmt2->execute([$projectId]);
                    $projSupervisor = intval($stmt2->fetchColumn() ?? 0);
                }
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $reportedBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied: only admin/gestor, project supervisor or reporter can delete problem', 403);
                }
                $stmt = $pdo->prepare("DELETE FROM field_supervision_problem WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('problem_deleted', 'field_supervision_problem', $id, 'Problem deleted', 'Problem ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Problem deleted']);
                break;
            }
        case 'add_problem_note': {
                if (!tableExists($pdo, 'field_supervision_problem_note')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problem Notes feature.', 501);
                }
                $problemId = intval($_POST['problem_id'] ?? 0);
                $text = trim($_POST['note_text'] ?? '');
                if ($problemId <= 0 || $text === '') jsonError('problem_id,note_text required');
                $stmt = $pdo->prepare("INSERT INTO field_supervision_problem_note (problem_id,note_text,author_user_id) VALUES (?,?,?)");
                $stmt->execute([$problemId, $text, $userId]);
                $pnid = $pdo->lastInsertId();
                try {
                    logAction('problem_note_added', 'field_supervision_problem_note', $pnid, 'Problem note added', 'Problem ' . $problemId);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Problem note added', 'id' => $pnid]);
                break;
            }
        case 'list_problem_notes': {
                if (!tableExists($pdo, 'field_supervision_problem_note')) {
                    jsonError('Field Supervision module not initialised. Please run the DB migration to enable Problem Notes feature.', 501);
                }
                $problemId = intval($_GET['problem_id'] ?? 0);
                if ($problemId <= 0) jsonError('problem_id required');
                $stmt = $pdo->prepare("SELECT id,note_text,author_user_id,created_at FROM field_supervision_problem_note WHERE problem_id=? ORDER BY created_at DESC");
                $stmt->execute([$problemId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'attach_visit_to_project': {
                if (!tableExists($pdo, 'field_visit') || !tableExists($pdo, 'field_supervision_project')) {
                    jsonError('Field Supervision module not initialised or visits table missing. Please run the DB migration to enable attachments.', 501);
                }
                $visitId = intval($_POST['visit_id'] ?? 0);
                $projectId = intval($_POST['project_id'] ?? 0);
                if ($visitId <= 0 || $projectId <= 0) jsonError('visit_id,project_id required');
                $stmt = $pdo->prepare("UPDATE field_visit SET project_id=? WHERE id=?");
                $stmt->execute([$projectId, $visitId]);
                try {
                    logAction('visit_attached_to_project', 'field_visit', $visitId, 'Visit attached to project ' . $projectId, 'Visit ' . $visitId);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Visit attached to project']);
                break;
            }
        case 'list_visit_attachments': {
                if (!tableExists($pdo, 'field_visit_attachment')) {
                    jsonError('Attachments feature is not available. Please run DB migration to enable attachments.', 501);
                }
                $visitId = intval($_GET['visit_id'] ?? 0);
                if ($visitId <= 0) jsonError('visit_id required');
                $stmt = $pdo->prepare("SELECT id,visit_id,file_path,file_name,mime_type,uploaded_by,uploaded_at FROM field_visit_attachment WHERE visit_id=? ORDER BY uploaded_at DESC");
                $stmt->execute([$visitId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'upload_visit_attachment': {
                if (!tableExists($pdo, 'field_visit_attachment')) {
                    jsonError('Attachments feature is not available. Please run DB migration to enable attachments.', 501);
                }
                $visitId = intval($_POST['visit_id'] ?? 0);
                if ($visitId <= 0) jsonError('visit_id required');
                if (empty($_FILES['file'])) jsonError('No file uploaded');
                $file = $_FILES['file'];
                if ($file['error'] !== UPLOAD_ERR_OK) jsonError('Upload failed: ' . $file['error']);
                // Basic security: sanitize name and ensure directory
                $uploadsDir = __DIR__ . '/../uploads/field_visit_attachments';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                $safeName = preg_replace('/[^A-Za-z0-9_\-\.]*/', '', basename($file['name']));
                $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . time() . '_' . $safeName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) jsonError('Could not move uploaded file');
                $stmt = $pdo->prepare("INSERT INTO field_visit_attachment (visit_id,file_path,file_name,mime_type,uploaded_by) VALUES (?,?,?,?,?)");
                $stmt->execute([$visitId, $targetPath, $safeName, $file['type'] ?? 'application/octet-stream', $userId]);
                $aid = $pdo->lastInsertId();
                try {
                    logAction('attachment_uploaded', 'field_visit_attachment', $aid, 'Visit attachment uploaded: ' . $safeName, $safeName);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Uploaded', 'id' => $aid, 'file_path' => $targetPath]);
                break;
            }
        case 'delete_visit_attachment': {
                if (!tableExists($pdo, 'field_visit_attachment')) {
                    jsonError('Attachments feature is not available. Please run DB migration to enable attachments.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                // Get row to delete file
                $stmt = $pdo->prepare("SELECT file_path FROM field_visit_attachment WHERE id=?");
                $stmt->execute([$id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$p) jsonError('Not found', 404);
                try {
                    if (!empty($p['file_path']) && file_exists($p['file_path'])) unlink($p['file_path']);
                } catch (Throwable $e) {
                    // continue
                }
                $stmt = $pdo->prepare("DELETE FROM field_visit_attachment WHERE id=?");
                $stmt->execute([$id]);
                try {
                    logAction('attachment_deleted', 'field_visit_attachment', $id, 'Visit attachment deleted', 'Attachment ' . $id);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Deleted']);
                break;
            }
        case 'download_visit_attachment': {
                if (!tableExists($pdo, 'field_visit_attachment')) {
                    jsonError('Attachments feature is not available. Please run DB migration to enable attachments.', 501);
                }
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                $stmt = $pdo->prepare("SELECT file_path,file_name,mime_type FROM field_visit_attachment WHERE id=?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Not found', 404);
                $fp = $row['file_path'];
                if (!file_exists($fp)) jsonError('File not found', 404);
                // output headers and stream file
                header('Content-Type: ' . ($row['mime_type'] ?: 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . basename($row['file_name']) . '"');
                header('Content-Length: ' . filesize($fp));
                readfile($fp);
                exit;
            }

            // ============ PROJECT CONTACTS ============
        case 'list_contacts': {
                $projectId = intval($_GET['project_id'] ?? 0);
                if ($projectId <= 0) jsonError('project_id required');
                if (!tableExists($pdo, 'field_supervision_contact')) {
                    // Return empty array if table doesn't exist yet
                    echo json_encode(['success' => true, 'data' => [], 'message' => 'Contacts table not yet created. Run DB migration.']);
                    break;
                }
                $stmt = $pdo->prepare("SELECT id, name, role, phone, created_by, created_at, updated_at FROM field_supervision_contact WHERE project_id = ? ORDER BY name ASC");
                $stmt->execute([$projectId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
        case 'add_contact': {
                if (!tableExists($pdo, 'field_supervision_contact')) {
                    jsonError('Contacts table not yet created. Please run the DB migration: sql_migrations/add_project_contacts.sql', 501);
                }
                $projectId = intval($_POST['project_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $contactRole = trim($_POST['role'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                if ($projectId <= 0 || $name === '') jsonError('project_id and name are required');
                $stmt = $pdo->prepare("INSERT INTO field_supervision_contact (project_id, name, role, phone, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$projectId, $name, $contactRole ?: null, $phone ?: null, $userId]);
                $contactId = $pdo->lastInsertId();
                // Add to timeline
                if (tableExists($pdo, 'field_supervision_timeline')) {
                    $timelineText = "Contacto adicionado: $name" . ($contactRole ? " ($contactRole)" : "") . ($phone ? " - $phone" : "");
                    $stmt2 = $pdo->prepare("INSERT INTO field_supervision_timeline (project_id, entry_type, entry_text, created_by) VALUES (?, 'contact', ?, ?)");
                    $stmt2->execute([$projectId, $timelineText, $userId]);
                }
                echo json_encode(['success' => true, 'message' => 'Contact added', 'id' => $contactId]);
                try {
                    logAction('contact_added', 'field_supervision_contact', $contactId, 'Contact added: ' . $name, $name);
                } catch (Exception $e) {
                }
                break;
            }
        case 'update_contact': {
                if (!tableExists($pdo, 'field_supervision_contact')) {
                    jsonError('Contacts table not yet created. Please run the DB migration.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $contactRole = trim($_POST['role'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                if ($id <= 0 || $name === '') jsonError('id and name are required');
                // Check permissions
                $stmt = $pdo->prepare("SELECT c.created_by, c.project_id, p.supervisor_user_id FROM field_supervision_contact c LEFT JOIN field_supervision_project p ON c.project_id = p.id WHERE c.id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Contact not found');
                $createdBy = intval($row['created_by'] ?? 0);
                $projSupervisor = intval($row['supervisor_user_id'] ?? 0);
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $createdBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied: only admin/gestor, project supervisor or creator can edit contacts');
                }
                $stmt = $pdo->prepare("UPDATE field_supervision_contact SET name = ?, role = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $contactRole ?: null, $phone ?: null, $id]);
                // Add to timeline
                if (tableExists($pdo, 'field_supervision_timeline')) {
                    $timelineText = "Contacto atualizado: $name" . ($contactRole ? " ($contactRole)" : "") . ($phone ? " - $phone" : "");
                    $stmt2 = $pdo->prepare("INSERT INTO field_supervision_timeline (project_id, entry_type, entry_text, created_by) VALUES (?, 'contact', ?, ?)");
                    $stmt2->execute([$row['project_id'], $timelineText, $userId]);
                }
                try {
                    logAction('contact_updated', 'field_supervision_contact', $id, 'Contact updated: ' . $name, $name);
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Contact updated']);
                break;
            }
        case 'delete_contact': {
                if (!tableExists($pdo, 'field_supervision_contact')) {
                    jsonError('Contacts table not yet created.', 501);
                }
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) jsonError('id required');
                // Check permissions
                $stmt = $pdo->prepare("SELECT c.created_by, c.name, c.project_id, p.supervisor_user_id FROM field_supervision_contact c LEFT JOIN field_supervision_project p ON c.project_id = p.id WHERE c.id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) jsonError('Contact not found');
                $createdBy = intval($row['created_by'] ?? 0);
                $projSupervisor = intval($row['supervisor_user_id'] ?? 0);
                if (!in_array($role, ['admin', 'gestor']) && $userId !== $createdBy && $userId !== $projSupervisor) {
                    jsonError('Permission denied');
                }
                $stmt = $pdo->prepare("DELETE FROM field_supervision_contact WHERE id = ?");
                $stmt->execute([$id]);
                // Remove any existing timeline entries referring to this contact
                // so that deleting a contact also clears its related timeline messages.
                if (tableExists($pdo, 'field_supervision_timeline')) {
                    try {
                        $stmt2 = $pdo->prepare("DELETE FROM field_supervision_timeline WHERE project_id = ? AND INSTR(entry_text, ?) > 0 AND (entry_type = 'contact' OR entry_type = '' OR entry_type IS NULL)");
                        $stmt2->execute([$row['project_id'], $row['name']]);
                    } catch (Throwable $e) {
                        // ignore timeline deletion errors
                    }
                }
                try {
                    logAction('contact_deleted', 'field_supervision_contact', $id, 'Contact deleted: ' . ($row['name'] ?? ''), $row['name'] ?? '');
                } catch (Exception $e) {
                }
                echo json_encode(['success' => true, 'message' => 'Contact deleted']);
                break;
            }

        default:
            jsonError('Unknown action', 404);
    }
} catch (Exception $e) {
    jsonError($e->getMessage());
}
