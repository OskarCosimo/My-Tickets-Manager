<?php
// includes/sidebar.php
// Dynamic Sidebar template file with collapsible link labels
$userRole   = $_SESSION['user_role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$isStaff    = in_array($userRole, ['admin', 'agent'], true);
$isAdmin    = $userRole === 'admin';
?>
<aside id="sidebar-wrapper" class="bg-dark text-white border-end">
    <div class="list-group list-group-flush py-3">
        <a href="/index.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('home', 'Home'); ?>">
            <i class="fa-solid fa-house me-2"></i><span class="link-text"><?php echo __('home', 'Home'); ?></span>
        </a>
        <a href="/submit.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('submit_ticket', 'Submit Ticket'); ?>">
            <i class="fa-solid fa-plus-circle me-2"></i><span class="link-text"><?php echo __('submit_ticket', 'Submit Ticket'); ?></span>
        </a>
        <a href="/track.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('track_ticket', 'Track Ticket'); ?>">
            <i class="fa-solid fa-magnifying-glass me-2"></i><span class="link-text"><?php echo __('track_ticket', 'Track Ticket'); ?></span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="/profile.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('account_settings', 'Account Settings'); ?>">
                <i class="fa-solid fa-user-gear me-2"></i><span class="link-text"><?php echo __('account_settings', 'Account Settings'); ?></span>
            </a>
        <?php endif; ?>

        <?php if ($isStaff): ?>
            <hr class="text-secondary my-2">
            <div class="px-3 text-uppercase text-muted small fw-bold mb-2 admin-header"><?php echo __('admin_panel', 'Admin Panel'); ?></div>
            <a href="/admin/dashboard.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('dashboard', 'Dashboard'); ?>">
                <i class="fa-solid fa-chart-line me-2"></i><span class="link-text"><?php echo __('dashboard', 'Dashboard'); ?></span>
            </a>
            <a href="/admin/tickets.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('tickets', 'Tickets'); ?>">
                <i class="fa-solid fa-ticket me-2"></i><span class="link-text"><?php echo __('tickets', 'Tickets'); ?></span>
            </a>
            <?php if ($isAdmin): ?>
                <a href="/admin/users.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('users', 'Users'); ?>">
                    <i class="fa-solid fa-users-gear me-2"></i><span class="link-text"><?php echo __('users', 'Users'); ?></span>
                </a>
                <a href="/admin/categories.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('categories', 'Categories'); ?>">
                    <i class="fa-solid fa-folder me-2"></i><span class="link-text"><?php echo __('categories', 'Categories'); ?></span>
                </a>
                <a href="/admin/settings.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('settings', 'Settings'); ?>">
                    <i class="fa-solid fa-sliders me-2"></i><span class="link-text"><?php echo __('settings', 'Settings'); ?></span>
                </a>
                <a href="/admin/translations.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('translations', 'Translations'); ?>">
                    <i class="fa-solid fa-language me-2"></i><span class="link-text"><?php echo __('translations', 'Translations'); ?></span>
                </a>
                <a href="/admin/update.php" class="list-group-item list-group-item-action bg-transparent text-white border-0" title="<?php echo __('system_updates', 'System Updates'); ?>">
                    <i class="fa-solid fa-arrows-rotate me-2"></i><span class="link-text"><?php echo __('system_updates', 'System Updates'); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</aside>