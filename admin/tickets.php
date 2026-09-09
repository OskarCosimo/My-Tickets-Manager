<?php
// admin/tickets.php
// Admin, Agency & Agent tickets overview table using DataTables
session_start();
require_once __DIR__ . '/../includes/config.php';

// Ensure user is authorized as Admin, Agency, or Agent
$userRole = $_SESSION['user_role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;
$allowedRoles = ['admin', 'agency', 'agent'];

if (!in_array($userRole, $allowedRoles, true)) {
    header("Location: /login.php");
    exit;
}

$searchQuery = trim($_GET['search'] ?? '');

// Fetch tickets based on user role scope
if ($userRole === 'admin') {
    // Admin sees all system tickets
    $stmt = $pdo->query("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        ORDER BY t.created_at DESC
    ");
} elseif ($userRole === 'agency') {
    // Agency sees tickets assigned to its agents OR submitted by its registered users
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE a.agency_id = ? OR u.agency_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
} elseif ($userRole === 'agent') {
    // Agent sees tickets directly assigned to them
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$userId]);
}

$tickets = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- DataTables CSS Assets -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<main class="main-content">
    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fa-solid fa-list-check me-2"></i> Tickets Management</h2>
            <a href="/submit.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> New Ticket</a>
        </div>
        <hr>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="ticketsTable" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Customer / Email</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Created At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">#<?php echo htmlspecialchars($ticket['tracking_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($ticket['user_username']): ?>
                                            <div><i class="fa-solid fa-user me-1 text-primary"></i> <?php echo htmlspecialchars($ticket['user_username']); ?></div>
                                        <?php else: ?>
                                            <div><i class="fa-regular fa-user me-1 text-muted"></i> <?php echo htmlspecialchars($ticket['guest_name'] ?: 'Guest'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($ticket['guest_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
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
                                    <td>
                                        <?php echo $ticket['assigned_agent'] ? htmlspecialchars($ticket['assigned_agent']) : '<span class="text-muted small">Unassigned</span>'; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <!-- Deep-link directly to track.php -->
                                        <a href="/track.php?code=<?php echo urlencode($ticket['tracking_code']); ?>&token=<?php echo urlencode($ticket['access_token']); ?>" class="btn btn-sm btn-outline-primary" title="Manage Ticket">
                                            <i class="fa-solid fa-eye me-1"></i> View & Reply
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

<!-- DataTables JS Assets -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#ticketsTable').DataTable({
            "order": [[ 6, "desc" ]],
            "pageLength": 25,
            "search": {
                "search": "<?php echo htmlspecialchars($searchQuery); ?>"
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>