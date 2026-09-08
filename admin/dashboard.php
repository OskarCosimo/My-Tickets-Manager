<?php
// admin/dashboard.php
// Admin & Agent Dashboard with statistics and assigned tickets summary
require_once __DIR__ . '/../includes/config.php';

// Ensure user is authorized as Admin or Agent (Matches tickets.php pattern)
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'agent'], true)) {
    header("Location: /login.php");
    exit;
}

// Default fallback statistics
$stats = [
    'total'    => 0,
    'open'     => 0,
    'answered' => 0,
    'closed'   => 0
];
$recentTickets = [];

try {
    // Fetch ticket statistics safely
    $stats['total']    = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $stats['open']     = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
    $stats['answered'] = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'answered'")->fetchColumn();
    $stats['closed']   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'closed'")->fetchColumn();

    // Fetch recent tickets list
    $stmt = $pdo->prepare("
        SELECT t.*, u.username AS assigned_agent 
        FROM tickets t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        ORDER BY t.updated_at DESC LIMIT 10
    ");
    $stmt->execute();
    $recentTickets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard Query Error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid my-4">
        <h2 class="mb-4"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</h2>
        <hr>

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
                <span><i class="fa-solid fa-clock-rotate-left me-2"></i> Recent Tickets</span>
                <a href="/admin/tickets.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle m-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Last Update</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTickets)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No recent tickets found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTickets as $ticket): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($ticket['tracking_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                        <td>
                                            <?php
                                                $statusClass = 'bg-secondary';
                                                if ($ticket['status'] === 'open') $statusClass = 'bg-warning text-dark';
                                                elseif ($ticket['status'] === 'answered') $statusClass = 'bg-info text-dark';
                                                elseif ($ticket['status'] === 'customer_reply') $statusClass = 'bg-primary';
                                                elseif ($ticket['status'] === 'closed') $statusClass = 'bg-success';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo strtoupper(str_replace('_', ' ', $ticket['status'])); ?></span>
                                        </td>
                                        <td><?php echo $ticket['assigned_agent'] ? htmlspecialchars($ticket['assigned_agent']) : '<span class="text-muted small">Unassigned</span>'; ?></td>
                                        <td><small><?php echo date('Y-m-d H:i', strtotime($ticket['updated_at'])); ?></small></td>
                                        <td class="text-center">
                                            <a href="/track.php?code=<?php echo urlencode($ticket['tracking_code']); ?>&token=<?php echo urlencode($ticket['access_token']); ?>" class="btn btn-sm btn-outline-primary" title="View Ticket">
                                                <i class="fa-solid fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
