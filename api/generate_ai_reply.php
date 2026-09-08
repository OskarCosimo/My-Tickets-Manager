<?php
// api/generate_ai_reply.php
// Endpoint for generating AI replies via AJAX
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ai_helper.php';

header('Content-Type: application/json');

// Ensure only Staff (Admin or Agent) can trigger AI generation
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'agent'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);

if (!$ticketId) {
    echo json_encode(['success' => false, 'error' => 'Invalid ticket ID.']);
    exit;
}

// Fetch Ticket
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
    exit;
}

// Fetch Replies
$stmtReplies = $pdo->prepare("SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$stmtReplies->execute([$ticketId]);
$replies = $stmtReplies->fetchAll();

$aiResponse = generate_ai_ticket_reply($pdo, $ticket['subject'], $ticket['message'], $replies);

if ($aiResponse) {
    echo json_encode(['success' => true, 'reply' => $aiResponse]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to generate AI response. Check settings or API keys.']);
}