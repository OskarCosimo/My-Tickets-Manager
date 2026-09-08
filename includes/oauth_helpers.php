<?php
// includes/oauth_helpers.php
// Generic OAuth User Registration/Login Helper

require_once __DIR__ . '/config.php';

/**
 * Log in or register an OAuth user in the local database and handle 2FA check
 *
 * @param PDO $pdo
 * @param string $email
 * @param string $provider
 * @param string $providerId
 * @param string|null $username
 * @return void
 */
function process_oauth_login(PDO $pdo, string $email, string $provider, string $providerId, ?string $username = null): void {
    if (empty($email)) {
        die("OAuth authentication failed: No email provided by identity provider.");
    }

    if (empty($username)) {
        $username = explode('@', $email)[0];
    }

    // Check if user exists by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Register new user
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, role, auth_provider, auth_provider_id) VALUES (?, ?, 'user', ?, ?)");
        $stmtInsert->execute([$username, $email, $provider, $providerId]);
        
        $stmtNew = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtNew->execute([$pdo->lastInsertId()]);
        $user = $stmtNew->fetch();
    } else {
        // Update provider details
        $stmtUpdate = $pdo->prepare("UPDATE users SET auth_provider = ?, auth_provider_id = ? WHERE id = ?");
        $stmtUpdate->execute([$provider, $providerId, $user['id']]);
    }

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
    }

    // Establish full session (No 2FA required)
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_email']= $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['username']  = $user['username'] ?? $user['email'];

    header("Location: " . (in_array($user['role'], ['admin', 'agent'], true) ? "/admin/dashboard.php" : "/index.php"));
    exit;
}