<?php
require 'config/database.php';

try {
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->fetch();

    if ($tableExists) {
        echo "✅ Tabela 'users' existe\n";

        // Check table structure
        $stmt = $pdo->query("DESCRIBE users");
        echo "Estrutura da tabela users:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$row['Field']}: {$row['Type']} {$row['Extra']}\n";
        }

        // Check if there are any users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nTotal de usuários: {$count['count']}\n";

        if ($count['count'] > 0) {
            // Show users
            $stmt = $pdo->query("SELECT id, username, email, full_name, role, is_active FROM users");
            echo "\nUsuários existentes:\n";
            while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "- ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}, Active: {$user['is_active']}\n";
            }
        }
    } else {
        echo "❌ Tabela 'users' NÃO existe\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
