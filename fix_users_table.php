<?php
require 'config/database.php';

try {
    echo "Tentando recriar a tabela users...\n";

    // Try to drop table if exists (this might work now)
    try {
        $pdo->exec("DROP TABLE IF EXISTS users");
        echo "Tabela dropada com sucesso\n";
    } catch (Exception $e) {
        echo "Não conseguiu dropar tabela: " . $e->getMessage() . "\n";
    }

    // Create users table
    $createTableSQL = "
        CREATE TABLE users (
            id int(11) NOT NULL AUTO_INCREMENT,
            username varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            password_hash varchar(255) NOT NULL,
            full_name varchar(255) DEFAULT NULL,
            role varchar(50) NOT NULL DEFAULT 'technician',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            last_login timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $pdo->exec($createTableSQL);
    echo "✅ Tabela users criada com sucesso\n";

    // Insert demo user
    $passwordHash = password_hash('demo123', PASSWORD_DEFAULT);
    $insertUserSQL = "
        INSERT INTO users (username, email, password_hash, full_name, role, is_active)
        VALUES ('demo', 'demo@cleanwatts.pt', '$passwordHash', 'Demo User', 'admin', 1)
    ";

    $pdo->exec($insertUserSQL);
    echo "✅ Usuário demo criado com sucesso\n";
    echo "Username: demo\n";
    echo "Password: demo123\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
