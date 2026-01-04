<?php
require_once __DIR__ . '/../config/database.php';
$tables = [
    'field_visit',
    'field_visit_attachment',
    'field_visit_action_item',
    'field_visit_note',
    'field_supervision_project',
    'field_supervision_timeline',
    'field_supervision_problem',
    'field_supervision_problem_note'
];
foreach ($tables as $t) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$t]);
    $exists = (int)$stmt->fetchColumn() > 0;
    echo $t . ': ' . ($exists ? 'exists' : 'MISSING') . PHP_EOL;
}

// Check columns
$colChecks = [
    ['field_visit', 'project_id'],
    ['field_supervision_problem', 'responsible_user_id']
];
foreach ($colChecks as $c) {
    list($table, $col) = $c;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $col]);
    $exists = (int)$stmt->fetchColumn() > 0;
    echo "Column check: $table.$col: " . ($exists ? 'exists' : 'MISSING') . PHP_EOL;
}

echo "Done\n";
