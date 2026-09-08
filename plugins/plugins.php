<?php
// Plugin Engine & Event Hooks System
global $pluginHooks;
$pluginHooks = [];

/**
 * Register a callback function to a specific event hook
 */
function add_hook(string $hookName, callable $callback): void {
    global $pluginHooks;
    if (!isset($pluginHooks[$hookName])) {
        $pluginHooks[$hookName] = [];
    }
    $pluginHooks[$hookName][] = $callback;
}

/**
 * Trigger an event hook and execute registered callbacks
 */
function trigger_hook(string $hookName, array $args = []): void {
    global $pluginHooks;
    if (isset($pluginHooks[$hookName])) {
        foreach ($pluginHooks[$hookName] as $callback) {
            if (is_callable($callback)) {
                call_user_func_array($callback, [$args]);
            }
        }
    }
}

/**
 * Automatically load all PHP files inside subdirectories of /plugins
 */
function load_plugins(?PDO $pdo = null): void {
    $pluginsDir = __DIR__ . '/';
    if (is_dir($pluginsDir)) {
        $pluginFiles = glob($pluginsDir . '*/*.php');
        foreach ($pluginFiles as $file) {
            require_once $file;
        }
    }
}

// Auto-load plugins safely using global $pdo instance
global $pdo;
load_plugins($pdo);