<?php
// install.php
// Web Installer for PHP Tickets Manager

session_start();

$configFile = __DIR__ . '/includes/config.php';

// Prevent re-installation if config already exists
if (file_exists($configFile)) {
    die("Application is already installed. If you want to re-install, delete 'includes/config.php' first.");
}

// 1. System Requirements & Essential Directory Checks
$requirements = [
    'php_version' => [
        'name'    => 'PHP Version (>= 8.0)',
        'passed'  => version_compare(PHP_VERSION, '8.0.0', '>='),
        'message' => 'PHP ' . PHP_VERSION . ' installed.'
    ],
    'ext_pdo' => [
        'name'    => 'PDO MySQL Extension',
        'passed'  => extension_loaded('pdo_mysql'),
        'message' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing pdo_mysql extension.'
    ],
    'ext_zip' => [
        'name'    => 'ZipArchive Extension (for Updates)',
        'passed'  => class_exists('ZipArchive'),
        'message' => class_exists('ZipArchive') ? 'Installed' : 'Missing php-zip extension.'
    ],
    'ext_curl' => [
        'name'    => 'cURL Extension',
        'passed'  => extension_loaded('curl'),
        'message' => extension_loaded('curl') ? 'Installed' : 'Missing php-curl extension.'
    ]
];

// Check only essential folder permissions
$rootWritable     = is_writable(__DIR__);
$includesWritable = is_writable(__DIR__ . '/includes');

$allRequirementsMet = $rootWritable && $includesWritable;
foreach ($requirements as $req) {
    if (!$req['passed']) {
        $allRequirementsMet = false;
        break;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allRequirementsMet) {
    $dbHost     = trim($_POST['db_host'] ?? 'localhost');
    $dbPort     = trim($_POST['db_port'] ?? '3306');
    $dbName     = trim($_POST['db_name'] ?? '');
    $dbUser     = trim($_POST['db_user'] ?? '');
    $dbPass     = $_POST['db_pass'] ?? '';

    $siteTitle  = trim($_POST['site_title'] ?? 'Support Tickets');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminUser  = trim($_POST['admin_user'] ?? 'admin');
    $adminPass  = $_POST['admin_pass'] ?? '';

    if (empty($dbName) || empty($dbUser) || empty($adminEmail) || empty($adminPass)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Test Database Connection
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;");
            $pdo->exec("USE `$dbName`;");

            // Import SQL Schema
            if (!file_exists(__DIR__ . '/database.sql')) {
                throw new Exception("Missing 'database.sql' file in root directory.");
            }
            $sqlSchema = file_get_contents(__DIR__ . '/database.sql');
            $pdo->exec($sqlSchema);

            // Insert Initial Settings
            $stmtSetting = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmtSetting->execute(['site_title', $siteTitle]);
            $stmtSetting->execute(['turnstile_enabled', '0']);

            // Insert default category
            $pdo->exec("INSERT IGNORE INTO categories (id, name, description) VALUES (1, 'General Support', 'General help and inquiries');");

            // Create Admin Account
            $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtAdmin = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $stmtAdmin->execute([$adminUser, $adminEmail, $hashedPass]);

            // Generate includes/config.php File
            $configContent = "<?php\n";
            $configContent .= "// includes/config.php\n";
            $configContent .= "// Database connection and system configuration\n\n";
            $configContent .= "\$db_host = " . var_export($dbHost, true) . ";\n";
            $configContent .= "\$db_port = " . var_export($dbPort, true) . ";\n";
            $configContent .= "\$db_name = " . var_export($dbName, true) . ";\n";
            $configContent .= "\$db_user = " . var_export($dbUser, true) . ";\n";
            $configContent .= "\$db_pass = " . var_export($dbPass, true) . ";\n\n";
            $configContent .= "try {\n";
            $configContent .= "    \$pdo = new PDO(\"mysql:host=\$db_host;port=\$db_port;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass, [\n";
            $configContent .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
            $configContent .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC\n";
            $configContent .= "    ]);\n";
            $configContent .= "} catch (PDOException \$e) {\n";
            $configContent .= "    die(\"Database connection failed: \" . \$e->getMessage());\n";
            $configContent .= "}\n\n";
            $configContent .= "if (!function_exists('get_setting')) {\n";
            $configContent .= "    function get_setting(PDO \$pdo, string \$key, string \$default = ''): string {\n";
            $configContent .= "        \$stmt = \$pdo->prepare(\"SELECT setting_value FROM settings WHERE setting_key = ?\");\n";
            $configContent .= "        \$stmt->execute([\$key]);\n";
            $configContent .= "        \$val = \$stmt->fetchColumn();\n";
            $configContent .= "        return \$val !== false ? \$val : \$default;\n";
            $configContent .= "    }\n";
            $configContent .= "}\n";

            file_put_contents($configFile, $configContent);

            $success = 'Installation completed successfully! You can now log in.';
        } catch (Exception $e) {
            $error = 'Installation Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install - PHP Tickets Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container my-5" style="max-width: 700px;">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center py-3">
                <h3 class="m-0"><i class="fa-solid fa-ticket me-2"></i> PHP Tickets Manager Setup</h3>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check me-1"></i> <?php echo $success; ?>
                        <hr>
                        <a href="/login.php" class="btn btn-success w-100 btn-lg">Go to Login</a>
                    </div>
                <?php else: ?>

                    <!-- Pre-installation Checks Summary Card -->
                    <div class="card mb-4 border shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <i class="fa-solid fa-list-check me-1"></i> Pre-Installation System Check
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($requirements as $req): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo $req['name']; ?></span>
                                    <?php if ($req['passed']): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> <?php echo $req['message']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> <?php echo $req['message']; ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>

                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Root Directory Permissions (<code>/</code>)</span>
                                <?php if ($rootWritable): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Writable</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Permission Denied</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Includes Directory Permissions (<code>/includes</code>)</span>
                                <?php if ($includesWritable): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Writable</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Permission Denied</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>

                    <?php if (!$allRequirementsMet): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>System Requirements Warning:</strong> Required PHP extensions or directory permissions are missing. Please fix directory permissions (e.g. <code>sudo chown -R www-data:www-data .</code>) and reload this page to continue.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="install.php">
                            <h5>1. Database Configuration</h5>
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Port</label>
                                    <input type="text" name="db_port" class="form-control" value="3306" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="db_name" class="form-control" value="ticketsmanager" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Database User</label>
                                    <input type="text" name="db_user" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Database Password</label>
                                    <input type="password" name="db_pass" class="form-control">
                                </div>
                            </div>

                            <hr>
                            <h5>2. Administrator Account & Site Info</h5>
                            <div class="mb-3">
                                <label class="form-label">Site Title</label>
                                <input type="text" name="site_title" class="form-control" value="Support Tickets" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Admin Username</label>
                                    <input type="text" name="admin_user" class="form-control" value="admin" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" name="admin_email" class="form-control" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Admin Password</label>
                                    <input type="password" name="admin_pass" class="form-control" required minlength="8">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="fa-solid fa-rocket me-1"></i> Install System Now</button>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>