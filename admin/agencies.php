<?php
// admin/agencies.php
// Agency & Agent Management Panel with Agent Approval Workflow, Single Table View & Auto-Assign Controls
session_start();
require_once __DIR__ . '/../includes/config.php';

$userRole = $_SESSION['user_role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;

if (!in_array($userRole, ['admin', 'agency'], true)) {
    header("Location: /login.php");
    exit;
}

$success = '';
$error   = '';

// --- ACTIONS: APPROVE / BAN / UNBAN / AUTO-ASSIGN TOGGLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Agency or Admin Approves/Revokes Agent
    if ($_POST['action'] === 'toggle_agent_approval') {
        $agentIdToToggle = (int)($_POST['agent_id'] ?? 0);

        if ($agentIdToToggle > 0) {
            if ($userRole === 'admin') {
                $stmtApprove = $pdo->prepare("UPDATE users SET is_approved = CASE WHEN is_approved = 1 THEN 0 ELSE 1 END WHERE id = ? AND role = 'agent'");
                $stmtApprove->execute([$agentIdToToggle]);
            } else {
                $stmtApprove = $pdo->prepare("UPDATE users SET is_approved = CASE WHEN is_approved = 1 THEN 0 ELSE 1 END WHERE id = ? AND agency_id = ? AND role = 'agent'");
                $stmtApprove->execute([$agentIdToToggle, $userId]);
            }
            $success = "Agent approval status updated successfully.";
        }
    }

    // ACTION: Admin Bans/Unbans Agency (Cascades to all assigned Agents)
    if ($_POST['action'] === 'toggle_agency_ban' && $userRole === 'admin') {
        $agencyIdToToggle = (int)($_POST['agency_id'] ?? 0);
        $newStatus        = (int)($_POST['banned_status'] ?? 0);

        if ($agencyIdToToggle > 0) {
            $stmtAgency = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND role = 'agency'");
            $stmtAgency->execute([$newStatus, $agencyIdToToggle]);

            $stmtAgents = $pdo->prepare("UPDATE users SET is_banned = ? WHERE agency_id = ? AND role = 'agent'");
            $stmtAgents->execute([$newStatus, $agencyIdToToggle]);

            $success = $newStatus === 1 ? "Agency and associated agents banned." : "Agency and associated agents unbanned.";
        }
    }

    // ACTION: Agency or Admin Bans/Unbans an individual Agent
    if ($_POST['action'] === 'toggle_agent_ban') {
        $agentIdToToggle = (int)($_POST['agent_id'] ?? 0);
        $newStatus       = (int)($_POST['banned_status'] ?? 0);

        if ($agentIdToToggle > 0) {
            if ($userRole === 'admin') {
                $stmtAgent = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND role = 'agent'");
                $stmtAgent->execute([$newStatus, $agentIdToToggle]);
            } else {
                $stmtAgent = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND agency_id = ? AND role = 'agent'");
                $stmtAgent->execute([$newStatus, $agentIdToToggle, $userId]);
            }
            $success = "Agent status updated successfully.";
        }
    }

    // ACTION: Agency or Admin Toggles Auto-Assign setting for an Agent
    if ($_POST['action'] === 'toggle_agent_auto_assign') {
        $agentIdToToggle = (int)($_POST['agent_id'] ?? 0);
        $newAutoAssign   = (int)($_POST['auto_assign_status'] ?? 0);

        if ($agentIdToToggle > 0) {
            if ($userRole === 'admin') {
                $stmtAuto = $pdo->prepare("UPDATE users SET auto_assign_tickets = ? WHERE id = ? AND role = 'agent'");
                $stmtAuto->execute([$newAutoAssign, $agentIdToToggle]);
            } else {
                $stmtAuto = $pdo->prepare("UPDATE users SET auto_assign_tickets = ? WHERE id = ? AND agency_id = ? AND role = 'agent'");
                $stmtAuto->execute([$newAutoAssign, $agentIdToToggle, $userId]);
            }
            $success = "Agent auto-assign preference updated successfully.";
        }
    }
}

