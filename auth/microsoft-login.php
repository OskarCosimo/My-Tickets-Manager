<?php
// auth/microsoft-login.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (get_setting($pdo, 'oauth_microsoft_enabled', '0') !== '1') {
    die("Microsoft authentication is currently disabled.");
}

$clientId = get_setting($pdo, 'oauth_microsoft_client_id');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/microsoft-callback.php';

$authUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?" . http_build_query([
    'client_id' => $clientId,
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'response_mode' => 'query',
    'scope' => 'openid profile email User.Read'
]);

header("Location: " . $authUrl);
exit;