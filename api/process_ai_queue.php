<?php
// api/process_ai_queue.php
// Asynchronous background worker for AI Auto-Responder queue with Concurrency Lock and Session Release
session_start();

// Rilascia immediatamente il blocco della sessione PHP per permettere
// all'utente di navigare su altre pagine senza attendere la generazione dell'IA
session_write_close();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ai_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

// Prevent timeout for long AI generations
set_time_limit(180);

// Ignore user abort so script continues running in background
ignore_user_abort(true);

// 1. Lock and pick one pending item atomically
$pdo->beginTransaction();

try {
    // Select one pending item and lock row
    $stmt = $pdo->prepare("
        SELECT q.id, q.ticket_id, t.tracking_code, t.access_token, t.guest_email, t.guest_name, t.subject, t.message 
        FROM ai_queue q 
        JOIN tickets t ON q.ticket_id = t.id 
        WHERE q.status = 'pending' AND q.attempts < 3 
        ORDER BY q.created_at ASC 
        LIMIT 1 
        FOR UPDATE
    ");
    $stmt->execute();
    $item = $stmt->fetch();

    if (!$item) {
        $pdo->commit();
        exit; // No items pending
    }

    // Mark as processing immediately inside transaction
    $stmtUpdate = $pdo->prepare("UPDATE ai_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?");
    $stmtUpdate->execute([$item['id']]);

    $pdo->commit(); // Commit lock and status change
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Queue Locking Error: " . $e->getMessage());
    exit;
}

// 2. Process AI generation outside transaction
$aiReply = generate_ai_ticket_reply($pdo, $item['subject'], $item['message']);

if ($aiReply) {
    // Insert AI reply into ticket_replies
    $stmtAiReply = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, NULL, ?)");
    $stmtAiReply->execute([$item['ticket_id'], $aiReply]);

    // Update ticket status
    $stmtTicketStatus = $pdo->prepare("UPDATE tickets SET status = 'answered' WHERE id = ?");
    $stmtTicketStatus->execute([$item['ticket_id']]);

    // Send Email notification to customer
    $recipient   = $item['guest_email'];
    $trackingUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/track.php?code=" . $item['tracking_code'] . "&token=" . $item['access_token'] . "&email=" . urlencode($recipient);

    $emailBody  = "<h3>New Reply Posted by AI Assistant</h3>";
    $emailBody .= "<blockquote style='background:#f9f9f9; padding:10px; border-left:4px solid #007bff;'>" . nl2br(htmlspecialchars(strip_tags($aiReply))) . "</blockquote>";
    $emailBody .= "<p><a href='{$trackingUrl}'>Click here to view the ticket and reply</a></p>";

    send_ticket_email($pdo, $recipient, $item['guest_name'] ?: 'Customer', "New Reply on Ticket #" . $item['tracking_code'], $emailBody);

    // Mark item as completed
    $stmtComplete = $pdo->prepare("UPDATE ai_queue SET status = 'completed' WHERE id = ?");
    $stmtComplete->execute([$item['id']]);
} else {
    // Mark item as failed
    $stmtFail = $pdo->prepare("UPDATE ai_queue SET status = 'failed' WHERE id = ?");
    $stmtFail->execute([$item['id']]);
}