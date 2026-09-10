<?php
// profile.php
// User Profile Settings Page with QR Code 2FA Activation & Ticket Auto-Assignment Settings
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: /logout.php");
    exit;
}

// Generate temp secret if 2FA is disabled
if (empty($_SESSION['temp_2fa_secret'])) {
    $_SESSION['temp_2fa_secret'] = generate_totp_secret();
}
$tempSecret = $_SESSION['temp_2fa_secret'];
$siteTitle  = get_setting($pdo, 'site_title', 'Support Tickets');
$qrUri      = get_totp_qr_url($user['email'], $siteTitle, $tempSecret);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Profile Info
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');

        if (empty($username)) {
            $error = 'Username cannot be empty.';
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            if ($stmtUpdate->execute([$username, $userId])) {
                $_SESSION['username'] = $username;
                $user['username'] = $username;
                $success = 'Profile information updated successfully.';
            } else {
                $error = 'Failed to update profile information.';
            }
        }
    }

    // Update Ticket Auto-Assignment Setting (Agencies & Agents only)
    if ($action === 'update_auto_assign' && in_array($user['role'], ['agency', 'agent'], true)) {
        $autoAssign = isset($_POST['auto_assign_tickets']) ? 1 : 0;
        
        $stmtAssign = $pdo->prepare("UPDATE users SET auto_assign_tickets = ? WHERE id = ?");
        if ($stmtAssign->execute([$autoAssign, $userId])) {
            $user['auto_assign_tickets'] = $autoAssign;
            $success = 'Ticket management preferences updated successfully.';
        } else {
            $error = 'Failed to update auto-assignment setting.';
        }
    }

    // Change Password
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtPass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmtPass->execute([$hashedPassword, $userId])) {
                $user['password_hash'] = $hashedPassword;
                $success = 'Password changed successfully.';
            } else {
                $error = 'Failed to update password.';
            }
        }
    }

    // Enable 2FA (Verify TOTP code)
    if ($action === 'enable_2fa') {
        $totpCode = trim($_POST['totp_code'] ?? '');

        if (verify_totp_code($tempSecret, $totpCode)) {
            $stmt2FA = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?");
            $stmt2FA->execute([$tempSecret, $userId]);
            
            unset($_SESSION['temp_2fa_secret']);
            $user['two_factor_enabled'] = 1;
            $user['two_factor_secret']  = $tempSecret;
            $success = 'Two-Factor Authentication (2FA) has been enabled for your account.';
        } else {
            $error = 'Invalid 2FA Verification Code. Please try again.';
        }
    }

    // Disable 2FA
    if ($action === 'disable_2fa') {
        $stmtDisable = $pdo->prepare("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE id = ?");
        $stmtDisable->execute([$userId]);

        $user['two_factor_enabled'] = 0;
        $user['two_factor_secret']  = null;
        $_SESSION['temp_2fa_secret'] = generate_totp_secret();
        $success = 'Two-Factor Authentication (2FA) has been disabled.';
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- QRCode.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<main class="main-content">
    <div class="container my-4" style="max-width: 800px;">
        <h2><i class="fa-solid fa-user-gear me-2"></i> Account Settings</h2>
        <hr>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <!-- Personal Information Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white">
                <i class="fa-solid fa-id-card me-1"></i> Personal Information
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username / Display Name</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Information</button>
                </form>
            </div>
        </div>

        <!-- Ticket Auto-Assignment Card (For Agencies and Agents) -->
        <?php if (in_array($user['role'], ['agency', 'agent'], true)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fa-solid fa-robot me-1"></i> Ticket Management Settings
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="update_auto_assign">
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_assign_tickets" value="1" id="autoAssignSwitch" <?php echo !empty($user['auto_assign_tickets']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="autoAssignSwitch">
                                Automatically assign new incoming tickets to my account
                            </label>
                        </div>
                        
                        <p class="text-muted small mb-3">
                            <?php if ($user['role'] === 'agency'): ?>
                                When enabled, new tickets will be automatically assigned to your agency upon creation and will instantly become accessible to all your agents.
                            <?php else: ?>
                                When enabled, new incoming tickets will be automatically assigned directly to your agent account upon submission.
                            <?php endif; ?>
                        </p>

                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Preferences</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Security & Password Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <i class="fa-solid fa-key me-1"></i> Security & Change Password
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning"><i class="fa-solid fa-shield-halved me-1"></i> Update Password</button>
                </form>
            </div>
        </div>

        <!-- Two-Factor Authentication (2FA) Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-mobile-screen-button me-1"></i> Two-Factor Authentication (2FA)</span>
                <?php if ($user['two_factor_enabled']): ?>
                    <span class="badge bg-success">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Disabled</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($user['two_factor_enabled']): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check me-1"></i> Two-Factor Authentication is currently active on your account.
                    </div>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="disable_2fa">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable 2FA?');">
                            <i class="fa-solid fa-lock-open me-1"></i> Disable Two-Factor Authentication
                        </button>
                    </form>
                <?php else: ?>
                    <p>To enable 2FA, scan the QR code below with your Authenticator App (Google Authenticator, Authy, etc.) and enter the generated 6-digit code to confirm setup.</p>
                    
                    <div class="row align-items-center mb-3">
                        <div class="col-md-4 text-center">
                            <div id="qrcode" class="p-2 border bg-white d-inline-block rounded"></div>
                        </div>
                        <div class="col-md-8">
                            <p class="mb-1"><strong>Secret Key (Manual Entry):</strong></p>
                            <code class="fs-5 bg-light p-2 rounded d-block mb-3 text-break"><?php echo htmlspecialchars($tempSecret); ?></code>

                            <form method="POST" action="profile.php">
                                <input type="hidden" name="action" value="enable_2fa">
                                <div class="input-group mb-2">
                                    <input type="text" name="totp_code" class="form-control" placeholder="Enter 6-digit code" maxlength="6" required autocomplete="off">
                                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i> Verify & Enable 2FA</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            new QRCode(document.getElementById("qrcode"), {
                                text: "<?php echo $qrUri; ?>",
                                width: 150,
                                height: 150
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>