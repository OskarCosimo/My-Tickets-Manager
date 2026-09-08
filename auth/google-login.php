<?php
// auth/google-login.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (get_setting($pdo, 'oauth_google_enabled', '0') !== '1') {
    die("Google authentication is currently disabled.");
}

$clientId = get_setting($pdo, 'oauth_google_client_id');
$redirectUri = urlencode('https://' . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php');

$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php',
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'prompt' => 'select_account'
]);

header("Location: " . $authUrl);
exit;