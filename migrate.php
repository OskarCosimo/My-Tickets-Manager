<?php
// migrate.php
// Safe incremental database migration script executed automatically during system updates

require_once __DIR__ . '/includes/config.php';

if (!isset($pdo)) {
    exit('Database connection unavailable.');
}

/**
 * Helper: Check if a column exists in a given table
 */
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Helper: Check if an index exists in a given table
 */
function index_exists(PDO $pdo, string $table, string $indexName): bool {
    try {
        $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}
