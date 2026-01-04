<?php
// tools/fix_encrypted_secret_column.php
require_once __DIR__ . '/../config/database.php';
try {
    $sql = "ALTER TABLE credential_store MODIFY encrypted_secret TEXT NOT NULL";
    $pdo->exec($sql);
    echo "Coluna 'encrypted_secret' alterada para TEXT com sucesso.\n";
} catch (Exception $e) {
    echo "Erro ao alterar a coluna: " . $e->getMessage() . "\n";
    exit(1);
}
