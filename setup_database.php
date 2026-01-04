<?php

/**
 * Database Initialization Script
 * 
 * This script creates the necessary database structure for the PV commissioning system.
 * Run this script once to set up the database.
 */

// Include database connection configuration
require_once 'config/database.php';

try {
    // Start a transaction
    $pdo->beginTransaction();
    // Array of SQL commands to create tables
    $sql = [
        // PV Module Brands Table
        "CREATE TABLE IF NOT EXISTS `pv_module_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // PV Module Models Table
        "CREATE TABLE IF NOT EXISTS `pv_module_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `characteristics` text DEFAULT NULL,
            `nominal_power` decimal(10,2) DEFAULT NULL,
            `voc` decimal(10,2) DEFAULT NULL,
            `isc` decimal(10,2) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `pv_module_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Inverter Brands Table
        "CREATE TABLE IF NOT EXISTS `inverter_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Inverter Models Table
        "CREATE TABLE IF NOT EXISTS `inverter_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `nominal_power` decimal(10,2) DEFAULT NULL,
            `max_output_current` decimal(10,2) DEFAULT NULL,
            `mppts` int(11) DEFAULT NULL,
            `strings_per_mppt` int(11) DEFAULT NULL,
            `datasheet_path` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `inverter_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Cable Brands Table
        "CREATE TABLE IF NOT EXISTS `cable_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Cable Models Table
        "CREATE TABLE IF NOT EXISTS `cable_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `cable_section` varchar(50) DEFAULT NULL,
            `conductor_material` varchar(100) DEFAULT NULL,
            `insulation_type` varchar(100) DEFAULT NULL,
            `voltage_rating` varchar(50) DEFAULT NULL,
            `temperature_rating` varchar(50) DEFAULT NULL,
            `characteristics` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `cable_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Meter Brands Table (for Smart meters / Smartlog / RESP Energy meter)
        "CREATE TABLE IF NOT EXISTS `meter_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Meter Models Table
        "CREATE TABLE IF NOT EXISTS `meter_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `characteristics` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `meter_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Circuit Breaker Brands Table
        "CREATE TABLE IF NOT EXISTS `circuit_breaker_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Circuit Breaker Models Table
        "CREATE TABLE IF NOT EXISTS `circuit_breaker_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `characteristics` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `circuit_breaker_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Differential Brands Table
        "CREATE TABLE IF NOT EXISTS `differential_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Differential Models Table
        "CREATE TABLE IF NOT EXISTS `differential_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `characteristics` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `differential_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // EPCs Table
        "CREATE TABLE IF NOT EXISTS `epcs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `address` varchar(500) DEFAULT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `website` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Representatives Table
        "CREATE TABLE IF NOT EXISTS `representatives` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `phone` varchar(50) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `epc_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`epc_id`) REFERENCES `epcs`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Commissioning Reports Table
        "CREATE TABLE IF NOT EXISTS `commissioning_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_name` varchar(255) NOT NULL,
            `date` date NOT NULL,
            `responsible` varchar(255) NOT NULL,
            `plant_location` varchar(500) DEFAULT NULL,
            `gps` varchar(100) DEFAULT NULL,
            `map_area_m2` decimal(10,2) DEFAULT NULL,
            `map_azimuth_deg` decimal(5,1) DEFAULT NULL,
            `map_polygon_coords` json DEFAULT NULL,
            `epc_id` int(11) DEFAULT NULL,
            `representative_id` int(11) DEFAULT NULL,
            `technician` varchar(255) DEFAULT NULL,
            `installed_power` decimal(10,2) DEFAULT NULL,
            `total_power` decimal(10,2) DEFAULT NULL,
            `certified_power` decimal(10,2) DEFAULT NULL,
            `cpe` varchar(100) DEFAULT NULL,
            `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
            `deleted_at` datetime DEFAULT NULL,
            `deleted_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`epc_id`) REFERENCES `epcs`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`representative_id`) REFERENCES `representatives`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Report Equipment Table
        "CREATE TABLE IF NOT EXISTS `report_equipment` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) NOT NULL,
            `equipment_type` varchar(100) NOT NULL,
            `deployment_status` varchar(100) DEFAULT NULL,
            `brand` varchar(255) DEFAULT NULL,
            `model` varchar(255) DEFAULT NULL,
            `quantity` int(11) DEFAULT NULL,
            `characteristics` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`report_id`) REFERENCES `commissioning_reports`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Report Layout Table
        "CREATE TABLE IF NOT EXISTS `report_layout` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) NOT NULL,
            `roof_id` varchar(100) DEFAULT NULL,
            `quantity` int(11) DEFAULT NULL,
            `azimuth` decimal(5,2) DEFAULT NULL,
            `tilt` decimal(5,2) DEFAULT NULL,
            `mounting` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`report_id`) REFERENCES `commissioning_reports`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Energy Meter Brands Table
        "CREATE TABLE IF NOT EXISTS `energy_meter_brands` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `brand_name` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Energy Meter Models Table
        "CREATE TABLE IF NOT EXISTS `energy_meter_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `brand_id` int(11) NOT NULL,
            `characteristics` text DEFAULT NULL,
            `communication_protocol` varchar(100) DEFAULT NULL,
            `voltage_range` varchar(100) DEFAULT NULL,
            `current_range` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`brand_id`) REFERENCES `energy_meter_brands`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Communications Models Table
        "CREATE TABLE IF NOT EXISTS `communications_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_name` varchar(255) NOT NULL,
            `equipment_type` varchar(100) NOT NULL,
            `manufacturer` varchar(255) DEFAULT NULL,
            `characteristics` text DEFAULT NULL,
            `communication_protocols` varchar(255) DEFAULT NULL,
            `power_supply` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Commissioning Responsibles Table
        "CREATE TABLE IF NOT EXISTS `commissioning_responsibles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `email` varchar(255) DEFAULT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Users Table
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `full_name` varchar(255) DEFAULT NULL,
            `role` varchar(50) NOT NULL DEFAULT 'technician',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `last_login` timestamp NULL DEFAULT NULL,
                `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
                `deleted_at` datetime DEFAULT NULL,
                `deleted_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Report Drafts Table
        "CREATE TABLE IF NOT EXISTS `report_drafts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) DEFAULT NULL,
            `session_id` varchar(255) NOT NULL,
            `form_data` longtext NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_report_session` (`report_id`, `session_id`),
            KEY `idx_session_updated` (`session_id`, `updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Audit Log Table
        "CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `username` varchar(255) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `entity_type` varchar(100) DEFAULT NULL,
            `entity_id` int(11) DEFAULT NULL,
            `entity_name` varchar(255) DEFAULT NULL,
            `description` text,
            `ip_address` varchar(45) DEFAULT NULL,
            `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_user_timestamp` (`user_id`, `timestamp`),
            KEY `idx_action_timestamp` (`action`, `timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Deletion Log (records soft delete and restore events)
        "CREATE TABLE IF NOT EXISTS `deletion_log` (",
        "    `id` int(11) NOT NULL AUTO_INCREMENT,",
        "    `report_id` int(11) DEFAULT NULL,",
        "    `report_project_name` varchar(255) DEFAULT NULL,",
        "    `report_type` varchar(50) DEFAULT NULL,",
        "    `deleted_by` int(11) DEFAULT NULL,",
        "    `deleted_user_name` varchar(255) DEFAULT NULL,",
        "    `deleted_at` datetime DEFAULT NULL,",
        "    `restored_by` int(11) DEFAULT NULL,",
        "    `restored_user_name` varchar(255) DEFAULT NULL,",
        "    `restored_at` datetime DEFAULT NULL,",
        "    `permanently_deleted` tinyint(1) NOT NULL DEFAULT 0,",
        "    `deleted_forever_by` int(11) DEFAULT NULL,",
        "    `deleted_forever_at` datetime DEFAULT NULL,",
        "    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),",
        "    PRIMARY KEY (`id`),",
        "    KEY `idx_deletion_report` (`report_id`, `report_type`)",
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Report Form Sessions Table
        "CREATE TABLE IF NOT EXISTS `report_form_sessions` (",
        "    `id` int(11) NOT NULL AUTO_INCREMENT,",
        "    `session_token` varchar(64) NOT NULL,",
        "    `report_id` int(11) DEFAULT NULL,",
        "    `user_id` int(11) DEFAULT NULL,",
        "    `php_session_id` varchar(128) DEFAULT NULL,",
        "    `expires_at` timestamp NULL DEFAULT NULL,",
        "    `is_active` tinyint(1) NOT NULL DEFAULT 1,",
        "    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),",
        "    PRIMARY KEY (`id`),",
        "    UNIQUE KEY `session_token` (`session_token`),",
        "    KEY `idx_report_user` (`report_id`, `user_id`),",
        "    KEY `idx_php_session` (`php_session_id`, `is_active`)",
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // ==============================
        // Site Survey - New Subproject
        // ==============================
        // Site Survey Reports
        "CREATE TABLE IF NOT EXISTS `site_survey_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_name` varchar(255) DEFAULT NULL,
            `date` date DEFAULT NULL,
            `responsible` varchar(255) DEFAULT NULL,
            `location` varchar(500) DEFAULT NULL,
            `gps` varchar(100) DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
            `deleted_at` datetime DEFAULT NULL,
            `deleted_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_user_date` (`user_id`,`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Site Survey Items (checklist + photos link)
        "CREATE TABLE IF NOT EXISTS `site_survey_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) NOT NULL,
            `item_type` varchar(100) NOT NULL,
            `item_key` varchar(255) DEFAULT NULL,
            `label` varchar(255) DEFAULT NULL,
            `status` varchar(100) DEFAULT NULL,
            `note` text DEFAULT NULL,
            `value` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_report_type` (`report_id`, `item_type`),
            CONSTRAINT `fk_ss_items_report` FOREIGN KEY (`report_id`) REFERENCES `site_survey_reports`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

        // Site Survey Drafts (similar to report_drafts)
        "CREATE TABLE IF NOT EXISTS `site_survey_drafts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) DEFAULT NULL,
            `session_id` varchar(255) NOT NULL,
            `form_data` longtext NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_report_session` (`report_id`, `session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    ];

    // Execute each SQL command
    foreach ($sql as $query) {
        $pdo->exec($query);
    }

    // Insert initial energy meter data
    $initialData = [
        // Energy Meter Brands
        "INSERT IGNORE INTO energy_meter_brands (brand_name) VALUES 
        ('Rayleigh Instruments'),
        ('Eastron'),
        ('Carlo Gavazzi'),
        ('Schneider Electric'),
        ('ABB'),
        ('Siemens'),
        ('Phoenix Contact'),
        ('Janitza'),
        ('Accuenergy'),
        ('Chint');",

        // Energy Meter Models
        "INSERT IGNORE INTO energy_meter_models (model_name, brand_id, characteristics, communication_protocol, voltage_range, current_range) VALUES 
        ('RI-D140', 1, 'Single phase energy meter with RS485 Modbus RTU', 'Modbus RTU', '230V', '5(100)A'),
        ('SDM630MCT', 2, 'Three phase multifunction energy meter', 'Modbus RTU', '400V', '5(100)A'),
        ('EM330', 3, 'Three phase energy analyzer with Modbus', 'Modbus RTU', '400V', '5(100)A'),
        ('iEM3155', 4, 'Energy meter with power quality analysis', 'Modbus TCP', '400V', '5(100)A'),
        ('AQ-210', 5, 'Energy meter with Ethernet communication', 'Modbus TCP', '400V', '5(100)A'),
        ('7KM2110-0BA00-3AA0', 6, 'Digital energy meter with communication', 'Modbus RTU', '400V', '5(100)A'),
        ('EMpro energy', 7, 'Energy monitoring device', 'Modbus RTU', '400V', '5(100)A'),
        ('UMG 96RM-E', 8, 'Network analyzer with Modbus', 'Modbus RTU', '400V', '5(100)A'),
        ('AcuRev 2000', 9, 'Multi-function energy meter', 'Modbus RTU', '400V', '5(100)A'),
        ('DTSU666', 10, 'Three phase energy meter', 'Modbus RTU', '400V', '5(100)A');",

        // Communications Models
        "INSERT IGNORE INTO communications_models (model_name, equipment_type, manufacturer, characteristics, communication_protocols, power_supply) VALUES 
        ('iHub IoT', 'HUB', 'Teltonika', 'IoT Gateway for remote monitoring and control', 'MQTT, Modbus, HTTP', '9-30V DC'),
        ('TRB140', 'RUT', 'Teltonika', 'Industrial LTE Cat 4 router with GPS', 'LTE, WiFi, Ethernet', '9-30V DC'),
        ('TRB 241', 'RUT', 'Teltonika', 'Industrial LTE Cat 6 router', 'LTE, WiFi, Ethernet, GNSS', '9-30V DC'),
        ('TRB 230', 'RUT', 'Teltonika', 'Industrial LTE Cat 4 router with RS232/RS485', 'LTE, WiFi, Ethernet, Serial', '9-30V DC'),
        ('Huawei SmartLogger3000', 'Logger', 'Huawei', 'Smart data logger for solar PV systems', 'Ethernet, RS485, WiFi', '100-240V AC'),
        ('Sungrow Logger COM100E', 'Logger', 'Sungrow', 'Communication logger for Sungrow inverters', 'Ethernet, RS485, WiFi', '100-240V AC'),
        ('xHub9300', 'HUB', 'Carlo Gavazzi', 'IoT Gateway for energy monitoring', 'MQTT, Modbus, REST API', '24V DC'),
        ('iHub2', 'HUB', 'Teltonika', 'Advanced IoT Gateway with edge computing', 'MQTT, Modbus, HTTP, CoAP', '9-30V DC');",

        // Commissioning Responsibles
        "INSERT IGNORE INTO commissioning_responsibles (name, email, phone, department) VALUES 
        ('Tiago França', 'tiago.franca@cleanwatts.pt', '+351 912 345 678', 'Engineering'),
        ('Luis Belo', 'luis.belo@cleanwatts.pt', '+351 913 456 789', 'Engineering'),
        ('Ruben Lopes', 'ruben.lopes@cleanwatts.pt', '+351 914 567 890', 'Engineering');",

        // Demo User
        "INSERT IGNORE INTO users (username, email, password_hash, full_name, role, is_active) VALUES 
        ('demo', 'demo@cleanwatts.pt', '\$2y\$10\$7vqsrOiilSTUX6drbR1iFej30lEBx8uGgCqAm.lNc66jAQrSt666u', 'Demo User', 'admin', 1);"
    ];

    // Execute initial data insertion
    foreach ($initialData as $query) {
        try {
            $pdo->exec($query);
        } catch (Exception $e) {
            // Log but don't fail setup for initial data
            error_log("Warning: Could not insert initial energy meter data: " . $e->getMessage());
        }
    }

    // Commit transaction if still active (DDL may auto-commit)
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo '<div style="font-family: Arial, sans-serif; max-width: 1300px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">';
    echo '<h1 style="color: #26989D;">Database Setup Complete</h1>'; // Cleanwatts success teal
    echo '<p>All required tables have been created successfully.</p>';
    echo '<p>You can now proceed to use the PV Commissioning System.</p>';
    echo '<p><a href="index.php" style="display: inline-block; background-color: #2CCCD3; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;">Go to Homepage</a></p>'; // Cleanwatts primary turquoise
    echo '</div>';
} catch (PDOException $e) {
    // Roll back transaction on error if active
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $e2) {
        // Ignore any errors during rollback attempt
    }

    echo '<div style="font-family: Arial, sans-serif; max-width: 1300px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">';
    echo '<h1 style="color: #dc3545;">Database Setup Error</h1>';
    echo '<p>An error occurred during database setup:</p>';
    echo '<p style="color: #dc3545; background-color: #f8d7da; padding: 10px; border-radius: 4px;">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Please check your database configuration and try again.</p>';
    echo '</div>';
}
