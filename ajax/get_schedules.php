<?php

/**
 * Get Schedules - AJAX Endpoint
 * 
 * Returns all schedules for the TOAST UI calendar
 * Supports filters by date and event type
 * 
 * @return JSON Array of events formatted for TOAST UI Calendar
 */

require_once '../includes/auth.php';
requireLogin();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Optional filter parameters
    $startDate = isset($_GET['start']) ? $_GET['start'] : null;
    $endDate = isset($_GET['end']) ? $_GET['end'] : null;
    $eventType = isset($_GET['type']) ? $_GET['type'] : null;

    // Query base
    $sql = "SELECT 
                s.id,
                s.title,
                s.description,
                s.event_type,
                s.start_date,
                s.end_date,
                s.all_day,
                s.location,
                s.project_name,
                s.assigned_to,
                s.status,
                s.color,
                u.full_name as assigned_name
            FROM schedules s
            LEFT JOIN users u ON s.assigned_to = u.id
            WHERE 1=1";

    $params = [];

    // Filtro por período
    if ($startDate) {
        $sql .= " AND s.end_date >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND s.start_date <= :end_date";
        $params[':end_date'] = $endDate;
    }

    // Filtro por tipo
    if ($eventType && $eventType !== 'all') {
        $sql .= " AND s.event_type = :event_type";
        $params[':event_type'] = $eventType;
    }

    $sql .= " ORDER BY s.start_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helper to ensure UTF-8 strings
    function ensure_utf8($v)
    {
        if ($v === null) return null;
        if (mb_check_encoding($v, 'UTF-8')) return $v;
        // Try to convert from ISO-8859-1 latin1 to UTF-8
        $converted = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        return $converted;
    }

    // Map colors by event type
    $typeColors = [
        'commissioning' => '#2CCCD3',      // Cleanwatts Blue
        'site_survey' => '#28a745',         // Green
        'field_supervision' => '#fd7e14',   // Orange
        'other' => '#6c757d'                // Grey
    ];

    $typeLabels = [
        'commissioning' => 'Commissioning',
        'site_survey' => 'Site Survey',
        'field_supervision' => 'Field Supervision',
        'other' => 'Other'
    ];

    // Format for TOAST UI Calendar
    $events = [];
    foreach ($schedules as $schedule) {
        $bgColor = $schedule['color'] ?: ($typeColors[$schedule['event_type']] ?? '#6c757d');
        $isAllDay = (bool) $schedule['all_day'];

        // Build full title: "Title @ Project Name" or just "Title" if no project
        $fullTitle = ensure_utf8($schedule['title']);
        if (!empty($schedule['project_name'])) {
            $fullTitle .= ' @ ' . ensure_utf8($schedule['project_name']);
        }

        $events[] = [
            'id' => (string) $schedule['id'],
            'calendarId' => $schedule['event_type'],
            'title' => $fullTitle,
            'body' => $schedule['description'] ?: '',
            'start' => $schedule['start_date'],
            'end' => $schedule['end_date'],
            'isAllday' => $isAllDay,
            'category' => $isAllDay ? 'allday' : 'time',  // Use correct category based on all_day flag
            'location' => $schedule['location'] ?: '',
            'state' => $schedule['status'],
            'backgroundColor' => $bgColor,
            'borderColor' => $bgColor,
            'color' => '#ffffff',
            'raw' => [
                'event_type' => $schedule['event_type'],
                'event_type_label' => $typeLabels[$schedule['event_type']] ?? $schedule['event_type'],
                'project_name' => ensure_utf8($schedule['project_name']),
                'assigned_to' => $schedule['assigned_to'],
                'assigned_name' => ensure_utf8($schedule['assigned_name']),
                'status' => $schedule['status'],
                'all_day' => $isAllDay,
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date']
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'events' => $events,
        'count' => count($events)
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[get_schedules.php] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error loading schedules',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[get_schedules.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
