<?php
// index.php
// Public homepage
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <div class="p-5 mb-4 bg-light rounded-3 shadow-sm">
            <div class="container-fluid py-3">
                <h1 class="display-5 fw-bold">Support Center</h1>
                <p class="col-md-8 fs-4">Welcome to our ticketing support platform. You can submit a new ticket or check the status of an existing request.</p>
                <div class="d-flex gap-3">
                    <a href="/submit.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-ticket me-2"></i> Submit New Ticket</a>
                    <a href="/track.php" class="btn btn-outline-secondary btn-lg"><i class="fa-solid fa-magnifying-glass me-2"></i> Check Ticket Status</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>