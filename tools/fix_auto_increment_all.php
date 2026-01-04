<?php
require_once __DIR__ . '/../config/database.php';
$tables = [
    'commissioning_responsible_persons',
    'communications_models',
    'credential_access_log',
    'credential_store',
    'deletion_log',
    'energy_meter_models',
    'field_supervision_contact',
    'field_supervision_problem',
    'field_supervision_problem_note',
    'field_supervision_project_contact',
    'field_supervision_timeline',
    'field_visit',
    'field_visit_action_item',
    'field_visit_attachment',
    'field_visit_issue',
    'field_visit_note',
    'procedure_doc',
    'proc_category',
    'site_survey_drafts',
    'site_survey_items',
    'site_survey_responsibles',
    'site_survey_roofs',
    'site_survey_shading',
    'site_survey_shading_objects',
    'users'
];

foreach ($tables as $table) {
    echo "--- Processing $table\n";
    try {
        $mRow = $pdo->query("SELECT IFNULL(MAX(id), 0) AS m FROM $table")->fetch(PDO::FETCH_ASSOC);
        $m = intval($mRow['m']);
        $rows = $pdo->query("SELECT id FROM $table WHERE id = 0")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 0) {
            echo "No id=0 rows in $table. Current max={$m}\n";
        } else {
            // Update each id=0 row to a new sequential id
            foreach ($rows as $r) {
                $m++;
                $upd = $pdo->prepare("UPDATE $table SET id = ? WHERE id = 0 LIMIT 1");
                $upd->execute([$m]);
                echo "Updated one id=0 row in $table to id={$m}\n";
            }
        }
        // Finally, modify the column to AUTO_INCREMENT
        $pdo->exec("ALTER TABLE $table MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = " . ($m + 1));
        echo "Altered $table.id to AUTO_INCREMENT. Next will be " . ($m + 1) . "\n";
    } catch (Exception $e) {
        echo "ERROR processing $table: " . $e->getMessage() . "\n";
    }
}

echo "Done\n";
