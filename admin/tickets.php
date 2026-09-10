<?php
// admin/tickets.php
// Admin, Agency & Agent tickets overview table using DataTables with Dynamic Reassignment
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

$message = '';
$error   = '';

// --- PROCESS TICKET REASSIGNMENT REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_ticket') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $assignTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    if ($ticketId > 0) {
        if ($userRole === 'admin') {
            // Admin can reassign any ticket to any Agency or Agent
            $stmtAssign = $pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
            $stmtAssign->execute([$assignTo, $ticketId]);
            $message = "Ticket assignment updated successfully.";
        } elseif ($userRole === 'agency') {
            // Agency can assign unassigned tickets OR tickets assigned to itself/its agents
            $stmtCheck = $pdo->prepare("
                SELECT id FROM tickets 
                WHERE id = ? AND (assigned_to IS NULL OR assigned_to = ? OR assigned_to IN (SELECT id FROM users WHERE agency_id = ?))
            ");
            $stmtCheck->execute([$ticketId, $userId, $userId]);

            if ($stmtCheck->fetch()) {
                // Verify that the selected assignee is either the agency itself or one of its agents
                if ($assignTo !== null) {
                    $stmtAgentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND (agency_id = ? OR id = ?) AND role IN ('agent', 'agency')");
                    $stmtAgentCheck->execute([$assignTo, $userId, $userId]);
                    if (!$stmtAgentCheck->fetch()) {
                        $assignTo = null;
                    }
                }

                $stmtAssign = $pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
                $stmtAssign->execute([$assignTo, $ticketId]);
                $message = "Ticket assigned to agent successfully.";
            } else {
                $error = "You do not have permission to reassign this ticket.";
            }
        }
    }
}

$searchQuery = trim($_GET['search'] ?? '');

// --- FETCH SELECTABLE STAFF USERS FOR ASSIGNMENT DROPDOWN ---
$assignableStaff = [];
if ($userRole === 'admin') {
    // Admin can assign to any Agency or Agent
    $assignableStaff = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('agency', 'agent') AND is_banned = 0 ORDER BY role ASC, username ASC")->fetchAll();
} elseif ($userRole === 'agency') {
    // Agency can assign to itself or its assigned agents
    $stmtStaff = $pdo->prepare("SELECT id, username, role FROM users WHERE (agency_id = ? OR id = ?) AND is_banned = 0 AND role IN ('agency', 'agent') ORDER BY username ASC");
    $stmtStaff->execute([$userId, $userId]);
    $assignableStaff = $stmtStaff->fetchAll();
}

// --- FETCH TICKETS BASED ON ROLE SCOPE ---
if ($userRole === 'admin') {
    // Admin sees all system tickets
    $stmt = $pdo->query("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent, a.role AS assigned_role
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        ORDER BY t.created_at DESC
    ");
} elseif ($userRole === 'agency') {
    // Agency sees tickets assigned to its agents, submitted by its users, assigned to itself, OR completely unassigned
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent, a.role AS assigned_role
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE a.agency_id = ? OR u.agency_id = ? OR t.assigned_to = ? OR t.assigned_to IS NULL
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
} elseif ($userRole === 'agent') {
    // Agent sees tickets directly assigned to them OR assigned to their parent agency
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, u.username AS user_username, a.username AS assigned_agent, a.role AS assigned_role
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE t.assigned_to = ? OR t.assigned_to = (SELECT agency_id FROM users WHERE id = ?)
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
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

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
                                        <?php if (in_array($userRole, ['admin', 'agency'], true)): ?>
                                            <!-- Assignment Form for Admin and Agency -->
                                            <form method="POST" action="tickets.php" class="d-inline-flex align-items-center gap-1 m-0">
                                                <input type="hidden" name="action" value="assign_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                                                <select name="assigned_to" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                                    <option value="">-- Unassigned --</option>
                                                    <?php foreach ($assignableStaff as $staff): ?>
                                                        <option value="<?php echo (int)$staff['id']; ?>" <?php echo (int)$ticket['assigned_to'] === (int)$staff['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($staff['username']) . ' (' . strtoupper($staff['role']) . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                            <!-- Read-only view for Agents -->
                                            <?php if ($ticket['assigned_agent']): ?>
                                                <span class="badge bg-dark">
                                                    <i class="fa-solid fa-user-check me-1"></i> <?php echo htmlspecialchars($ticket['assigned_agent']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">Unassigned</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
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