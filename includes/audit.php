<?php

/**
 * Audit Log Helper Functions
 * Sistema de auditoria para registar todos os actions dos utilizadores
 */

/**
 * Registar uma ação de auditoria
 * 
 * @param string $action - Tipo de ação (e.g., 'user_created', 'report_deleted')
 * @param string $entityType - Tipo de entidade (e.g., 'users', 'reports')
 * @param int $entityId - ID da entidade
 * @param string $description - Descrição da ação
 * @param string $entityName - Nome da entidade (opcional)
 * @return bool - true se registado com sucesso
 */
function logAction($action, $entityType, $entityId, $description, $entityName = null)
{
    global $pdo;

    try {
        // Get current user info from session
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'system';

        // Get client IP
        $ipAddress = getClientIP();

        // Prepare SQL
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, username, action, entity_type, entity_id, entity_name, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Execute
        $result = $stmt->execute([
            $userId,
            $username,
            $action,
            $entityType,
            $entityId,
            $entityName,
            $description,
            $ipAddress
        ]);

        if ($result) {
            error_log("[AUDIT] Action logged: $action - $description");
        }

        return $result;
    } catch (Exception $e) {
        error_log("[AUDIT] Error logging action: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter endereço IP do cliente
 * @return string - IP address
 */
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/**
 * Obter log de auditoria com filtros
 * 
 * @param array $filters - Filtros opcionais: ['user_id', 'action', 'entity_type', 'days']
 * @param int $limit - Número máximo de registos
 * @return array - Array de logs
 */
function getAuditLog($filters = [], $limit = 50)
{
    global $pdo;

    try {
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];

        // Filtro por utilizador
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }

        // Filtro por ação
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        // Filtro por tipo de entidade
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        // Filtro por dias atrás
        if (!empty($filters['days'])) {
            $sql .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params[] = intval($filters['days']);
        }

        // Order by timestamp DESC e limit
        $sql .= " ORDER BY timestamp DESC LIMIT ?";
        $params[] = intval($limit);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("[AUDIT] Error fetching logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter estatísticas de auditoria
 * 
 * @param string $period - 'today', 'week', 'month'
 * @return array - Estatísticas
 */
function getAuditStats($period = 'today')
{
    global $pdo;

    try {
        $daysAgo = match ($period) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            default => 1
        };

        $sql = "
            SELECT 
                action,
                COUNT(*) as count
            FROM audit_log
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$daysAgo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("[AUDIT] Error fetching stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Formatar log entry para exibição
 * 
 * @param array $log - Log entry
 * @return string - Formatted message
 */
function formatLogEntry($log)
{
    $icon = getActionIcon($log['action']);
    $time = formatTimeAgo($log['timestamp']);
    $username = htmlspecialchars($log['username']);
    $description = htmlspecialchars($log['description']);

    return "{$icon} <strong>{$username}</strong> - {$description} <small class='text-muted'>({$time})</small>";
}

/**
 * Obter ícone para tipo de ação
 * 
 * @param string $action - Tipo de ação
 * @return string - HTML icon
 */
function getActionIcon($action)
{
    $icons = [
        'user_created' => '<i class="fas fa-user-plus text-success me-2"></i>',
        'user_deleted' => '<i class="fas fa-user-minus text-danger me-2"></i>',
        'privilege_changed' => '<i class="fas fa-crown text-warning me-2"></i>',
        'password_reset' => '<i class="fas fa-key text-info me-2"></i>',
        'report_created' => '<i class="fas fa-file-alt text-primary me-2"></i>',
        'report_edited' => '<i class="fas fa-edit text-secondary me-2"></i>',
        'report_deleted' => '<i class="fas fa-trash text-danger me-2"></i>',
        'login' => '<i class="fas fa-sign-in-alt text-success me-2"></i>',
        'logout' => '<i class="fas fa-sign-out-alt text-secondary me-2"></i>',
        'default' => '<i class="fas fa-history text-muted me-2"></i>'
    ];

    return $icons[$action] ?? $icons['default'];
}

/**
 * Formatar tempo relativo (ex: "2 hours ago")
 * 
 * @param string $timestamp - Timestamp no formato 'Y-m-d H:i:s'
 * @return string - Tempo relativo
 */
function formatTimeAgo($timestamp)
{
    // Ensure we're using the correct timezone (Europe/Lisbon)
    date_default_timezone_set('Europe/Lisbon');

    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = intval($diff / 60);
        return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = intval($diff / 3600);
        $mins = intval(($diff % 3600) / 60);
        return $hours . " hour" . ($hours > 1 ? "s" : "") .
            ($mins > 0 ? " " . $mins . " min" . ($mins > 1 ? "s" : "") : "") . " ago";
    } elseif ($diff < 604800) {
        $days = intval($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('Y-m-d H:i', $time);
    }
}
