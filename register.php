<?php
// register.php
// User registration page
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/turnstile.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate Turnstile Captcha
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    if (!verify_turnstile($pdo, $turnstileToken)) {
        $error = 'Captcha verification failed. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Check if email or username already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmtCheck->execute([$email, $username]);
            if ($stmtCheck->fetch()) {
                $error = 'Email or Username already registered.';
            } else {
                // Create user with standard BCRYPT password hash
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, auth_provider) VALUES (?, ?, ?, 'user', 'local')");
                
                if ($stmt->execute([$username, $email, $passwordHash])) {
                    $success = 'Account created successfully! You can now <a href="/login.php">login</a>.';
                } else {
                    $error = 'Registration failed. Please try again later.';
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="main-content">
    <div class="container my-5" style="max-width: 500px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Create an Account</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>

                    <?php if (get_setting($pdo, 'turnstile_enabled', '0') === '1'): ?>
                        <div class="mb-3">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>

                <div class="text-center mt-3">
                    <small>Already have an account? <a href="/login.php">Login here</a></small>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>