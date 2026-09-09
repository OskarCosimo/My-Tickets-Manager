<?php
// register.php
// Public user registration page with Agency referral & Agency registration support
session_start();
require_once __DIR__ . '/includes/config.php';

$error = '';
$success = '';

// Check if requested role is agency (e.g. register.php?role=agency)
$requestedRole = trim($_GET['role'] ?? $_POST['requested_role'] ?? 'user');
$assignedRole  = ($requestedRole === 'agency') ? 'agency' : 'user';

// Capture and validate Agency ID from referral link (e.g. register.php?agency=12)
$agencyId = (int)($_GET['agency'] ?? $_POST['agency_id'] ?? 0);
if ($agencyId > 0) {
    $stmtAgency = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'agency'");
    $stmtAgency->execute([$agencyId]);
    if (!$stmtAgency->fetch()) {
        $agencyId = null;
    }
} else {
    $agencyId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username      = trim($_POST['username'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $requestedRole = trim($_POST['requested_role'] ?? 'user');
    $assignedRole  = ($requestedRole === 'agency') ? 'agency' : 'user';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email or username is already registered
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmtCheck->execute([$email, $username]);
        
        if ($stmtCheck->fetch()) {
            $error = "Email or Username/Agency Name is already registered.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with the correct role ('agency' or 'user') and agency_id if present
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, agency_id) VALUES (?, ?, ?, ?, ?)");
            if ($stmtInsert->execute([$username, $email, $passwordHash, $assignedRole, $agencyId])) {
                $success = "Registration completed successfully as " . strtoupper($assignedRole) . "! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-5" style="max-width: 500px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="m-0">
                <i class="fa-solid <?php echo $assignedRole === 'agency' ? 'fa-building' : 'fa-user-plus'; ?> me-2"></i>
                <?php echo $assignedRole === 'agency' ? 'Agency Registration' : 'Register Account'; ?>
            </h4>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <a href="/login.php" class="btn btn-primary w-100">Go to Login</a>
            <?php else: ?>
                <form method="POST" action="register.php">
                    <input type="hidden" name="requested_role" value="<?php echo htmlspecialchars($assignedRole); ?>">

                    <?php if ($agencyId): ?>
                        <input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>">
                        <div class="alert alert-info small py-2">
                            <i class="fa-solid fa-building me-1"></i> Registering via official Agency invitation #<?php echo $agencyId; ?>.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label"><?php echo $assignedRole === 'agency' ? 'Agency Name' : 'Username'; ?></label>
                        <input type="text" name="username" class="form-control" placeholder="<?php echo $assignedRole === 'agency' ? 'e.g. My Agency LLC' : 'e.g. john_doe'; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="your.email@domain.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-check me-1"></i> <?php echo $assignedRole === 'agency' ? 'Register Agency' : 'Create Account'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>