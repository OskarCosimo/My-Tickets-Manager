<?php
// reset_password.php
// Validates token and updates user password
session_start();
require_once __DIR__ . '/includes/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;

if (empty($token)) {
    die("Invalid or missing reset token.");
}

// Check if token exists and is not expired
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "This password reset link is invalid or has expired. Please request a new one.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword     = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and invalidate token
        $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmtUpdate->execute([$hashedPassword, $user['id']]);

        $success = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-5" style="max-width: 500px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="m-0"><i class="fa-solid fa-lock me-2"></i> Set New Password</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check me-1"></i> Your password has been updated successfully!
                </div>
                <a href="/login.php" class="btn btn-success w-100 btn-lg">Go to Login</a>
            <?php else: ?>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if ($user): ?>
                    <form method="POST" action="reset_password.php">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-floppy-disk me-1"></i> Update Password</button>
                    </form>
                <?php else: ?>
                    <a href="/forgot_password.php" class="btn btn-outline-primary w-100">Request New Reset Link</a>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>