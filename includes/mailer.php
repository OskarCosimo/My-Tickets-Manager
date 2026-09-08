<?php
// includes/mailer.php
// Email notification module using PHPMailer or native mail function

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';

/**
 * Autoload PHPMailer either from Composer vendor or manual directory
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

/**
 * Send an email using SMTP or native PHP mail() based on admin settings
 *
 * @param PDO $pdo
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $subject
 * @param string $bodyContent
 * @return bool
 */
function send_ticket_email(PDO $pdo, string $recipientEmail, string $recipientName, string $subject, string $bodyContent): bool {
    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $smtpEnabled = get_setting($pdo, 'smtp_enabled', '0') === '1';

    if ($smtpEnabled) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer class not found. Falling back to native mail().");
            return send_native_mail($recipientEmail, $subject, $bodyContent);
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = get_setting($pdo, 'smtp_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = get_setting($pdo, 'smtp_user');
            $mail->Password   = get_setting($pdo, 'smtp_pass');
            $mail->Port       = (int)get_setting($pdo, 'smtp_port', '587');

            // Encryption Protocol (tls / ssl / none)
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

            // Recipients
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipientEmail, $recipientName ?: 'User');

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $bodyContent;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    } else {
        return send_native_mail($recipientEmail, $subject, $bodyContent);
    }
}

/**
 * Fallback function using standard PHP mail()
 */
function send_native_mail(string $to, string $subject, string $message): bool {
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>' . "\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Notify the original author and all previous participants of a reply
 *
 * @param PDO $pdo
 * @param array $ticket
 * @param string $replyMessage
 * @param string $senderEmail
 */
function notify_ticket_participants(PDO $pdo, array $ticket, string $replyMessage, string $senderEmail): void {
    $recipients = [];

    // 1. Original ticket author (Guest or Registered User)
    if (!empty($ticket['guest_email'])) {
        $recipients[$ticket['guest_email']] = $ticket['guest_name'] ?: 'Customer';
    } elseif (!empty($ticket['user_id'])) {
        $stmtUser = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmtUser->execute([$ticket['user_id']]);
        if ($author = $stmtUser->fetch()) {
            $recipients[$author['email']] = $author['username'];
        }
    }

    // 2. Fetch emails of all users who previously replied
    $stmtReplies = $pdo->prepare("
        SELECT DISTINCT u.email, u.username 
        FROM ticket_replies tr 
        JOIN users u ON tr.user_id = u.id 
        WHERE tr.ticket_id = ? AND u.email IS NOT NULL AND u.email != ''
    ");
    $stmtReplies->execute([$ticket['id']]);
    while ($participant = $stmtReplies->fetch()) {
        if (!empty($participant['email'])) {
            $recipients[$participant['email']] = $participant['username'];
        }
    }

    // 3. If there are multiple recipients, remove the current sender.
    // If the sender is the only recipient (e.g. during self-testing), keep them so the test email is sent.
    if (count($recipients) > 1 && isset($recipients[$senderEmail])) {
        unset($recipients[$senderEmail]);
    }

    if (empty($recipients)) {
        return;
    }

    // Prepare Email Content
    $trackingUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/track.php?code=" . $ticket['tracking_code'] . "&token=" . $ticket['access_token'];
    $subject = "New Reply on Ticket #" . $ticket['tracking_code'] . ": " . $ticket['subject'];
    
    $emailBody  = "<h3>New Reply Posted</h3>";
    $emailBody .= "<p>A new reply has been added to ticket <strong>#" . htmlspecialchars($ticket['tracking_code']) . "</strong>.</p>";
    $emailBody .= "<blockquote style='background:#f9f9f9; padding:10px; border-left:4px solid #007bff;'>" . nl2br(htmlspecialchars(strip_tags($replyMessage))) . "</blockquote>";
    $emailBody .= "<p><a href='{$trackingUrl}'>Click here to view the ticket and reply</a></p>";

    // Send notifications to all recipients
    foreach ($recipients as $email => $name) {
        send_ticket_email($pdo, $email, $name, $subject, $emailBody);
    }
}