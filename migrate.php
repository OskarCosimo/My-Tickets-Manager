<?php
// migrate.php
// Safe incremental database migration script executed automatically during system updates

require_once __DIR__ . '/includes/config.php';

// Force global scope for $pdo variable when called inside functions/updaters
global $pdo;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Attempt fallback PDO instantiation if not globally set
    if (isset($db_host, $db_port, $db_name, $db_user, $db_pass)) {
        try {
            $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (Exception $e) {
            error_log("Migration PDO Connection Error: " . $e->getMessage());
            return;
        }
    } else {
        return; // Silent exit to allow updater completion
    }
}

/**
 * Helper: Check if a table exists in the database
 */
if (!function_exists('table_exists')) {
    function table_exists(PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Helper: Check if a column exists in a given table
 */
if (!function_exists('column_exists')) {
    function column_exists(PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Helper: Check if an index exists in a given table
 */
if (!function_exists('index_exists')) {
    function index_exists(PDO $pdo, string $table, string $indexName): bool {
        try {
            $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
            $stmt->execute([$indexName]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}

try {
    // --- INCREMENTAL DB MIGRATIONS HERE ---

    // v1.0.5: Add agency_id to users table for Agency role support
if (!column_exists($pdo, 'users', 'agency_id')) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `agency_id` INT UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_agency` FOREIGN KEY (`agency_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
}

    // v1.0.2: Add Password Reset Tokens
    if (!column_exists($pdo, 'users', 'reset_token')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL, ADD COLUMN `reset_token_expires` DATETIME DEFAULT NULL");
    }

    // v1.0.5: Create Rate Limits Table for Brute-Force and Spam Protection
    if (!table_exists($pdo, 'rate_limits')) {
        $pdo->exec("
            CREATE TABLE `rate_limits` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `action_key` VARCHAR(50) NOT NULL,
              `ip_address` VARCHAR(45) NOT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_action_ip_created` (`action_key`, `ip_address`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

} catch (Exception $e) {
    error_log("Database Migration Error: " . $e->getMessage());
}