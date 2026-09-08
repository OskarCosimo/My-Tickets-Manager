<?php
// Plugin Name: Discord Notifications
// File: plugins/discord_notifier/discord_plugin.php

// Hook to register configuration fields inside Admin Panel -> Settings
add_hook('admin_settings_form', function($args) {
    global $pdo;
    
    // Fetch stored setting or fallback to empty string if missing from DB
    $currentWebhook = get_setting($pdo, 'discord_webhook_url', '');
    ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white">
            <i class="fa-brands fa-discord me-2"></i> Discord Notifications Plugin
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-bold">Discord Webhook URL</label>
                <input type="url" name="settings[discord_webhook_url]" class="form-control" placeholder="https://discord.com/api/webhooks/..." value="<?php echo htmlspecialchars($currentWebhook); ?>">
                <div class="form-text">
                    Enter your Discord Webhook URL to receive instant notifications for new tickets and replies. Leave blank to disable.
                </div>
            </div>
        </div>
    </div>
    <?php
});

// Hook: Trigger notification on new ticket creation
add_hook('on_ticket_created', function($data) {
    global $pdo;
    
    $webhookUrl = get_setting($pdo, 'discord_webhook_url', '');
    if (empty($webhookUrl)) {
        return;
    }

    $ticket = $data['ticket'];
    $trackingUrl = "https://" . $_SERVER['HTTP_HOST'] . "/track.php?code=" . $ticket['tracking_code'] . "&token=" . $ticket['access_token'];

    $payload = json_encode([
        "username" => "Ticket Bot",
        "embeds" => [
            [
                "title" => "🎟️ New Ticket Created: #" . $ticket['tracking_code'],
                "url" => $trackingUrl,
                "color" => 3447003, // Blue
                "fields" => [
                    ["name" => "Subject", "value" => $ticket['subject'], "inline" => false],
                    ["name" => "Customer", "value" => $ticket['guest_name'] ?: ($data['username'] ?? 'Customer'), "inline" => true],
                    ["name" => "Category", "value" => $data['category_name'] ?? 'General', "inline" => true],
                    ["name" => "Direct Link", "value" => "[View Ticket](" . $trackingUrl . ")", "inline" => false]
                ],
                "timestamp" => date("c")
            ]
        ]
    ]);

    send_discord_webhook($webhookUrl, $payload);
});

// Hook: Trigger notification on ticket reply
add_hook('on_ticket_replied', function($data) {
    global $pdo;

    $webhookUrl = get_setting($pdo, 'discord_webhook_url', '');
    if (empty($webhookUrl)) {
        return;
    }

    $ticket = $data['ticket'];
    $replyMessage = $data['reply']['message'] ?? '';
    $senderName = $data['sender_name'] ?? 'Customer';
    $trackingUrl = "https://" . $_SERVER['HTTP_HOST'] . "/track.php?code=" . $ticket['tracking_code'] . "&token=" . $ticket['access_token'];

    $payload = json_encode([
        "username" => "Ticket Bot",
        "embeds" => [
            [
                "title" => "💬 New Reply on Ticket #" . $ticket['tracking_code'],
                "url" => $trackingUrl,
                "color" => 15105570, // Orange
                "fields" => [
                    ["name" => "Sender", "value" => $senderName, "inline" => true],
                    ["name" => "Message Preview", "value" => substr(strip_tags($replyMessage), 0, 200) . '...', "inline" => false],
                    ["name" => "Direct Link", "value" => "[View Ticket](" . $trackingUrl . ")", "inline" => false]
                ],
                "timestamp" => date("c")
            ]
        ]
    ]);

    send_discord_webhook($webhookUrl, $payload);
});

if (!function_exists('send_discord_webhook')) {
    function send_discord_webhook($url, $payload) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }
}