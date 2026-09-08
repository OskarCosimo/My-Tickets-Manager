<?php
// auth/google-callback.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/oauth_helpers.php';

$code = $_GET['code'] ?? '';
if (empty($code)) {
    die("Google authorization code missing.");
}

$clientId = get_setting($pdo, 'oauth_google_client_id');
$clientSecret = get_setting($pdo, 'oauth_google_client_secret');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php';

// Exchange code for Access Token
$tokenUrl = "https://oauth2.googleapis.com/token";
$postData = [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    die("Failed to retrieve Google access token.");
}

// Fetch user profile
$userInfoUrl = "https://www.googleapis.com/oauth2/v2/userinfo";
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

process_oauth_login(
    $pdo,
    $userData['email'] ?? '',
    'google',
    $userData['id'] ?? '',
    $userData['name'] ?? null
);