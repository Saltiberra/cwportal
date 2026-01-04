<?php

/**
 * Simple script to create users table
 */

require_once 'config/database.php';

try {
    $pdo->beginTransaction();

    // Create users table
    $sql = "
        CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `full_name` varchar(255) DEFAULT NULL,
            `role` varchar(50) NOT NULL DEFAULT 'technician',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `last_login` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $pdo->exec($sql);

    // Insert demo user
    $passwordHash = password_hash('demo123', PASSWORD_DEFAULT);
    $insertSQL = "
        INSERT IGNORE INTO users (username, email, password_hash, full_name, role, is_active)
        VALUES ('demo', 'demo@cleanwatts.pt', '$passwordHash', 'Demo User', 'admin', 1)
    ";

    $pdo->exec($insertSQL);

    $pdo->commit();

    echo "✅ Tabela users criada com sucesso!\n";
    echo "Usuário demo criado:\n";
    echo "Username: demo\n";
    echo "Password: demo123\n";
} catch (Exception $e) {
    try {
        $pdo->rollBack();
    } catch (Exception $e2) {
    }

    echo "Erro: " . $e->getMessage() . "\n";
}
