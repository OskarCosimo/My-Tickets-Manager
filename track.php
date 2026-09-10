<?php
// track.php
// Public tracking page with Email verification, Reply capability, Staff status actions, Follow/Unfollow, and Internal Notifications
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/turnstile.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/notifications_helper.php';

$code        = trim($_REQUEST['code'] ?? '');
$token       = trim($_REQUEST['token'] ?? '');
$searchEmail = trim($_REQUEST['email'] ?? ($_SESSION['user_email'] ?? ''));

$userRole = $_SESSION['user_role'] ?? '';
$isStaff  = in_array($userRole, ['admin', 'agency', 'agent'], true);

$ticket      = null;
$replies     = [];
$error       = '';
$success     = '';
$isFollowing = false;

if (!empty($code)) {
    if (!$isStaff && empty($searchEmail)) {
        $error = __('email_required', 'Please enter the email address associated with the ticket.');
    } else {
        if ($isStaff) {
            if (!empty($token)) {
                $stmt = $pdo->prepare("SELECT t.*, c.name as category_name FROM tickets t LEFT JOIN categories c ON t.category_id = c.id WHERE t.tracking_code = ? AND t.access_token = ?");
                $stmt->execute([$code, $token]);
            } else {
                $stmt = $pdo->prepare("SELECT t.*, c.name as category_name FROM tickets t LEFT JOIN categories c ON t.category_id = c.id WHERE t.tracking_code = ?");
                $stmt->execute([$code]);
            }
        } else {
            if (!empty($token)) {
                $stmt = $pdo->prepare("
                    SELECT t.*, c.name as category_name 
                    FROM tickets t 
                    LEFT JOIN categories c ON t.category_id = c.id 
                    LEFT JOIN users u ON t.user_id = u.id 
                    WHERE t.tracking_code = ? 
                      AND t.access_token = ? 
                      AND (t.guest_email = ? OR u.email = ?)
                ");
                $stmt->execute([$code, $token, $searchEmail, $searchEmail]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT t.*, c.name as category_name 
                    FROM tickets t 
                    LEFT JOIN categories c ON t.category_id = c.id 
                    LEFT JOIN users u ON t.user_id = u.id 
                    WHERE t.tracking_code = ? 
                      AND (t.guest_email = ? OR u.email = ?)
                ");
                $stmt->execute([$code, $searchEmail, $searchEmail]);
            }
        }

        $ticket = $stmt->fetch();

        if (!$ticket) {
            $error = __('ticket_not_found', 'No ticket found matching the specified criteria.');
        }
    }

    // Staff Toggle Follow/Unfollow Ticket Action
    if ($ticket && $isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_follow'])) {
        $staffUserId = (int)$_SESSION['user_id'];
        $stmtCheckFollow = $pdo->prepare("SELECT id FROM ticket_followers WHERE ticket_id = ? AND user_id = ?");
        $stmtCheckFollow->execute([$ticket['id'], $staffUserId]);
        
        if ($stmtCheckFollow->fetch()) {
            $stmtUnfollow = $pdo->prepare("DELETE FROM ticket_followers WHERE ticket_id = ? AND user_id = ?");
            $stmtUnfollow->execute([$ticket['id'], $staffUserId]);
            $success = "You have stopped following this ticket.";
            $isFollowing = false;
        } else {
            $stmtFollow = $pdo->prepare("INSERT INTO ticket_followers (ticket_id, user_id) VALUES (?, ?)");
            $stmtFollow->execute([$ticket['id'], $staffUserId]);
            $success = "You are now following this ticket. You will receive internal notifications for all updates.";
            $isFollowing = true;
        }
    }

    // Check if current logged staff user is following this ticket
    if ($ticket && $isStaff && !isset($_POST['toggle_follow'])) {
        $stmtIsFollow = $pdo->prepare("SELECT id FROM ticket_followers WHERE ticket_id = ? AND user_id = ?");
        $stmtIsFollow->execute([$ticket['id'], $_SESSION['user_id']]);
        $isFollowing = (bool)$stmtIsFollow->fetch();
    }

    // Staff Manual Status Update
    if ($ticket && $isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $allowedStatuses = ['open', 'answered', 'customer_reply', 'closed'];
        $newStatus = trim($_POST['status'] ?? '');

        if (in_array($newStatus, $allowedStatuses, true)) {
            $stmtUpdateStatus = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            if ($stmtUpdateStatus->execute([$newStatus, $ticket['id']])) {
                $ticket['status'] = $newStatus;
                $success = __('status_updated', 'Ticket status updated successfully!');
            } else {
                $error = __('status_update_failed', 'Failed to update ticket status.');
            }
        }
    }

    // Handle Reply Submission
    if ($ticket && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
        $rateError = '';
        if (!check_rate_limit($pdo, 'reply', $rateError)) {
            $error = $rateError;
        } else {
            $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
            
            if (!verify_turnstile($pdo, $turnstileToken)) {
                $error = __('captcha_failed', 'Captcha verification failed.');
            } else {
                $replyMessage = trim($_POST['reply_message'] ?? '');

                if (empty($replyMessage)) {
                    $error = __('message_required', 'Please enter a message before replying.');
                } else {
                    $userId = $_SESSION['user_id'] ?? null;
                    $senderEmail = $userId ? ($_SESSION['user_email'] ?? '') : $searchEmail;

                    $stmtReply = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
                    if ($stmtReply->execute([$ticket['id'], $userId, $replyMessage])) {
                        
                        $newStatus = $isStaff ? 'answered' : 'customer_reply';
                        $stmtUpdate = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
                        $stmtUpdate->execute([$newStatus, $ticket['id']]);

                        // Send email notifications to all participants EXCEPT the sender
                        notify_ticket_participants($pdo, $ticket, $replyMessage, $senderEmail);

                        // Dispatch internal platform notification to followers and assignees
                        $notifTitle = "New reply on ticket #" . $ticket['tracking_code'];
                        $notifMsg   = "Reply posted by " . ($userId ? ($_SESSION['username'] ?? 'Staff') : ($ticket['guest_name'] ?: 'Customer'));
                        notify_ticket_followers($pdo, $ticket, $notifTitle, $notifMsg, $userId);

                        // Trigger Plugin Hooks (e.g. Discord)
                        trigger_hook('on_ticket_replied', [
                            'ticket' => $ticket,
                            'reply' => ['message' => $replyMessage],
                            'sender_name' => $userId ? ($_SESSION['user_email'] ?? 'User') : ($ticket['guest_name'] ?: 'Customer')
                        ]);

                        $success = __('reply_sent', 'Your reply has been posted successfully!');
                        $ticket['status'] = $newStatus;
                    } else {
                        $error = __('reply_failed', 'Failed to post your reply. Please try again.');
                    }
                }
            }
        }
    }

    if ($ticket) {
        $stmtReplies = $pdo->prepare("SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
        $stmtReplies->execute([$ticket['id']]);
        $replies = $stmtReplies->fetchAll();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Local My-WYSIWYG Assets (CSS, i18n, JS) -->
<link rel="stylesheet" href="/assets/vendor/my-wysiwyg/my_wysiwyg.css">
<script src="/assets/vendor/my-wysiwyg/my_wysiwyg_i18n.js"></script>
<script src="/assets/vendor/my-wysiwyg/my_wysiwyg.js"></script>

<main class="main-content">
    <div class="container my-4" style="max-width: 850px;">
        <h2><?php echo __('track_your_ticket', 'Track Your Ticket'); ?></h2>
        <hr>

        <?php if ($isStaff): ?>
            <!-- Admin / Staff Information Notice -->
            <div class="alert alert-info py-2 small mb-3">
                <i class="fa-solid fa-circle-info me-1"></i> You are logged in as a staff member, so you don't need to enter the email. Normal users must provide both the tracking code and the original email address to view a ticket.
            </div>
        <?php endif; ?>

        <form method="GET" action="track.php" class="row g-3 mb-4">
            <?php if ($isStaff): ?>
                <div class="col-md-8">
                    <input type="text" name="code" class="form-control" placeholder="Enter Tracking Code (e.g. ABC-123-XYZ)" value="<?php echo htmlspecialchars($code); ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> <?php echo __('search', 'Search Ticket'); ?></button>
                </div>
            <?php else: ?>
                <div class="col-md-5">
                    <input type="text" name="code" class="form-control" placeholder="Tracking Code (e.g. ABC-123-XYZ)" value="<?php echo htmlspecialchars($code); ?>" required>
                </div>
                <div class="col-md-4">
                    <input type="email" name="email" class="form-control" placeholder="Your Ticket Email" value="<?php echo htmlspecialchars($searchEmail); ?>" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> <?php echo __('search', 'Search'); ?></button>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <?php if ($ticket): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0">[#<?php echo htmlspecialchars($ticket['tracking_code']); ?>] <?php echo htmlspecialchars($ticket['subject']); ?></h5>
                    <span class="badge bg-info text-dark"><?php echo strtoupper($ticket['status']); ?></span>
                </div>
                <div class="card-body">
                    <div class="ticket-description mb-3"><?php echo $ticket['message']; ?></div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">Submitted on: <?php echo $ticket['created_at']; ?> | Category: <strong><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></strong></small>

                        <div class="d-flex align-items-center gap-2">
                            <?php if ($isStaff): ?>
                                <!-- Follow / Unfollow Ticket Button for Staff -->
                                <form method="POST" action="track.php?code=<?php echo urlencode($code); ?>&token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($searchEmail); ?>" class="d-inline">
                                    <input type="hidden" name="toggle_follow" value="1">
                                    <button type="submit" class="btn btn-sm <?php echo $isFollowing ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                        <i class="fa-solid <?php echo $isFollowing ? 'fa-star' : 'fa-star-half-stroke'; ?> me-1"></i>
                                        <?php echo $isFollowing ? 'Following Ticket' : 'Follow Ticket'; ?>
                                    </button>
                                </form>

                                <!-- Staff Quick Status Change Control -->
                                <form method="POST" action="track.php?code=<?php echo urlencode($code); ?>&token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($searchEmail); ?>" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="form-select form-select-sm" style="width: auto;">
                                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="answered" <?php echo $ticket['status'] === 'answered' ? 'selected' : ''; ?>>Answered</option>
                                        <option value="customer_reply" <?php echo $ticket['status'] === 'customer_reply' ? 'selected' : ''; ?>>Customer Reply</option>
                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Update Status</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <h4 class="mb-3"><?php echo __('replies', 'Replies'); ?></h4>
            <?php foreach ($replies as $reply): ?>
                <div class="card mb-3 <?php echo $reply['user_id'] ? 'border-primary' : ''; ?>">
                    <div class="card-header py-1 bg-light d-flex justify-content-between">
                        <strong><?php echo $reply['username'] ? htmlspecialchars($reply['username']) . ' (Staff)' : htmlspecialchars($ticket['guest_name'] ?: 'Customer'); ?></strong>
                        <small class="text-muted"><?php echo $reply['created_at']; ?></small>
                    </div>
                    <div class="card-body">
                        <div><?php echo $reply['message']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($ticket['status'] !== 'closed'): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-secondary text-white">Post a Reply</div>
                    <div class="card-body">
                        <form id="reply_form" method="POST" action="track.php?code=<?php echo urlencode($code); ?>&token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($searchEmail); ?>">
                            <input type="hidden" name="submit_reply" value="1">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($searchEmail); ?>">

                            <?php if ($isStaff && get_setting($pdo, 'ai_enabled', '0') === '1'): ?>
                                <div class="mb-3">
                                    <button type="button" id="btn_generate_ai" class="btn btn-sm btn-outline-dark">
                                        <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Generate AI Suggestion
                                    </button>
                                    <span id="ai_spinner" class="spinner-border spinner-border-sm text-primary d-none ms-2" role="status"></span>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Your Message</label>
                                <textarea id="reply_message" name="reply_message" class="form-control" rows="5"></textarea>
                            </div>

                            <?php if (get_setting($pdo, 'turnstile_enabled', '0') === '1'): ?>
                                <div class="mb-3">
                                    <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-reply me-1"></i> Send Reply</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-4">This ticket is closed. You cannot post further replies.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let editorInstance = null;

        if (typeof MyWysiwyg !== 'undefined' && document.getElementById('reply_message')) {
            editorInstance = new MyWysiwyg('#reply_message', {
                lang: '<?php echo htmlspecialchars($currentLang ?? "en"); ?>',
                maxChars: 5000
            });
        }

        const btnAI = document.getElementById('btn_generate_ai');
        const spinner = document.getElementById('ai_spinner');

        if (btnAI) {
            btnAI.addEventListener('click', function() {
                btnAI.disabled = true;
                if (spinner) spinner.classList.remove('d-none');

                const formData = new FormData();
                formData.append('ticket_id', '<?php echo $ticket['id'] ?? 0; ?>');

                fetch('/api/generate_ai_reply.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    btnAI.disabled = false;
                    if (spinner) spinner.classList.add('d-none');

                    if (data.success && data.reply) {
                        if (editorInstance && typeof editorInstance.setHtml === 'function') {
                            editorInstance.setHtml(data.reply);
                        } else {
                            document.getElementById('reply_message').value = data.reply;
                        }
                    } else {
                        alert('AI Error: ' + (data.error || 'Failed to generate response.'));
                    }
                })
                .catch(err => {
                    btnAI.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                    alert('Server connection error while calling AI.');
                });
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>