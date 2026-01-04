<?php

/**
 * Dropdown Handler
 * Carrega opções dinâmicas para dropdowns
 * 
 * FALLBACK MODE: Se a tabela requerida não existir,
 * retorna um array vazio em vez de erro HTTP
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? null;
    $type = $_GET['type'] ?? null;

    error_log("[DROPDOWN] action={$action}, type={$type}");

    if (!$action) {
        throw new Exception('Missing action parameter');
    }

    $response = ['success' => false, 'options' => [], 'message' => 'No action specified'];

    switch ($action) {
        case 'getBrands':
            // getBrands for equipment dropdowns
            $response = handleGetBrands($type);
            break;

        case 'getModels':
            // getModels for equipment dropdowns
            $brand_id = $_GET['brand_id'] ?? null;
            $response = handleGetModels($type, $brand_id);
            break;

        default:
            error_log("[DROPDOWN] Unknown action: {$action}");
            $response = [
                'success' => true,
                'options' => [],
                'message' => "No options for action: {$action}"
            ];
    }

    http_response_code(200);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("[DROPDOWN] ❌ Error: " . $e->getMessage());

    // FALLBACK: Retornar sucesso com opções vazias em vez de erro
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'options' => [],
        'message' => 'Options not available (fallback mode)'
    ]);
}

/**
 * Handle getBrands - Carrega marcas de equipamento
 */
function handleGetBrands($type)
{
    global $pdo;

    // Mapeamento de tipos para tabelas
    $tableMap = [
        'circuit_breaker' => 'circuit_breaker_brands',
        'differential' => 'differential_brands',
        'inverter' => 'inverter_brands',
        'meter' => 'meter_brands'
    ];

    $table = $tableMap[$type] ?? null;

    if (!$table) {
        error_log("[DROPDOWN] Unknown type: {$type}");
        return [
            'success' => true,
            'options' => [],
            'message' => "Unknown equipment type: {$type}"
        ];
    }

    try {
        // Verificar se tabela existe
        $testStmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");

        // Carregar marcas
        $stmt = $pdo->query("SELECT id, name FROM {$table} ORDER BY name");
        $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'options' => $brands,
            'type' => $type
        ];
    } catch (Exception $e) {
        error_log("[DROPDOWN] ❌ Error loading brands from {$table}: " . $e->getMessage());

        // FALLBACK: Retornar array vazio
        return [
            'success' => true,
            'options' => [],
            'message' => "Table {$table} not available"
        ];
    }
}

/**
 * Handle getModels - Carrega modelos de equipamento
 */
function handleGetModels($type, $brand_id)
{
    global $pdo;

    // Mapeamento de tipos para tabelas
    $tableMap = [
        'circuit_breaker' => 'circuit_breaker_models',
        'differential' => 'differential_models',
        'inverter' => 'inverter_models',
        'meter' => 'meter_models'
    ];

    $table = $tableMap[$type] ?? null;

    if (!$table || !$brand_id) {
        return [
            'success' => true,
            'options' => [],
            'message' => 'Invalid type or brand_id'
        ];
    }

    try {
        // Verificar se tabela existe
        $testStmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");

        // Carregar modelos
        $stmt = $pdo->prepare("SELECT id, name FROM {$table} WHERE brand_id = ? ORDER BY name");
        $stmt->execute([$brand_id]);
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'options' => $models,
            'type' => $type,
            'brand_id' => $brand_id
        ];
    } catch (Exception $e) {
        error_log("[DROPDOWN] ❌ Error loading models from {$table}: " . $e->getMessage());

        // FALLBACK: Retornar array vazio
        return [
            'success' => true,
            'options' => [],
            'message' => "Table {$table} not available"
        ];
    }
}
