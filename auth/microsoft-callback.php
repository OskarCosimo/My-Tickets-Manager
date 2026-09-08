<?php
// auth/microsoft-callback.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/oauth_helpers.php';

$code = $_GET['code'] ?? '';
if (empty($code)) {
    die("Microsoft authorization code missing.");
}

$clientId = get_setting($pdo, 'oauth_microsoft_client_id');
$clientSecret = get_setting($pdo, 'oauth_microsoft_client_secret');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/microsoft-callback.php';

// Exchange code for Access Token
$tokenUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/token";
$postData = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
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
    die("Failed to retrieve Microsoft access token.");
}

// Fetch user profile from Microsoft Graph API
$graphUrl = "https://graph.microsoft.com/v1.0/me";
$ch = curl_init($graphUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

// Microsoft accounts might return userPrincipalName instead of mail
$email = $userData['mail'] ?? $userData['userPrincipalName'] ?? '';

process_oauth_login(
    $pdo,
    $email,
    'microsoft',
    $userData['id'] ?? '',
    $userData['displayName'] ?? null
);