// Helper query function to fetch stats for a single agent
function get_agent_activity_data(PDO $pdo, int $agentId): array {
    $assignedTickets = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ?");
    $assignedTickets->execute([$agentId]);

    $totalReplies = $pdo->prepare("SELECT COUNT(*) FROM ticket_replies WHERE user_id = ?");
    $totalReplies->execute([$agentId]);

    $closedTickets = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status = 'closed'");
    $closedTickets->execute([$agentId]);

    $stmtRepliesLog = $pdo->prepare("
        SELECT r.created_at, r.message, t.tracking_code, t.subject 
        FROM ticket_replies r 
        JOIN tickets t ON r.ticket_id = t.id 
        WHERE r.user_id = ? 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $stmtRepliesLog->execute([$agentId]);

    return [
        'assigned' => (int)$assignedTickets->fetchColumn(),
        'replies'  => (int)$totalReplies->fetchColumn(),
        'closed'   => (int)$closedTickets->fetchColumn(),
        'logs'     => $stmtRepliesLog->fetchAll()
    ];
}

// Fetch data based on scope
if ($userRole === 'admin') {
    $stmtAgencies = $pdo->query("
        SELECT a.*, COUNT(u.id) AS agent_count 
        FROM users a 
        LEFT JOIN users u ON u.agency_id = a.id AND u.role = 'agent'
        WHERE a.role = 'agency'
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $agencies = $stmtAgencies->fetchAll();
} else {
    $stmtAgents = $pdo->prepare("SELECT u.*, COALESCE(u.is_approved, 1) AS is_approved FROM users u WHERE u.agency_id = ? AND u.role = 'agent' ORDER BY is_approved ASC, u.created_at DESC");
    $stmtAgents->execute([$userId]);
    $myAgents = $stmtAgents->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<main class="main-content">
    <div class="container-fluid my-4">
        <h2><i class="fa-solid fa-building-user me-2"></i> <?php echo $userRole === 'admin' ? 'Agencies Management' : 'My Agents Management'; ?></h2>
        <hr>

        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
            <!-- ADMIN VIEW: Single Table for Registered Agencies -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fa-solid fa-building me-2"></i> Registered Agencies
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="agenciesTable" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr>
                                    <th>Agency Name</th>
                                    <th>Email</th>
                                    <th>Approval Status</th>
                                    <th>Total Agents</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agencies as $agency): ?>
                                    <?php 
                                        $stmtAgencyAgents = $pdo->prepare("SELECT u.*, COALESCE(u.is_approved, 1) AS is_approved FROM users u WHERE u.agency_id = ? AND u.role = 'agent' ORDER BY is_approved ASC, u.created_at DESC");
                                        $stmtAgencyAgents->execute([$agency['id']]);
                                        $agencyAgents = $stmtAgencyAgents->fetchAll();
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($agency['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($agency['email']); ?></td>
                                        <td>
                                            <?php if (!empty($agency['is_approved'])): ?>
                                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Pending Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info text-dark"><?php echo $agency['agent_count']; ?> Agents</span></td>
                                        <td>
                                            <?php if (!empty($agency['is_banned'])): ?>
                                                <span class="badge bg-danger">Banned</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo date('Y-m-d H:i', strtotime($agency['created_at'])); ?></small></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#agentsModal<?php echo $agency['id']; ?>" title="View & Manage Agency Agents">
                                                    <i class="fa-solid fa-users me-1"></i> Agents (<?php echo $agency['agent_count']; ?>)
                                                </button>

                                                <form method="POST" action="agencies.php" class="d-inline m-0">
                                                    <input type="hidden" name="action" value="toggle_agency_ban">
                                                    <input type="hidden" name="agency_id" value="<?php echo $agency['id']; ?>">
                                                    <input type="hidden" name="banned_status" value="<?php echo !empty($agency['is_banned']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn <?php echo !empty($agency['is_banned']) ? 'btn-success' : 'btn-outline-danger'; ?>" onclick="return confirm('Change ban status for this agency?');">
                                                        <i class="fa-solid <?php echo !empty($agency['is_banned']) ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Agency Agents Modal for Admin -->
                                            <div class="modal fade text-start" id="agentsModal<?php echo $agency['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title"><i class="fa-solid fa-building me-2"></i> Agents in Agency: <?php echo htmlspecialchars($agency['username']); ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php if (empty($agencyAgents)): ?>
                                                                <div class="text-center text-muted py-4">No agents registered under this agency.</div>
                                                            <?php else: ?>
                                                                <div class="table-responsive">
                                                                    <table class="table table-striped table-hover align-middle">
                                                                        <thead class="table-light">
                                                                            <tr>
                                                                                <th>Agent Username</th>
                                                                                <th>Email</th>
                                                                                <th>Approval Status</th>
                                                                                <th>Auto-Assign</th>
                                                                                <th>Assigned Tickets</th>
                                                                                <th class="text-end">Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($agencyAgents as $agent): ?>
                                                                                <?php $stats = get_agent_activity_data($pdo, $agent['id']); ?>
                                                                                <tr>
                                                                                    <td><strong><?php echo htmlspecialchars($agent['username']); ?></strong></td>
                                                                                    <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                                                                    <td>
                                                                                        <?php if (!empty($agent['is_approved'])): ?>
                                                                                            <span class="badge bg-success">Approved</span>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                        <form method="POST" action="agencies.php" class="m-0">
                                                                                            <input type="hidden" name="action" value="toggle_agent_auto_assign">
                                                                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                                                            <input type="hidden" name="auto_assign_status" value="<?php echo !empty($agent['auto_assign_tickets']) ? '0' : '1'; ?>">
                                                                                            <div class="form-check form-switch m-0">
                                                                                                <input class="form-check-input" type="checkbox" onchange="this.form.submit()" <?php echo !empty($agent['auto_assign_tickets']) ? 'checked' : ''; ?>>
                                                                                            </div>
                                                                                        </form>
                                                                                    </td>
                                                                                    <td><span class="badge bg-primary"><?php echo $stats['assigned']; ?> Tickets</span></td>
                                                                                    <td class="text-end">
                                                                                        <form method="POST" action="agencies.php" class="d-inline m-0">
                                                                                            <input type="hidden" name="action" value="toggle_agent_approval">
                                                                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                                                            <button type="submit" class="btn btn-sm <?php echo empty($agent['is_approved']) ? 'btn-success' : 'btn-outline-warning'; ?>" title="<?php echo empty($agent['is_approved']) ? 'Approve Agent' : 'Revoke Approval'; ?>">
                                                                                                <i class="fa-solid <?php echo empty($agent['is_approved']) ? 'fa-user-check' : 'fa-user-clock'; ?>"></i>
                                                                                            </button>
                                                                                        </form>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            <?php endif; ?>
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

        <?php else: ?>

            <!-- AGENCY VIEW: Direct Table for Agency's Registered Agents -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fa-solid fa-user-gear me-2"></i> My Assigned Agents
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="myAgentsTable" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr>
                                    <th>Agent Username</th>
                                    <th>Email</th>
                                    <th>Approval Status</th>
                                    <th>Auto-Assign</th>
                                    <th>Joined Date</th>
                                    <th class="text-center">Actions & Statistics</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myAgents as $agent): ?>
                                    <?php $stats = get_agent_activity_data($pdo, $agent['id']); ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($agent['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                        <td>
                                            <?php if (!empty($agent['is_approved'])): ?>
                                                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Pending Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="agencies.php" class="m-0">
                                                <input type="hidden" name="action" value="toggle_agent_auto_assign">
                                                <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                <input type="hidden" name="auto_assign_status" value="<?php echo !empty($agent['auto_assign_tickets']) ? '0' : '1'; ?>">
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input" type="checkbox" onchange="this.form.submit()" <?php echo !empty($agent['auto_assign_tickets']) ? 'checked' : ''; ?>>
                                                </div>
                                            </form>
                                        </td>
                                        <td><small><?php echo date('Y-m-d H:i', strtotime($agent['created_at'])); ?></small></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Approve / Revoke Agent Button -->
                                                <form method="POST" action="agencies.php" class="d-inline m-0">
                                                    <input type="hidden" name="action" value="toggle_agent_approval">
                                                    <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                    <button type="submit" class="btn <?php echo empty($agent['is_approved']) ? 'btn-success' : 'btn-outline-warning'; ?>" title="<?php echo empty($agent['is_approved']) ? 'Approve Agent' : 'Revoke Approval'; ?>">
                                                        <i class="fa-solid <?php echo empty($agent['is_approved']) ? 'fa-user-check' : 'fa-user-clock'; ?>"></i>
                                                        <?php echo empty($agent['is_approved']) ? 'Approve' : 'Revoke'; ?>
                                                    </button>
                                                </form>

                                                <!-- Activity Stats Trigger Button -->
                                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#statsModal<?php echo $agent['id']; ?>">
                                                    <i class="fa-solid fa-chart-pie me-1"></i> Stats
                                                </button>

                                                <!-- Ban / Unban Toggle Button -->
                                                <form method="POST" action="agencies.php" class="d-inline m-0">
                                                    <input type="hidden" name="action" value="toggle_agent_ban">
                                                    <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                    <input type="hidden" name="banned_status" value="<?php echo !empty($agent['is_banned']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn <?php echo !empty($agent['is_banned']) ? 'btn-success' : 'btn-outline-danger'; ?>">
                                                        <i class="fa-solid <?php echo !empty($agent['is_banned']) ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Agent Activity Log Modal for Agency -->
                                            <div class="modal fade text-start" id="statsModal<?php echo $agent['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title">
                                                                <i class="fa-solid fa-chart-line me-2"></i> Agent Performance: <?php echo htmlspecialchars($agent['username']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-3 mb-4">
                                                                <div class="col-md-4">
                                                                    <div class="card bg-primary text-white text-center">
                                                                        <div class="card-body py-2">
                                                                            <h6 class="card-title mb-1">Assigned Tickets</h6>
                                                                            <h3 class="m-0"><?php echo $stats['assigned']; ?></h3>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="card bg-info text-dark text-center">
                                                                        <div class="card-body py-2">
                                                                            <h6 class="card-title mb-1">Total Replies Sent</h6>
                                                                            <h3 class="m-0"><?php echo $stats['replies']; ?></h3>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="card bg-success text-white text-center">
                                                                        <div class="card-body py-2">
                                                                            <h6 class="card-title mb-1">Closed / Resolved</h6>
                                                                            <h3 class="m-0"><?php echo $stats['closed']; ?></h3>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <h6 class="fw-bold mb-2"><i class="fa-solid fa-clock-rotate-left me-1"></i> Recent Activity Feed</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-striped border">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th>Timestamp</th>
                                                                            <th>Ticket Reference</th>
                                                                            <th>Reply Preview</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (empty($stats['logs'])): ?>
                                                                            <tr><td colspan="3" class="text-center text-muted py-2">No activity recorded for this agent yet.</td></tr>
                                                                        <?php else: ?>
                                                                            <?php foreach ($stats['logs'] as $log): ?>
                                                                                <tr>
                                                                                    <td><small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small></td>
                                                                                    <td><strong>#<?php echo htmlspecialchars($log['tracking_code']); ?></strong> - <?php echo htmlspecialchars($log['subject']); ?></td>
                                                                                    <td><small><?php echo htmlspecialchars(substr(strip_tags($log['message']), 0, 50)) . '...'; ?></small></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
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

        <?php endif; ?>
    </div>
</main>

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#agenciesTable').length) {
            $('#agenciesTable').DataTable({
                "order": [[ 2, "asc" ]],
                "pageLength": 10
            });
        }
        if ($('#myAgentsTable').length) {
            $('#myAgentsTable').DataTable({
                "order": [[ 2, "asc" ]],
                "pageLength": 10
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>