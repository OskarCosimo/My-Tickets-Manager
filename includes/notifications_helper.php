<?php
// includes/notifications_helper.php
// Internal Notification Dispatcher Engine

/**
 * Send internal notification to a specific user
 */
function create_internal_notification(PDO $pdo, int $userId, int $ticketId, string $title, string $message): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, ticket_id, title, message) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $ticketId, $title, $message]);
    } catch (Exception $e) {
        error_log("Internal Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send internal notification to all staff members following or assigned to a ticket
 */
function notify_ticket_followers(PDO $pdo, array $ticket, string $title, string $message, ?int $excludeUserId = null): void {
    $ticketId = (int)$ticket['id'];
    $recipients = [];

    // 1. Direct Assigned User
    if (!empty($ticket['assigned_to'])) {
        $recipients[] = (int)$ticket['assigned_to'];
    }

    // 2. Fetch all followers from ticket_followers
    $stmtFollowers = $pdo->prepare("SELECT user_id FROM ticket_followers WHERE ticket_id = ?");
    $stmtFollowers->execute([$ticketId]);
    while ($row = $stmtFollowers->fetch()) {
        $recipients[] = (int)$row['user_id'];
    }

    // 3. Fetch auto-assign staff users
    $stmtAutoAssign = $pdo->query("SELECT id FROM users WHERE auto_assign_tickets = 1 AND role IN ('admin', 'agency', 'agent') AND is_banned = 0");
    while ($row = $stmtAutoAssign->fetch()) {
        $recipients[] = (int)$row['id'];
    }

    // Remove duplicate IDs and excluded user (current actor)
    $recipients = array_unique($recipients);
    if ($excludeUserId) {
        $recipients = array_diff($recipients, [$excludeUserId]);
    }

    foreach ($recipients as $recipientId) {
        create_internal_notification($pdo, $recipientId, $ticketId, $title, $message);
    }
}