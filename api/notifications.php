<?php
// api/notifications.php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

if ($action === 'get') {
    // Fetch unread count and latest 15 notifications
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userId]);
    $unreadCount = (int)$stmtUnread->fetchColumn();

    $stmtList = $pdo->prepare("
        SELECT n.*, t.tracking_code, t.access_token 
        FROM notifications n 
        LEFT JOIN tickets t ON n.ticket_id = t.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 15
    ");
    $stmtList->execute([$userId]);
    $notifications = $stmtList->fetchAll();

    echo json_encode([
        'success'      => true,
        'unread_count' => $unreadCount,
        'items'        => $notifications
    ]);
    exit;
}

if ($action === 'mark_read') {
    $stmtMark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmtMark->execute([$userId]);

    echo json_encode(['success' => true]);
    exit;
}