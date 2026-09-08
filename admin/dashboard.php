<?php
// admin/dashboard.php
// Admin main dashboard with statistics and assigned tickets summary
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

// Fetch general ticket statistics
$stats = [
    'total'    => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'open'     => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn(),
    'answered' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'answered'")->fetchColumn(),
    'closed'   => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'closed'")->fetchColumn()
];

// Fetch recent tickets list
$stmt = $pdo->prepare("
    SELECT t.*, u.username AS assigned_agent 
    FROM tickets t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    ORDER BY t.updated_at DESC LIMIT 10
");
$stmt->execute();
$recentTickets = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4">Admin Dashboard</h2>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="/admin/tickets.php" class="text-decoration-none">
                    <div class="card bg-primary text-white shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Total Tickets</h6>
                            <h2 class="m-0"><?php echo $stats['total']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="/admin/tickets.php?search=open" class="text-decoration-none">
                    <div class="card bg-warning text-dark shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Open Tickets</h6>
                            <h2 class="m-0"><?php echo $stats['open']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="/admin/tickets.php?search=answered" class="text-decoration-none">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Answered</h6>
                            <h2 class="m-0"><?php echo $stats['answered']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="/admin/tickets.php?search=closed" class="text-decoration-none">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Closed Tickets</h6>
                            <h2 class="m-0"><?php echo $stats['closed']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Tickets Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>Recent Tickets</span>
                <a href="/admin/tickets.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle m-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Last Update</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $ticket): ?>
                                <tr>
                                    <td><strong>#<?php echo htmlspecialchars($ticket['tracking_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo strtoupper($ticket['status']); ?></span></td>
                                    <td><?php echo $ticket['assigned_agent'] ? htmlspecialchars($ticket['assigned_agent']) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                    <td><?php echo $ticket['updated_at']; ?></td>
                                    <td class="text-center">
                                        <a href="/admin/tickets.php?search=<?php echo urlencode($ticket['tracking_code']); ?>" class="btn btn-sm btn-outline-secondary me-1" title="Find in List">
                                            <i class="fa-solid fa-list"></i>
                                        </a>
                                        <a href="/track.php?code=<?php echo urlencode($ticket['tracking_code']); ?>&token=<?php echo urlencode($ticket['access_token']); ?>" class="btn btn-sm btn-outline-primary" title="Open Ticket">
                                            <i class="fa-solid fa-eye"></i> Manage
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>