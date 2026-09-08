<?php
// includes/updater.php
// GitHub Releases update checker and automatic package extraction engine

require_once __DIR__ . '/version.php';

/**
 * Check if a new version release is available on GitHub
 *
 * @return array|false
 */
function check_for_updates() {
    $url = "https://api.github.com/repos/" . GITHUB_REPO . "/releases/latest";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Tickets-Manager-Updater');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return false;
    }

    $data = json_decode($response, true);
    if (!isset($data['tag_name'])) {
        return false;
    }

    $latestVersion = ltrim($data['tag_name'], 'v');

    if (version_compare($latestVersion, APP_VERSION, '>')) {
        return [
            'has_update'   => true,
            'version'      => $latestVersion,
            'download_url' => $data['zipball_url'],
            'changelog'    => $data['body'] ?? ''
        ];
    }

    return ['has_update' => false, 'version' => APP_VERSION];
}

/**
 * Recursively verify if all directories and files are writable by web server
 *
 * @param string $dir
 * @param array $unwritableItems
 * @return bool
 */
function check_system_writable(string $dir = __DIR__ . '/../', array &$unwritableItems = []): bool {
    $dir = realpath($dir);
    if (!$dir || !is_writable($dir)) {
        $unwritableItems[] = $dir ?: 'Root Directory';
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.git') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (!is_writable($path)) {
            $unwritableItems[] = $path;
        }

        if (is_dir($path)) {
            check_system_writable($path, $unwritableItems);
        }
    }

    return empty($unwritableItems);
}

/**
 * Download and extract zip release package safely without overwriting sensitive files
 *
 * @param string $downloadUrl
 * @return array
 */
function apply_update(string $downloadUrl): array {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'error' => 'ZipArchive PHP extension is not enabled on this server.'];
    }

    // Pre-check write permissions before starting update
    $unwritable = [];
    if (!check_system_writable(__DIR__ . '/../', $unwritable)) {
        return [
            'success' => false, 
            'error'   => 'Cannot proceed with update: some files or directories are not writable by the web server. Please fix permissions using "sudo chown -R www-data:www-data ." and try again.'
        ];
    }

    $tempZipPath = __DIR__ . '/../tmp_update.zip';
    $extractPath = __DIR__ . '/../';

    // List of files and directories protected from automatic overwrite
    $protectedFiles = [
        'includes/config.php',
        'config.php',
        '.htaccess',
        'assets/custom/'
    ];

    // 1. Download Zip archive from GitHub
    $fp = fopen($tempZipPath, 'w+');
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Tickets-Manager-Updater');
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (!file_exists($tempZipPath) || filesize($tempZipPath) < 1000) {
        @unlink($tempZipPath);
        return ['success' => false, 'error' => 'Download failed or update package is corrupted.'];
    }

    // 2. Extract Zip package
    $zip = new ZipArchive();
    if ($zip->open($tempZipPath) === TRUE) {
        // GitHub wraps release files inside a root folder like 'username-repo-hash/'
        $rootFolderInZip = $zip->getNameIndex(0);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Strip GitHub root folder prefix
            $relativePath = preg_replace('/^' . preg_quote($rootFolderInZip, '/') . '/', '', $filename);

            if (empty($relativePath)) {
                continue;
            }

            $targetPath = $extractPath . $relativePath;

            // Check if file is protected from overwrite
            $isProtected = false;
            foreach ($protectedFiles as $protected) {
                if (strpos($relativePath, $protected) === 0) {
                    $isProtected = true;
                    break;
                }
            }

            // Skip protected files if they already exist on the server
            if ($isProtected && file_exists($targetPath)) {
                continue;
            }

            if (substr($filename, -1) === '/') {
                if (!file_exists($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $dir = dirname($targetPath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy("zip://" . $tempZipPath . "#" . $filename, $targetPath);
            }
        }
        $zip->close();
        @unlink($tempZipPath);

        // 3. Execute database migration script if present in update package
        if (file_exists($extractPath . 'migrate.php')) {
            require_once $extractPath . 'migrate.php';
            @unlink($extractPath . 'migrate.php');
        }

        return ['success' => true];
    }

    @unlink($tempZipPath);
    return ['success' => false, 'error' => 'Unable to open or extract update Zip archive.'];
}