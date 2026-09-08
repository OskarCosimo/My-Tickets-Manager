<?php
// Internationalization (i18n) helper module

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set or switch language via GET parameter or SESSION
if (isset($_GET['lang'])) {
    $requestedLang = preg_replace('/[^a-z0-9_-]/i', '', $_GET['lang']);
    if (file_exists(__DIR__ . '/../translations/lang-' . $requestedLang . '.json')) {
        $_SESSION['lang'] = $requestedLang;
        setcookie('app_lang', $requestedLang, time() + (86400 * 30), "/");
    }
}

// Fallback to cookie or default 'en'
$currentLang = $_SESSION['lang'] ?? $_COOKIE['app_lang'] ?? 'en';
$langFilePath = __DIR__ . '/../translations/lang-' . $currentLang . '.json';

$translationsData = [];
if (file_exists($langFilePath)) {
    $translationsData = json_decode(file_get_contents($langFilePath), true) ?? [];
}

/**
 * Global translation function
 * 
 * @param string $key
 * @param string $default
 * @return string
 */
function __(string $key, string $default = ''): string {
    global $translationsData;
    if (isset($translationsData[$key]) && !empty($translationsData[$key])) {
        return $translationsData[$key];
    }
    return !empty($default) ? $default : $key;
}

/**
 * Get list of available translated languages
 * 
 * @return array
 */
function get_available_languages(): array {
    $languages = [];
    $files = glob(__DIR__ . '/../translations/lang-*.json');
    foreach ($files as $file) {
        $code = str_replace(['lang-', '.json'], '', basename($file));
        $languages[] = $code;
    }
    return $languages;
}