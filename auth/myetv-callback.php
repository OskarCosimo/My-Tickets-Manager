<?php
// auth/myetv-callback.php
// MYETV OAuth Callback handler
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_myetv.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    die("Authorization code missing from MYETV response.");
}

// Handle callback and retrieve MYETV user data
$myetvUser = handleMyETVCallback($pdo, $code);

if (!$myetvUser || !isset($myetvUser['email'])) {
    die("Failed to authenticate with MYETV API.");
}

$email = $myetvUser['email'];
$myetvId = $myetvUser['id'] ?? $email;
$username = $myetvUser['username'] ?? explode('@', $email)[0];

// Check if user already exists in local database
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Create new account for MYETV user
    $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, role, auth_provider, auth_provider_id) VALUES (?, ?, 'user', 'myetv', ?)");
    $stmtInsert->execute([$username, $email, $myetvId]);
    
    $stmtNew = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtNew->execute([$pdo->lastInsertId()]);
    $user = $stmtNew->fetch();
} else {
    // Update auth provider info if previously local
    $stmtUpdate = $pdo->prepare("UPDATE users SET auth_provider = 'myetv', auth_provider_id = ? WHERE id = ?");
    $stmtUpdate->execute([$myetvId, $user['id']]);
}

// Check 2FA Status
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

// Establish session directly
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_email']= $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['username']  = $user['username'] ?? $user['email'];

// Redirect based on role
header("Location: " . (in_array($user['role'], ['admin', 'agent'], true) ? "/admin/dashboard.php" : "/index.php"));
exit;