<?php
// Migration: create user_preferences table if not exists
require_once __DIR__ . '/../config/database.php';

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS user_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pref_key VARCHAR(191) NOT NULL,
  pref_value TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY user_pref_unique (user_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

try {
    $pdo->exec($sql);
    echo "OK: user_preferences table created or already exists.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
