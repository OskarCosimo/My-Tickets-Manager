<?php
// test_mail.php
session_start();
require_once __DIR__ . '/includes/config.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die("Composer autoload not found in /vendor/autoload.php");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

echo "<h3>SMTP Diagnostics Test</h3>";

$smtpEnabled = get_setting($pdo, 'smtp_enabled', '0');
echo "<strong>SMTP Enabled in Settings:</strong> " . ($smtpEnabled === '1' ? 'YES' : 'NO (Enable it in Admin Settings!)') . "<br>";
echo "<strong>Host:</strong> " . htmlspecialchars(get_setting($pdo, 'smtp_host')) . "<br>";
echo "<strong>Port:</strong> " . htmlspecialchars(get_setting($pdo, 'smtp_port')) . "<br>";
echo "<strong>Crypto:</strong> " . htmlspecialchars(get_setting($pdo, 'smtp_crypto')) . "<br>";
echo "<strong>User:</strong> " . htmlspecialchars(get_setting($pdo, 'smtp_user')) . "<br><hr>";

$mail = new PHPMailer(true);

try {
    // Enable full verbose debug output
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;

    $mail->isSMTP();
    $mail->Host       = get_setting($pdo, 'smtp_host');
    $mail->SMTPAuth   = true;
    $mail->Username   = get_setting($pdo, 'smtp_user');
    $mail->Password   = get_setting($pdo, 'smtp_pass');
    $mail->Port       = (int)get_setting($pdo, 'smtp_port', '587');

    $crypto = strtolower(get_setting($pdo, 'smtp_crypto', 'tls'));
    if ($crypto === 'tls' || $crypto === 'starttls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($crypto === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }

    $fromEmail = get_setting($pdo, 'smtp_from_email', get_setting($pdo, 'smtp_user'));
    $fromName  = get_setting($pdo, 'site_title', 'Support Team');

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($fromEmail, 'Test Recipient');

    $mail->isHTML(true);
    $mail->Subject = 'Test Email from My Tickets Manager';
    $mail->Body    = 'This is a test email to verify SMTP configuration.';

    echo "<pre>";
    $mail->send();
    echo "</pre>";
    echo "<h4 style='color:green;'>SUCCESS: Email sent successfully!</h4>";

} catch (Exception $e) {
    echo "</pre>";
    echo "<h4 style='color:red;'>FAILED: Email could not be sent.</h4>";
    echo "<strong>Error details:</strong> " . htmlspecialchars($mail->ErrorInfo);
}