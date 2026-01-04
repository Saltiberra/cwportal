<?php

/**
 * ===================================================================
 * CLEANWATTS PORTAL - DATABASE CONFIGURATION FOR DEPLOYMENT
 * ===================================================================
 * 
 * Este ficheiro contém as credenciais de produção para a base de dados.
 * 
 * INSTRUÇÕES DE USO:
 * ==================
 * 1. Edita as credenciais abaixo com os dados do teu servidor de produção
 * 2. Quando precisares fazer deploy:
 *    a) Copiar este ficheiro para: config/production.php
 *    b) Criar um ficheiro vazio chamado: config/.production
 *    c) Deploy do projeto para o servidor
 * 3. Para voltar ao desenvolvimento local:
 *    a) Deletar o ficheiro: config/.production
 *    b) O sistema voltará automaticamente a usar local (localhost)
 * 
 * SEGURANÇA:
 * ==========
 * - Nunca commites as credenciais de produção ao Git
 * - Este ficheiro (DATABASE.DEPLOY.php) pode ser commitado
 * - O ficheiro production.php NUNCA deve ser commitado
 * - Usa environment variables no servidor sempre que possível
 * 
 * VARIÁVEIS DE AMBIENTE:
 * ======================
 * Se o teu servidor de hosting suporta, podes definir estas variáveis:
 * - DB_HOST: Endereço do servidor MySQL
 * - DB_NAME: Nome da base de dados
 * - DB_USER: Utilizador da base de dados
 * - DB_PASS: Password da base de dados
 * - DB_PORT: Porta (por defeito 3306)
 * 
 * Se as variáveis de ambiente estiverem definidas, o sistema irá usá-las
 * automaticamente, mesmo sem o ficheiro production.php.
 */

return [
    // *** PRODUÇÃO: InfinityFree - Cleanwatts Portal ***

    'host' => 'sql100.infinityfree.com',           // Servidor MySQL InfinityFree
    'name' => 'if0_40570175_cleanwattsportal',     // Database de produção
    'user' => 'if0_40570175',                      // Utilizador InfinityFree
    'pass' => 'P3ta1174',                          // Password segura
    'port' => 3306,                                // Porta padrão MySQL
];

/**
 * EXEMPLO PARA INFINITYFREE:
 * ==========================
 * return [
 *     'host' => 'mysql.infinityfree.com',
 *     'name' => 'epiz_123456789_cleanwatts',
 *     'user' => 'epiz_123456789',
 *     'pass' => 'SUAsENHAaqui123',
 *     'port' => 3306,
 * ];
 * 
 * EXEMPLO PARA OUTRO SERVIDOR:
 * =============================
 * return [
 *     'host' => 'seu-servidor.com',
 *     'name' => 'seu_database_name',
 *     'user' => 'seu_username',
 *     'pass' => 'sua_password_segura',
 *     'port' => 3306,
 * ];
 */
