<?php
// auth/myetv-login.pnp
// Start MYETV OAuth authorization flow
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_myetv.php';

// Check if MYETV OAuth is enabled in admin settings
if (get_setting($pdo, 'oauth_myetv_enabled', '0') !== '1') {
    die("MYETV authentication is currently disabled by administrator.");
}

// Redirect user to MYETV authorization server
redirectToMyETVProvider($pdo);