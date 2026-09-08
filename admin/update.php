<?php
// admin/update.php
// System Automatic Updates Manager page
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/updater.php';

// Ensure user is authorized as Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

$message = '';
$error = '';

// Dynamically obtain the exact absolute path of the application root
$appRootPath = realpath(__DIR__ . '/../');

// Check write permissions for the codebase
$unwritableFiles = [];
$isSystemWritable = check_system_writable($appRootPath, $unwritableFiles);

$updateInfo = check_for_updates();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_update'])) {
    if (!$isSystemWritable) {
        $error = "Update blocked: Some files or directories are not writable by the web server.";
    } elseif ($updateInfo && !empty($updateInfo['has_update'])) {
        $result = apply_update($updateInfo['download_url']);
        if ($result['success']) {
            $message = "System updated successfully to version " . htmlspecialchars($updateInfo['version']) . "!";
            $updateInfo = check_for_updates(); // Re-check version info after update
            $isSystemWritable = check_system_writable($appRootPath, $unwritableFiles);
        } else {
            $error = "Update failed: " . htmlspecialchars($result['error']);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid my-4" style="max-width: 800px;">
        <h2><i class="fa-solid fa-arrows-rotate me-2"></i> System Updates</h2>
        <hr>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><i class="fa-solid fa-code-branch me-1"></i> Version Details</div>
            <div class="card-body">
                <p class="fs-5">Currently Installed Version: <strong>v<?php echo APP_VERSION; ?></strong></p>

                <?php if ($updateInfo && !empty($updateInfo['has_update'])): ?>
                    <div class="alert alert-warning">
                        <h4 class="alert-heading"><i class="fa-solid fa-circle-exclamation me-1"></i> New Release Available: v<?php echo htmlspecialchars($updateInfo['version']); ?></h4>
                        <p class="mb-2">We strongly recommend creating a full database backup before applying system updates.</p>
                        <?php if (!empty($updateInfo['changelog'])): ?>
                            <hr>
                            <h6>Release Notes & Changelog:</h6>
                            <pre class="bg-light p-2 rounded small"><?php echo htmlspecialchars($updateInfo['changelog']); ?></pre>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isSystemWritable): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading"><i class="fa-solid fa-lock me-1"></i> Write Permissions Required</h5>
                            <p class="mb-2">Automatic update is disabled because the web server user does not have write permissions on all files and directories.</p>
                            <p class="mb-2">Please execute the following command in your server terminal to grant permissions for this specific application root:</p>
                            
                            <code class="d-block p-2 bg-dark text-white rounded mb-3">sudo chown -R www-data:www-data <?php echo htmlspecialchars($appRootPath); ?> && sudo chmod -R 755 <?php echo htmlspecialchars($appRootPath); ?></code>
                            
                            <?php if (!empty($unwritableFiles)): ?>
                                <hr>
                                <h6>Unwritable Items Detected (<?php echo count($unwritableFiles); ?>):</h6>
                                <ul class="small mb-0">
                                    <?php foreach (array_slice($unwritableFiles, 0, 5) as $file): ?>
                                        <li><code><?php echo htmlspecialchars(str_replace($appRootPath, '', $file)); ?></code></li>
                                    <?php endforeach; ?>
                                    <?php if (count($unwritableFiles) > 5): ?>
                                        <li><em>...and <?php echo count($unwritableFiles) - 5; ?> more items.</em></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="update.php">
                        <button type="submit" name="run_update" value="1" class="btn btn-success btn-lg" <?php echo !$isSystemWritable ? 'disabled' : ''; ?> onclick="return confirm('Are you sure you want to update the system to version v<?php echo htmlspecialchars($updateInfo['version']); ?>?');">
                            <i class="fa-solid fa-download me-1"></i> Update System Now
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success m-0">
                        <i class="fa-solid fa-circle-check me-1"></i> Your system is running the latest available version.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
