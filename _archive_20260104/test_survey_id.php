<?php
include 'includes/db_connection.php';

$result = $pdo->query('SELECT id, title FROM site_surveys ORDER BY id DESC LIMIT 1');
$row = $result->fetch();

if ($row) {
    echo "ID: " . $row['id'] . " - Title: " . $row['title'];
} else {
    echo "No surveys found";
}
