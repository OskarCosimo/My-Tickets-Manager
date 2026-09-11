<?php
// admin/tickets.php
// Admin, Agency & Agent tickets overview table with Modal Assignee & Follower Management
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

// Fetch agent's assigned agency_id if applicable
$userAgencyId = null;
if ($userRole === 'agent') {
    $stmtAgCheck = $pdo->prepare("SELECT agency_id FROM users WHERE id = ?");
    $stmtAgCheck->execute([$userId]);
    $userAgencyId = $stmtAgCheck->fetchColumn() ?: null;
}

// --- PROCESS ADD ASSIGNEE / FOLLOWER ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_follower') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $targetStaffId = (int)($_POST['staff_id'] ?? 0);

    if ($ticketId > 0 && $targetStaffId > 0) {
        $canManage = false;
        if ($userRole === 'admin') {
            $canManage = true;
        } elseif ($userRole === 'agency') {
            // Agency can only assign itself or its own agents
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND (agency_id = ? OR id = ?) AND is_banned = 0");
            $stmtCheck->execute([$targetStaffId, $userId, $userId]);
            if ($stmtCheck->fetch()) {
                $canManage = true;
            }
        } elseif ($userRole === 'agent' && $targetStaffId === $userId) {
            $canManage = true;
        }

        if ($canManage) {
            // Update assigned_to if main ticket is unassigned
            $stmtMain = $pdo->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
            $stmtMain->execute([$ticketId]);
            $currentAssigned = $stmtMain->fetchColumn();

            if (empty($currentAssigned)) {
                $stmtSet = $pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
                $stmtSet->execute([$targetStaffId, $ticketId]);
            }

            // Add to ticket_followers table
            $stmtFollow = $pdo->prepare("INSERT IGNORE INTO ticket_followers (ticket_id, user_id) VALUES (?, ?)");
            $stmtFollow->execute([$ticketId, $targetStaffId]);
            $message = "Staff member successfully added to ticket followers.";
        } else {
            $error = "Permission denied: You cannot assign this staff member.";
        }
    }
}

// --- PROCESS REMOVE ASSIGNEE / FOLLOWER ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_follower') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $targetStaffId = (int)($_POST['staff_id'] ?? 0);

    if ($ticketId > 0 && $targetStaffId > 0) {
        $canManage = false;
        if ($userRole === 'admin') {
            $canManage = true;
        } elseif ($userRole === 'agency') {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND (agency_id = ? OR id = ?)");
            $stmtCheck->execute([$targetStaffId, $userId, $userId]);
            if ($stmtCheck->fetch()) {
                $canManage = true;
            }
        } elseif ($userRole === 'agent' && $targetStaffId === $userId) {
            $canManage = true;
        }

        if ($canManage) {
            // Delete from ticket_followers
            $stmtDel = $pdo->prepare("DELETE FROM ticket_followers WHERE ticket_id = ? AND user_id = ?");
            $stmtDel->execute([$ticketId, $targetStaffId]);

            // If main assignee is removed, unassign or pick another follower
            $stmtMain = $pdo->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
            $stmtMain->execute([$ticketId]);
            if ((int)$stmtMain->fetchColumn() === $targetStaffId) {
                $stmtNext = $pdo->prepare("SELECT user_id FROM ticket_followers WHERE ticket_id = ? LIMIT 1");
                $stmtNext->execute([$ticketId]);
                $nextStaff = $stmtNext->fetchColumn() ?: null;

                $stmtUpdateMain = $pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
                $stmtUpdateMain->execute([$nextStaff, $ticketId]);
            }
            $message = "Staff member removed from ticket.";
        } else {
            $error = "Permission denied: You cannot remove this staff member.";
        }
    }
}

$searchQuery = trim($_GET['search'] ?? '');

// --- FETCH SELECTABLE STAFF USERS BASED ON ROLE SCOPE ---
$assignableStaff = [];
if ($userRole === 'admin') {
    $assignableStaff = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('admin', 'agency', 'agent') AND is_banned = 0 ORDER BY role ASC, username ASC")->fetchAll();
} elseif ($userRole === 'agency') {
    $stmtStaff = $pdo->prepare("SELECT id, username, role FROM users WHERE (agency_id = ? OR id = ?) AND is_banned = 0 AND role IN ('agency', 'agent') ORDER BY username ASC");
    $stmtStaff->execute([$userId, $userId]);
    $assignableStaff = $stmtStaff->fetchAll();
} elseif ($userRole === 'agent') {
    $stmtStaff = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ? AND is_banned = 0");
    $stmtStaff->execute([$userId]);
    $assignableStaff = $stmtStaff->fetchAll();
}

