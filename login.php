<?php
// login.php
// Unified Login page with OAuth integration for MYETV, Google, Facebook, Microsoft, and 2FA verification check
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/turnstile.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rateError = '';
    if (!check_rate_limit($pdo, 'login', $rateError)) {
        $error = $rateError;
    } else {
        $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
        if (!verify_turnstile($pdo, $turnstileToken)) {
            $error = 'Captcha verification failed.';
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Check password using password_verify or fallback to password column check
            $passwordValid = false;
            if ($user) {
                if (!empty($user['password_hash'])) {
                    $passwordValid = password_verify($password, $user['password_hash']);
                } elseif (!empty($user['password'])) {
                    $passwordValid = password_verify($password, $user['password']);
                }
            }

            if ($user && $passwordValid) {
                // Check if 2FA is enabled for this user account
                if ((int)($user['two_factor_enabled'] ?? 0) === 1 && !empty($user['two_factor_secret'])) {
                    $_SESSION['2fa_pending_user'] = [
                        'id'                 => $user['id'],
                        'username'           => $user['username'] ?? $user['email'],
                        'role'               => $user['role'],
                        'email'              => $user['email'],
                        'two_factor_secret'  => $user['two_factor_secret']
                    ];
                    header("Location: /login_2fa.php");
                    exit;
                } else {
                    // Complete Login directly
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_email']= $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['username']  = $user['username'] ?? $user['email'];

                    header("Location: " . (in_array($user['role'], ['admin', 'agent', 'agency'], true) ? "/admin/dashboard.php" : "/index.php"));
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$myetvEnabled  = get_setting($pdo, 'oauth_myetv_enabled') === '1';
$googleEnabled = get_setting($pdo, 'oauth_google_enabled') === '1';
$fbEnabled     = get_setting($pdo, 'oauth_facebook_enabled') === '1';
$msEnabled     = get_setting($pdo, 'oauth_microsoft_enabled') === '1';
$hasSso        = $myetvEnabled || $googleEnabled || $fbEnabled || $msEnabled;
?>

<main class="main-content">
    <div class="container my-5" style="max-width: 450px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Login</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Password</label>
                            <a href="/forgot_password.php" class="small text-decoration-none">Forgot Password?</a>
                        </div>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <?php if (get_setting($pdo, 'turnstile_enabled', '0') === '1'): ?>
                        <div class="mb-3">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                </form>

                <?php if ($hasSso): ?>
                    <hr class="my-4">

                    <!-- SSO Login Options -->
                    <div class="d-grid gap-2">
                        <?php if ($myetvEnabled): ?>
                            <a href="/auth/myetv-login.php" class="btn btn-outline-dark"><i class="fa-solid fa-tv me-2"></i> Login with MYETV</a>
                        <?php endif; ?>
                        <?php if ($googleEnabled): ?>
                            <a href="/auth/google-login.php" class="btn btn-outline-danger"><i class="fa-brands fa-google me-2"></i> Login with Google</a>
                        <?php endif; ?>
                        <?php if ($fbEnabled): ?>
                            <a href="/auth/facebook-login.php" class="btn btn-outline-primary"><i class="fa-brands fa-facebook me-2"></i> Login with Facebook</a>
                        <?php endif; ?>
                        <?php if ($msEnabled): ?>
                            <a href="/auth/microsoft-login.php" class="btn btn-outline-secondary"><i class="fa-brands fa-microsoft me-2"></i> Login with Microsoft</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>