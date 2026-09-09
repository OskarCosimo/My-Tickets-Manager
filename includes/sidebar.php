<?php
// includes/sidebar.php
// Dynamic Sidebar template file with high-contrast labels and role-based sections
$userRole   = $_SESSION['user_role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$isStaff    = in_array($userRole, ['admin', 'agency', 'agent'], true);
$isAgency   = in_array($userRole, ['admin', 'agency'], true);
$isAdmin    = $userRole === 'admin';
?>
<aside id="sidebar-wrapper" class="bg-dark text-white border-end border-secondary">
    <div class="list-group list-group-flush py-3">
        
        <!-- SECTION 1: Public & General Users -->
        <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('general', 'General'); ?></div>
        
        <a href="/index.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('home', 'Home'); ?>">
            <i class="fa-solid fa-house me-2 text-primary"></i><span class="link-text"><?php echo __('home', 'Home'); ?></span>
        </a>
        <a href="/submit.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('submit_ticket', 'Submit Ticket'); ?>">
            <i class="fa-solid fa-plus-circle me-2 text-success"></i><span class="link-text"><?php echo __('submit_ticket', 'Submit Ticket'); ?></span>
        </a>
        <a href="/track.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('track_ticket', 'Track Ticket'); ?>">
            <i class="fa-solid fa-magnifying-glass me-2 text-info"></i><span class="link-text"><?php echo __('track_ticket', 'Track Ticket'); ?></span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="/profile.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('account_settings', 'Account Settings'); ?>">
                <i class="fa-solid fa-user-gear me-2 text-warning"></i><span class="link-text"><?php echo __('account_settings', 'Account Settings'); ?></span>
            </a>
        <?php endif; ?>

        <?php if ($isStaff): ?>
            <!-- SECTION 2: Staff Workspace (Admin, Agency, Agent) -->
            <hr class="border-secondary my-2">
            <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('staff_workspace', 'Staff Workspace'); ?></div>
            
            <a href="/admin/dashboard.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('dashboard', 'Dashboard'); ?>">
                <i class="fa-solid fa-chart-line me-2 text-primary"></i><span class="link-text"><?php echo __('dashboard', 'Dashboard'); ?></span>
            </a>
            <a href="/admin/tickets.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('tickets', 'Tickets'); ?>">
                <i class="fa-solid fa-ticket me-2 text-warning"></i><span class="link-text"><?php echo __('tickets', 'Tickets'); ?></span>
            </a>

            <?php if ($isAgency): ?>
                <!-- SECTION 3: Agency & Referral Management (Admin & Agency) -->
                <hr class="border-secondary my-2">
                <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('agency_management', 'Agency Management'); ?></div>
                
                <a href="/admin/users.php?filter=agents" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('my_agents', 'My Agents'); ?>">
                    <i class="fa-solid fa-user-tie me-2 text-info"></i><span class="link-text"><?php echo __('my_agents', 'My Agents'); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <!-- SECTION 4: System Administration (Admin Only) -->
                <hr class="border-secondary my-2">
                <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('system_administration', 'System Administration'); ?></div>
                
                <a href="/admin/users.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('all_users', 'All Users'); ?>">
                    <i class="fa-solid fa-users-gear me-2 text-danger"></i><span class="link-text"><?php echo __('all_users', 'All Users'); ?></span>
                </a>
                <a href="/admin/categories.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('categories', 'Categories'); ?>">
                    <i class="fa-solid fa-folder me-2 text-warning"></i><span class="link-text"><?php echo __('categories', 'Categories'); ?></span>
                </a>
                <a href="/admin/settings.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('settings', 'Settings'); ?>">
                    <i class="fa-solid fa-sliders me-2 text-secondary"></i><span class="link-text"><?php echo __('settings', 'Settings'); ?></span>
                </a>
                <a href="/admin/rate_limits.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('rate_limits', 'Rate Limits'); ?>">
                    <i class="fa-solid fa-gauge-high me-2 text-success"></i><span class="link-text"><?php echo __('rate_limits', 'Rate Limits'); ?></span>
                </a>
                <a href="/admin/translations.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('translations', 'Translations'); ?>">
                    <i class="fa-solid fa-language me-2 text-info"></i><span class="link-text"><?php echo __('translations', 'Translations'); ?></span>
                </a>
                <a href="/admin/update.php" class="list-group-item list-group-item-action bg-transparent text-light border-0" title="<?php echo __('system_updates', 'System Updates'); ?>">
                    <i class="fa-solid fa-arrows-rotate me-2 text-primary"></i><span class="link-text"><?php echo __('system_updates', 'System Updates'); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
</aside>