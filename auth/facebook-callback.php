<?php
// auth/facebook-callback.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/oauth_helpers.php';

$code = $_GET['code'] ?? '';
if (empty($code)) {
    die("Facebook authorization code missing.");
}

$appId = get_setting($pdo, 'oauth_facebook_client_id');
$appSecret = get_setting($pdo, 'oauth_facebook_client_secret');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/facebook-callback.php';

// Exchange code for Access Token
$tokenUrl = "https://graph.facebook.com/v18.0/oauth/access_token?" . http_build_query([
    'client_id' => $appId,
    'client_secret' => $appSecret,
    'redirect_uri' => $redirectUri,
    'code' => $code
]);

$response = file_get_contents($tokenUrl);
$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    die("Failed to retrieve Facebook access token.");
}

// Fetch user profile
$graphUrl = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . $tokenData['access_token'];
$userResponse = file_get_contents($graphUrl);
$userData = json_decode($userResponse, true);

process_oauth_login(
    $pdo,
    $userData['email'] ?? '',
    'facebook',
    $userData['id'] ?? '',
    $userData['name'] ?? null
);