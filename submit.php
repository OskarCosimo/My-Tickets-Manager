<?php
// submit.php
// Ticket submission page with Category selection, Pre-filled form support, and My-WYSIWYG editor integration
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/turnstile.php';
require_once __DIR__ . '/includes/mailer.php';

$allowGuests = get_setting($pdo, 'allow_guest_tickets', '1') === '1';
$turnstileEnabled = get_setting($pdo, 'turnstile_enabled', '0') === '1';
$error = '';
$success = '';

// Pre-fill parameters support
$preName     = trim($_REQUEST['name'] ?? $_REQUEST['guest_name'] ?? '');
$preEmail    = trim($_REQUEST['email'] ?? $_REQUEST['guest_email'] ?? '');
$preSubject  = trim($_REQUEST['subject'] ?? '');
$preCategory = (int)($_REQUEST['category'] ?? $_REQUEST['category_id'] ?? 0);
$preMessage  = trim($_REQUEST['message'] ?? $_REQUEST['msg'] ?? '');

if (isset($_SESSION['user_email'])) {
    $preEmail = $_SESSION['user_email'];
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    
    if (!verify_turnstile($pdo, $turnstileToken)) {
        $error = 'Captcha verification failed. Please try again.';
    } else {
        $subject    = trim($_POST['subject'] ?? '');
        $message    = trim($_POST['message'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $guestEmail = isset($_SESSION['user_id']) ? $_SESSION['user_email'] : trim($_POST['email'] ?? '');
        $guestName  = isset($_SESSION['user_id']) ? ($_SESSION['username'] ?? '') : trim($_POST['name'] ?? '');

        if (empty($subject) || empty($message)) {
            $error = 'Please fill in all required fields.';
        } elseif (!isset($_SESSION['user_id']) && (empty($guestEmail) || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL))) {
            $error = 'Please provide a valid email address.';
        } else {
            $trackingCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 3) . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 3) . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 3));
            $accessToken  = bin2hex(random_bytes(32));
            $userId       = $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO tickets (tracking_code, access_token, user_id, category_id, guest_email, guest_name, subject, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$trackingCode, $accessToken, $userId, $categoryId, $guestEmail, $guestName, $subject, $message])) {
                $ticketId = $pdo->lastInsertId();
                $recipient = $guestEmail;
                $trackingUrl = "https://" . $_SERVER['HTTP_HOST'] . "/track.php?code=" . $trackingCode . "&token=" . $accessToken . "&email=" . urlencode($recipient);
                
                $emailBody  = "<h3>Ticket Submitted Successfully</h3>";
                $emailBody .= "<p>Your ticket reference code is: <strong>{$trackingCode}</strong></p>";
                $emailBody .= "<p>You can track the progress of your ticket using the link below:</p>";
                $emailBody .= "<p><a href='{$trackingUrl}'>{$trackingUrl}</a></p>";

                send_ticket_email($pdo, $recipient, $guestName ?: 'Customer', "Ticket Received: {$trackingCode}", $emailBody);

                // Add to AI Queue if AI Auto-Responder is enabled
                if (get_setting($pdo, 'ai_enabled', '0') === '1' && get_setting($pdo, 'ai_auto_respond', '0') === '1') {
                    $stmtQueue = $pdo->prepare("INSERT INTO ai_queue (ticket_id) VALUES (?)");
                    $stmtQueue->execute([$ticketId]);

                    // Trigger non-blocking asynchronous queue processing worker
$workerUrl = "https://" . $_SERVER['HTTP_HOST'] . "/api/process_ai_queue.php";
$ch = curl_init($workerUrl);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'InternalWorker/1.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);
                }

                // Fetch category name for plugin hook
                $categoryName = 'General';
                if ($categoryId) {
                    $stmtCat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmtCat->execute([$categoryId]);
                    $categoryName = $stmtCat->fetchColumn() ?: 'General';
                }

                trigger_hook('on_ticket_created', [
                    'ticket' => [
                        'tracking_code' => $trackingCode,
                        'access_token'  => $accessToken,
                        'subject'       => $subject,
                        'guest_name'    => $guestName
                    ],
                    'category_name' => $categoryName
                ]);

                $success = "Ticket submitted successfully! Code: <strong>{$trackingCode}</strong>";
            } else {
                $error = 'Failed to submit the ticket. Please try again later.';
            }
        }
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
    <div class="container my-4" style="max-width: 750px;">
        <h2>Submit a Ticket</h2>
        <hr>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <form id="ticket_form" method="POST" action="submit.php">
            <input type="hidden" name="submit_ticket" value="1">

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Your Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($preName); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Your Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($preEmail); ?>" required>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-8">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($preSubject); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $preCategory === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea id="ticket_message" name="message" class="form-control" rows="6"><?php echo htmlspecialchars($preMessage); ?></textarea>
            </div>

            <?php if ($turnstileEnabled): ?>
                <div class="mb-3">
                    <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Submit Ticket</button>
        </form>
    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof MyWysiwyg !== 'undefined' && document.getElementById('ticket_message')) {
            new MyWysiwyg('#ticket_message', {
                lang: '<?php echo htmlspecialchars($currentLang ?? "en"); ?>'
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>