<?php
// includes/rate_limiter.php
// Dynamic Rate Limiter helper powered by MySQL and Admin settings

/**
 * Check rate limit for a specific action.
 * 
 * @param PDO $pdo
 * @param string $actionKey (es. 'login', 'forgot_password', 'submit_ticket', 'ticket_reply')
 * @param string $errorMsg Error message returned if limit exceeded
 * @return string Error message if rate limited, empty string if allowed
 */
function check_rate_limit(PDO $pdo, string $actionKey, string &$errorMsg = ''): bool {
    // Determine client IP
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
          ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
          ?? $_SERVER['REMOTE_ADDR'] 
          ?? '127.0.0.1';

    // Handle multiple proxy IPs
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    // Read limit rules from settings (defaults: max 5 attempts in 60 seconds)
    $maxAttemptsSetting = "rate_limit_{$actionKey}_max";
    $timeWindowSetting  = "rate_limit_{$actionKey}_seconds";

    $maxAttempts = (int)get_setting($pdo, $maxAttemptsSetting, '5');
    $timeWindow  = (int)get_setting($pdo, $timeWindowSetting, '60');

    // If rate limiting is disabled (0), allow immediately
    if ($maxAttempts <= 0 || $timeWindow <= 0) {
        return true;
    }

    // 1. Cleanup expired attempts globally for this action (keep DB light)
    $stmtClean = $pdo->prepare("
        DELETE FROM rate_limits 
        WHERE action_key = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmtClean->execute([$actionKey, $timeWindow]);

    // 2. Count active attempts from this IP within the time window
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM rate_limits 
        WHERE action_key = ? AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmtCount->execute([$actionKey, $ip, $timeWindow]);
    $currentAttempts = (int)$stmtCount->fetchColumn();

    if ($currentAttempts >= $maxAttempts) {
        $errorMsg = "Too many attempts for this action. Please wait {$timeWindow} seconds before trying again.";
        return false;
    }

    // 3. Register current attempt
    $stmtInsert = $pdo->prepare("INSERT INTO rate_limits (action_key, ip_address) VALUES (?, ?)");
    $stmtInsert->execute([$actionKey, $ip]);

    return true;
}