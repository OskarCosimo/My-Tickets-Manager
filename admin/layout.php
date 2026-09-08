<?php
// admin/layout.php
// Dynamic Admin Layout Header & Sidebar (Bootstrap 5)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - My Tickets Manager</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; flex-direction: column; }
        #wrapper { display: flex; flex: 1; }
        #sidebar-wrapper { min-width: 250px; max-width: 250px; background-color: #212529; transition: margin 0.25s ease-out; }
        #sidebar-wrapper .list-group-item { background: transparent; color: #adb5bd; border: none; }
        #sidebar-wrapper .list-group-item:hover, #sidebar-wrapper .list-group-item.active { background-color: #0d6efd; color: #fff; }
        #page-content-wrapper { flex: 1; padding: 20px; }
    </style>
</head>
<body>
    <!-- Top Header -->
    <header class="navbar navbar-dark bg-dark sticky-top p-2 shadow">
        <a class="navbar-brand me-0 px-3 fs-6" href="#">My Tickets Manager</a>
        <div class="navbar-nav flex-row">
            <span class="nav-item text-nowrap text-white me-3">Welcome, Admin</span>
            <a class="nav-link px-3" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </header>

    <div id="wrapper">
        <!-- Collapsible Sidebar -->
        <aside id="sidebar-wrapper">
            <div class="list-group list-group-flush py-3">
                <a href="/admin/dashboard.php" class="list-group-item list-group-item-action active">
                    <i class="fa-solid fa-chart-line me-2"></i> Dashboard & Stats
                </a>
                <a href="/admin/tickets.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-ticket me-2"></i> Manage Tickets
                </a>
                <a href="/admin/users.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-users me-2"></i> Users & Staff
                </a>
                <a href="/admin/settings.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-sliders me-2"></i> System Settings
                </a>
                <a href="/admin/translations.php" class="list-group-item list-group-item-action">
                    <i class="fa-solid fa-language me-2"></i> LibreTranslate
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main id="page-content-wrapper">
            <div class="container-fluid">