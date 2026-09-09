<?php
// includes/header.php
// Header with Bootstrap 5.3 Theme Switcher, responsive CSS, Turnstile JS API, and language selector
require_once __DIR__ . '/config.php';
$siteTitle = get_setting($pdo, 'site_title', 'My Tickets Manager');
$availableLangs = get_available_languages();
global $currentLang;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLang); ?>" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    
    <!-- Early theme initialization to avoid page flash -->
    <script>
        (function() {
            const storedTheme = localStorage.getItem('theme') || 'auto';
            if (storedTheme === 'auto') {
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-bs-theme', systemDark ? 'dark' : 'light');
            } else {
                document.documentElement.setAttribute('data-bs-theme', storedTheme);
            }
        })();
    </script>

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

        /* Dynamic Sidebar Styling with Enhanced Contrast */
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
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        #sidebar-wrapper .list-group-item:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }

        #sidebar-wrapper .list-group-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        #sidebar-wrapper .sidebar-header {
            color: #0dcaf0 !important; /* Cyan highlight for high readability */
            letter-spacing: 0.5px;
        }

        /* Collapsed Sidebar State */
        body.sidebar-collapsed #sidebar-wrapper {
            width: 65px;
        }

        body.sidebar-collapsed #sidebar-wrapper .link-text,
        body.sidebar-collapsed #sidebar-wrapper .sidebar-header {
            display: none !important;
        }

        /* Responsive behavior: collapsed by default on small screens */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                width: 65px;
            }
            #sidebar-wrapper .link-text,
            #sidebar-wrapper .sidebar-header {
                display: none !important;
            }
            body.sidebar-expanded #sidebar-wrapper {
                width: 240px;
            }
            body.sidebar-expanded #sidebar-wrapper .link-text,
            body.sidebar-expanded #sidebar-wrapper .sidebar-header {
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
                
                <!-- Bootstrap 5 Theme Switcher Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-dark btn-sm dropdown-toggle border-secondary" type="button" id="themeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-half-stroke me-1" id="themeIcon"></i>
                        <span id="themeLabel">Auto</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="themeDropdown">
                        <li>
                            <button class="dropdown-item d-flex align-items-center" type="button" data-bs-theme-value="light">
                                <i class="fa-solid fa-sun me-2 text-warning"></i> Light
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center" type="button" data-bs-theme-value="dark">
                                <i class="fa-solid fa-moon me-2 text-primary"></i> Dark
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center" type="button" data-bs-theme-value="auto">
                                <i class="fa-solid fa-circle-half-stroke me-2 text-secondary"></i> System Auto
                            </button>
                        </li>
                    </ul>
                </div>

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