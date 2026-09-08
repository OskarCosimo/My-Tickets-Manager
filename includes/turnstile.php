<?php
// includes/turnstile.php
// Cloudflare Turnstile verification module

/**
 * Validate Cloudflare Turnstile token
 * 
 * @param PDO $pdo
 * @param string $token
 * @return bool
 */
function verify_turnstile(PDO $pdo, string $token): bool {
    $enabled = get_setting($pdo, 'turnstile_enabled', '0');
    if ($enabled !== '1') {
        return true; // Skip verification if disabled in admin panel
    }

    $secretKey = get_setting($pdo, 'turnstile_secret_key');
    if (empty($token) || empty($secretKey)) {
        return false;
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return false;
    }

    $responseData = json_decode($response, true);
    return isset($responseData['success']) && $responseData['success'] === true;
}