// --- FETCH TICKETS BASED ON ROLE SCOPE ---
if ($userRole === 'admin') {
    $stmt = $pdo->query("
        SELECT t.*, c.name AS category_name, u.username AS user_username
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC
    ");
} elseif ($userRole === 'agency') {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS category_name, u.username AS user_username
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN users a ON t.assigned_to = a.id 
        WHERE a.agency_id = ? OR u.agency_id = ? OR t.assigned_to = ? OR t.assigned_to IS NULL
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
} elseif ($userRole === 'agent') {
    if ($userAgencyId) {
        // Agent with Agency: see tickets assigned to self, assigned to parent agency, created by self, OR followed by self
        $stmt = $pdo->prepare("
            SELECT t.*, c.name AS category_name, u.username AS user_username
            FROM tickets t 
            LEFT JOIN categories c ON t.category_id = c.id 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.assigned_to = ? 
               OR t.assigned_to = ? 
               OR t.user_id = ? 
               OR t.id IN (SELECT ticket_id FROM ticket_followers WHERE user_id = ?)
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId, $userAgencyId, $userId, $userId]);
    } else {
        // Independent Agent (No Agency): see only tickets assigned to self, created by self, OR followed by self
        $stmt = $pdo->prepare("
            SELECT t.*, c.name AS category_name, u.username AS user_username
            FROM tickets t 
            LEFT JOIN categories c ON t.category_id = c.id 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE t.assigned_to = ? 
               OR t.user_id = ? 
               OR t.id IN (SELECT ticket_id FROM ticket_followers WHERE user_id = ?)
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
    }
}

$tickets = $stmt->fetchAll();

// Helper function to fetch all followers for a specific ticket inside modal
function get_ticket_followers(PDO $pdo, int $ticketId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.role, u.agency_id 
        FROM users u 
        LEFT JOIN ticket_followers tf ON tf.user_id = u.id 
        LEFT JOIN tickets t ON t.assigned_to = u.id 
        WHERE (tf.ticket_id = ? OR t.id = ?) AND u.is_banned = 0
        ORDER BY u.role ASC, u.username ASC
    ");
    $stmt->execute([$ticketId, $ticketId]);
    return $stmt->fetchAll();
}

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
                                <th>Created At</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php $followers = get_ticket_followers($pdo, $ticket['id']); ?>
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
                                        <small><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View & Reply Main Button -->
                                            <a href="/track.php?code=<?php echo urlencode($ticket['tracking_code']); ?>&token=<?php echo urlencode($ticket['access_token']); ?>" class="btn btn-outline-primary" title="View & Reply Ticket">
                                                <i class="fa-solid fa-eye me-1"></i> View
                                            </a>

                                            <!-- Assignees / Followers Modal Trigger Button -->
                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#assignModal<?php echo $ticket['id']; ?>" title="Manage Assignees & Followers">
                                                <i class="fa-solid fa-user-plus"></i>
                                            </button>
                                        </div>

                                        <!-- Modal for Managing Staff Assignees & Followers -->
                                        <div class="modal fade text-start" id="assignModal<?php echo $ticket['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-dark text-white">
                                                        <h5 class="modal-title">
                                                            <i class="fa-solid fa-users-gear me-2"></i> Manage Followers (#<?php echo htmlspecialchars($ticket['tracking_code']); ?>)
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <h6>Current Assigned / Following Staff:</h6>
                                                        <ul class="list-group mb-3">
                                                            <?php if (empty($followers)): ?>
                                                                <li class="list-group-item text-muted small">No staff assigned or following this ticket.</li>
                                                            <?php else: ?>
                                                                <?php foreach ($followers as $f): ?>
                                                                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                                        <div>
                                                                            <strong><?php echo htmlspecialchars($f['username']); ?></strong>
                                                                            <span class="badge bg-secondary ms-1"><?php echo strtoupper($f['role']); ?></span>
                                                                        </div>
                                                                        <?php 
                                                                            $canRemove = false;
                                                                            if ($userRole === 'admin') $canRemove = true;
                                                                            elseif ($userRole === 'agency' && ((int)$f['agency_id'] === $userId || (int)$f['id'] === $userId)) $canRemove = true;
                                                                            elseif ($userRole === 'agent' && (int)$f['id'] === $userId) $canRemove = true;
                                                                        ?>
                                                                        <?php if ($canRemove): ?>
                                                                            <form method="POST" action="tickets.php" class="m-0">
                                                                                <input type="hidden" name="action" value="remove_follower">
                                                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                                <input type="hidden" name="staff_id" value="<?php echo $f['id']; ?>">
                                                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Remove staff from ticket">
                                                                                    <i class="fa-solid fa-xmark"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </ul>

                                                        <hr>
                                                        <h6>Add Staff Member / Agency:</h6>
                                                        <form method="POST" action="tickets.php" class="d-flex gap-2">
                                                            <input type="hidden" name="action" value="add_follower">
                                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                            
                                                            <select name="staff_id" class="form-select form-select-sm" required>
                                                                <option value="">-- Select Staff Member --</option>
                                                                <?php foreach ($assignableStaff as $s): ?>
                                                                    <option value="<?php echo $s['id']; ?>">
                                                                        <?php echo htmlspecialchars($s['username']) . ' (' . strtoupper($s['role']) . ')'; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="fa-solid fa-plus me-1"></i> Add
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <div class="modal-footer py-2">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

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
            "order": [[ 5, "desc" ]],
            "pageLength": 25,
            "search": {
                "search": "<?php echo htmlspecialchars($searchQuery); ?>"
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>