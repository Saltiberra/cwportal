<?php
// Reusable recent activity timeline include
// If $activityLogs not set, fetch defaults
if (!isset($activityLogs)) {
    try {
        $activityLogs = getAuditLog(['days' => 7], 20);
    } catch (Exception $e) {
        $activityLogs = [];
    }
}
?>
<?php if (!empty($activityLogs)): ?>
    <style>
        /* Activity timeline widget styles - copied from commissioning dashboard for parity */
        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .activity-timeline::-webkit-scrollbar {
            width: 8px;
        }

        .activity-timeline::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .activity-timeline::-webkit-scrollbar-thumb {
            background: #2CCCD3;
            border-radius: 10px;
        }

        .activity-timeline::-webkit-scrollbar-thumb:hover {
            background: #1fb7b8;
        }

        .activity-timeline {
            scrollbar-color: #2CCCD3 #f1f1f1;
            scrollbar-width: thin;
        }

        .activity-item:last-child {
            border-bottom: none !important;
        }
    </style>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0"><i class="fas fa-scroll me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="activity-timeline">
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="activity-item d-flex mb-3 pb-3 border-bottom">
                                <div class="activity-icon me-3" style="min-width: 40px; text-align: center;">
                                    <?php echo getActionIcon($log['action']); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                        <span class="text-muted"><?php
                                                                    $actionText = '';
                                                                    switch ($log['action']) {
                                                                        case 'user_created':
                                                                            $actionText = 'created account';
                                                                            break;
                                                                        case 'user_deleted':
                                                                            $actionText = 'deleted account';
                                                                            break;
                                                                        case 'user_deactivated':
                                                                            $actionText = 'deactivated account';
                                                                            break;
                                                                        case 'privilege_changed':
                                                                            $actionText = 'changed privilege';
                                                                            break;
                                                                        case 'password_reset':
                                                                            $actionText = 'reset password';
                                                                            break;
                                                                        case 'report_created':
                                                                            $actionText = 'created report';
                                                                            break;
                                                                        case 'report_edited':
                                                                            $actionText = 'edited report';
                                                                            break;
                                                                        case 'report_deleted':
                                                                            $actionText = 'deleted report';
                                                                            break;
                                                                        case 'login':
                                                                            $actionText = 'logged in';
                                                                            break;
                                                                        case 'logout':
                                                                            $actionText = 'logged out';
                                                                            break;
                                                                        default:
                                                                            $actionText = $log['action'];
                                                                    }
                                                                    echo htmlspecialchars($actionText);
                                                                    ?></span>
                                    </p>
                                    <small class="text-muted">
                                        <?php if (!empty($log['entity_name'])): ?>
                                            <strong><?php echo htmlspecialchars($log['entity_name']); ?></strong>
                                        <?php endif; ?>
                                        <?php echo $log['description'] ? ' · ' . htmlspecialchars($log['description']) : ''; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo formatTimeAgo($log['timestamp']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>