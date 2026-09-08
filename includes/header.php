<?php
// includes/header.php
// Header with responsive layout CSS, Turnstile JS API, and language selector integration
require_once __DIR__ . '/config.php';
$siteTitle = get_setting($pdo, 'site_title', 'My Tickets Manager');
$availableLangs = get_available_languages();
global $currentLang;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- Cloudflare Turnstile API Script -->
    <?php if (get_setting($pdo, 'turnstile_enabled', '0') === '1'): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>

    <style>
        /* Base Flexbox Layout */
        body { min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
        .wrapper { display: flex; flex: 1; align-items: stretch; width: 100%; }
        .main-content { flex: 1; padding: 20px; min-width: 0; }

        /* Dynamic Sidebar Styling */
        #sidebar-wrapper {
            width: 240px;
            transition: width 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
            flex-shrink: 0;
        }

        #sidebar-wrapper .list-group-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
        }

        #sidebar-wrapper .list-group-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Collapsed Sidebar State */
        body.sidebar-collapsed #sidebar-wrapper {
            width: 65px;
        }

        body.sidebar-collapsed #sidebar-wrapper .link-text,
        body.sidebar-collapsed #sidebar-wrapper .admin-header {
            display: none !important;
        }

        /* Responsive behavior: collapsed by default on small screens */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                width: 65px;
            }
            #sidebar-wrapper .link-text,
            #sidebar-wrapper .admin-header {
                display: none !important;
            }
            body.sidebar-expanded #sidebar-wrapper {
                width: 240px;
            }
            body.sidebar-expanded #sidebar-wrapper .link-text,
            body.sidebar-expanded #sidebar-wrapper .admin-header {
                display: inline-block !important;
            }
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark bg-dark sticky-top p-2 shadow">
        <div class="container-fluid">
            <!-- Sidebar Toggle Hamburger Button -->
            <button id="sidebarToggle" class="btn btn-dark text-white me-2 border-0" type="button">
                <i class="fa-solid fa-bars fs-5"></i>
            </button>

            <a class="navbar-brand me-0 px-2 fs-6" href="/"><?php echo htmlspecialchars($siteTitle); ?></a>
            
            <div class="d-flex align-items-center ms-auto gap-3">
                <!-- Language Selector Dropdown -->
                <form method="GET" class="m-0">
                    <select name="lang" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <?php foreach ($availableLangs as $langCode): ?>
                            <option value="<?php echo $langCode; ?>" <?php echo $currentLang === $langCode ? 'selected' : ''; ?>>
                                <?php echo strtoupper($langCode); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="navbar-nav flex-row">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a class="nav-link px-2 text-white" href="/logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i> <?php echo __('logout', 'Logout'); ?></a>
                    <?php else: ?>
                        <a class="nav-link px-2 text-white" href="/login.php"><i class="fa-solid fa-right-to-bracket me-1"></i> <?php echo __('login', 'Login'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <div class="wrapper">