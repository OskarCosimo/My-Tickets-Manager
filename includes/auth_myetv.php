<?php
// includes/auth_myetv.php
// MYETV OAuth2 Single Sign-On integration module

/**
 * Redirect user to MYETV OAuth authorization screen
 * 
 * @param PDO $pdo
 */
function redirectToMyETVProvider(PDO $pdo): void {
    $clientId = get_setting($pdo, 'oauth_myetv_client_id');
    $redirectUri = urlencode('https://' . $_SERVER['HTTP_HOST'] . '/auth/myetv-callback.php');
    
    // MYETV OAuth Authorization Endpoint with required scopes
    $authUrl = "https://developers.myetv.tv/api/oauth/authorize.php?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope=profile%20email";
    
    header("Location: " . $authUrl);
    exit;
}

/**
 * Handle OAuth callback from MYETV
 * 
 * @param PDO $pdo
 * @param string $code
 * @return array|false Returns user info array or false on failure
 */
function handleMyETVCallback(PDO $pdo, string $code) {
    $clientId = get_setting($pdo, 'oauth_myetv_client_id');
    $clientSecret = get_setting($pdo, 'oauth_myetv_client_secret');
    $redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/myetv-callback.php';

    // Token Exchange Request Endpoint
    $tokenUrl = "https://developers.myetv.tv/api/oauth/token.php";
    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        return false;
    }

    // Fetch User Profile with Access Token
    $userUrl = "https://developers.myetv.tv/api/v1/profile.php";
    $ch = curl_init($userUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token']
    ]);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    return json_decode($userResponse, true);
}