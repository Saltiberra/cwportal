<?php
require_once __DIR__ . '/../config/database.php';
try {
    $defaults = ['Plataformas', 'Meters', 'Loggers', 'Inverters', 'Modems'];
    foreach ($defaults as $name) {
        $stmt = $pdo->prepare("INSERT INTO proc_category (name) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM proc_category WHERE LOWER(name)=LOWER(?))");
        $stmt->execute([$name, $name]);
        if ($stmt->rowCount()) {
            echo "Inserted category: $name\n";
        } else {
            echo "Category exists: $name\n";
        }
    }
    echo "Done.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
