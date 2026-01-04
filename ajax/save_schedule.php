<?php

/**
 * Save Schedule - AJAX Endpoint
 * 
 * Creates or updates a calendar schedule
 * 
 * @return JSON Operation result
 */

require_once '../includes/auth.php';
requireLogin();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Accept POST or PUT
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

try {
    // Basic validation
    // Title is optional and will be auto-generated if not provided
    if (empty($input['start_date'])) {
        throw new Exception('Start date is required');
    }
    if (empty($input['end_date'])) {
        throw new Exception('End date is required');
    }

    // Validate that end_date is after start_date
    $start = new DateTime($input['start_date']);
    $end = new DateTime($input['end_date']);
    if ($end <= $start) {
        throw new Exception('End date must be after start date');
    }
    if (empty($input['event_type'])) {
        throw new Exception('Event type is required');
    }

    // Validate event type
    $validTypes = ['commissioning', 'site_survey', 'field_supervision', 'other'];
    if (!in_array($input['event_type'], $validTypes)) {
        throw new Exception('Invalid event type');
    }

    // Map type labels (used to generate a title if not provided)
    $typeLabels = [
        'commissioning' => 'Commissioning',
        'site_survey' => 'Site Survey',
        'field_supervision' => 'Field Supervision',
        'other' => 'Other'
    ];

    // Auto-generate title if not provided
    if (empty($input['title'])) {
        $label = $typeLabels[$input['event_type']] ?? $input['event_type'];
        $project = !empty($input['project_name']) ? ' - ' . $input['project_name'] : '';
        $input['title'] = $label . $project;
    }

    // Prepare data
    $data = [
        'title' => trim($input['title']),
        'description' => isset($input['description']) ? trim($input['description']) : null,
        'event_type' => $input['event_type'],
        'start_date' => $input['start_date'],
        'end_date' => $input['end_date'],
        'all_day' => isset($input['all_day']) ? (int) $input['all_day'] : 0,
        'location' => isset($input['location']) ? trim($input['location']) : null,
        'project_name' => isset($input['project_name']) ? trim($input['project_name']) : null,
        'assigned_to' => !empty($input['assigned_to']) ? (int) $input['assigned_to'] : null,
        'status' => isset($input['status']) ? $input['status'] : 'scheduled',
        'color' => isset($input['color']) ? $input['color'] : null
    ];

    // If a project_id was provided (from Field Supervision), resolve its title into project_name
    if (!empty($input['project_id'])) {
        $projId = (int)$input['project_id'];
        try {
            $stmt = $pdo->prepare('SELECT title FROM field_supervision_project WHERE id = ?');
            $stmt->execute([$projId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['title'])) {
                $data['project_name'] = $row['title'];
            }
        } catch (Exception $e) {
            // If project lookup fails, ignore and keep provided project_name (if any)
        }
    }

    // Verificar se é update ou insert
    $scheduleId = isset($input['id']) ? (int) $input['id'] : null;

    if ($scheduleId) {
        // UPDATE
        $sql = "UPDATE schedules SET 
                    title = :title,
                    description = :description,
                    event_type = :event_type,
                    start_date = :start_date,
                    end_date = :end_date,
                    all_day = :all_day,
                    location = :location,
                    project_name = :project_name,
                    assigned_to = :assigned_to,
                    status = :status,
                    color = :color
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $data['id'] = $scheduleId;
        $stmt->execute($data);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'id' => $scheduleId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // INSERT
        $data['created_by'] = $_SESSION['user_id'] ?? null;

        $sql = "INSERT INTO schedules 
                    (title, description, event_type, start_date, end_date, all_day, 
                     location, project_name, assigned_to, status, color, created_by)
                VALUES 
                    (:title, :description, :event_type, :start_date, :end_date, :all_day,
                     :location, :project_name, :assigned_to, :status, :color, :created_by)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Schedule created successfully',
            'id' => $newId
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    error_log('[save_schedule.php] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error while saving schedule',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[save_schedule.php] Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
