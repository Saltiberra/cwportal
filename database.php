<?php

/**
 * Database Configuration
 *
 * Defaults to LOCAL development, but supports production overrides via:
 * - Environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT)
 * - Optional config/production.php file returning an array with keys
 *   ['host','name','user','pass','port'] when the file .production exists
 */

// Set timezone to Europe/Lisbon
date_default_timezone_set('Europe/Lisbon');

// 1) Defaults (LOCAL dev)
$cfg = [
    'host' => '127.0.0.1',
    'name' => 'cleanwattsportal',
    'user' => 'root',
    'pass' => '',
    'port' => 3306,
];

// 2) Environment variable overrides (e.g., hosting control panel)
if (getenv('DB_HOST')) $cfg['host'] = getenv('DB_HOST');
if (getenv('DB_NAME')) $cfg['name'] = getenv('DB_NAME');
if (getenv('DB_USER')) $cfg['user'] = getenv('DB_USER');
if (getenv('DB_PASS')) $cfg['pass'] = getenv('DB_PASS');
if (getenv('DB_PORT')) $cfg['port'] = (int) getenv('DB_PORT');

// 3) Production file override: config/production.php (only if .production flag exists)
$prodFlag = __DIR__ . DIRECTORY_SEPARATOR . '.production';
$prodFile = __DIR__ . DIRECTORY_SEPARATOR . 'production.php';
if (file_exists($prodFlag) && file_exists($prodFile)) {
    try {
        $prod = include $prodFile; // must return array with keys host,name,user,pass,port
        if (is_array($prod)) {
            $cfg = array_merge($cfg, array_intersect_key($prod, $cfg));
        }
    } catch (Throwable $e) {
        error_log('[DB] production.php override failed: ' . $e->getMessage());
    }
}

// Expose as constants for legacy code
define('DB_HOST', $cfg['host']);
define('DB_NAME', $cfg['name']);
define('DB_USER', $cfg['user']);
define('DB_PASS', $cfg['pass']);
define('DB_PORT', $cfg['port']);

// Create connection
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    if (defined('DB_PORT') && DB_PORT) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    }

    error_log('[DB] Attempting connection to ' . DB_HOST . ':' . (defined('DB_PORT') ? DB_PORT : '3306') . '/' . DB_NAME);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Align MySQL session time zone with PHP/app time zone to avoid gaps in time-based UI
    try {
        $pdo->exec("SET time_zone = 'Europe/Lisbon'");
        error_log('[DB] Session time_zone set to Europe/Lisbon');
    } catch (Throwable $tzEx1) {
        try {
            $tz = new DateTimeZone('Europe/Lisbon');
            $dt = new DateTime('now', $tz);
            $offsetSeconds = $tz->getOffset($dt);
            $sign = $offsetSeconds >= 0 ? '+' : '-';
            $hours = str_pad((string) floor(abs($offsetSeconds) / 3600), 2, '0', STR_PAD_LEFT);
            $minutes = str_pad((string) floor((abs($offsetSeconds) % 3600) / 60), 2, '0', STR_PAD_LEFT);
            $offsetStr = $sign . $hours . ':' . $minutes;
            $pdo->exec("SET time_zone = '" . $offsetStr . "'");
            error_log('[DB] Session time_zone set to offset ' . $offsetStr);
        } catch (Throwable $tzEx2) {
            error_log('[DB] Warning: Could not set MySQL session time_zone. ' . $tzEx2->getMessage());
        }
    }

    // Ensure client connection charset is utf8mb4
    try {
        $pdo->exec("SET NAMES 'utf8mb4'");
        $pdo->exec("SET character_set_client = utf8mb4");
        $pdo->exec("SET character_set_results = utf8mb4");
        $pdo->exec("SET character_set_connection = utf8mb4");
        error_log('[DB] SET NAMES utf8mb4 executed');
    } catch (Throwable $e) {
        error_log('[DB] Warning: error setting client charset utf8mb4: ' . $e->getMessage());
    }

    error_log('[DB] Connection successful');
} catch (PDOException $e) {
    $error_msg = $e->getMessage();
    error_log('[DB] Connection failed - ' . $error_msg);
    // Show a helpful message instead of a blank page if display_errors is off
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "Database connection failed.\n";
    echo "Please verify config in /config/database.php (and production.php if used).\n";
    echo "Error: " . $error_msg;
    exit;
}
