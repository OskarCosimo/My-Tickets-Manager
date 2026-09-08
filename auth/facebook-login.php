<?php
// auth/facebook-login.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (get_setting($pdo, 'oauth_facebook_enabled', '0') !== '1') {
    die("Facebook authentication is currently disabled.");
}

$appId = get_setting($pdo, 'oauth_facebook_client_id');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/facebook-callback.php';

$authUrl = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'scope' => 'email,public_profile'
]);

header("Location: " . $authUrl);
exit;