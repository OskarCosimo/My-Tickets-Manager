<?php
// admin/agencies.php
// Agency & Agent Management Panel with Cascade Banning
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

// --- ACTIONS: BAN / UNBAN / TOGGLE AGENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION 1: Admin Bans/Unbans Agency (Cascades to all assigned Agents)
    if ($_POST['action'] === 'toggle_agency_ban' && $userRole === 'admin') {
        $agencyIdToToggle = (int)($_POST['agency_id'] ?? 0);
        $newStatus        = (int)($_POST['banned_status'] ?? 0); // 1 = ban, 0 = active

        if ($agencyIdToToggle > 0) {
            // 1. Update Agency Status
            $stmtAgency = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND role = 'agency'");
            $stmtAgency->execute([$newStatus, $agencyIdToToggle]);

            // 2. Cascade status update to all Agents belonging to this Agency
            $stmtAgents = $pdo->prepare("UPDATE users SET is_banned = ? WHERE agency_id = ? AND role = 'agent'");
            $stmtAgents->execute([$newStatus, $agencyIdToToggle]);

            $success = $newStatus === 1 
                ? "Agency and all associated agents have been banned." 
                : "Agency and all associated agents have been unbanned.";
        }
    }

    // ACTION 2: Agency or Admin Bans/Unbans an individual Agent
    if ($_POST['action'] === 'toggle_agent_ban') {
        $agentIdToToggle = (int)($_POST['agent_id'] ?? 0);
        $newStatus       = (int)($_POST['banned_status'] ?? 0);

        if ($agentIdToToggle > 0) {
            if ($userRole === 'admin') {
                $stmtAgent = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND role = 'agent'");
                $stmtAgent->execute([$newStatus, $agentIdToToggle]);
            } else {
                // Agencies can only manage agents directly assigned to them
                $stmtAgent = $pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ? AND agency_id = ? AND role = 'agent'");
                $stmtAgent->execute([$newStatus, $agentIdToToggle, $userId]);
            }
            $success = "Agent status updated successfully.";
        }
    }
}

// Fetch data based on role scope
if ($userRole === 'admin') {
    // Admin fetches all Agencies with total Agents count
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
    // Agency only fetches its assigned Agents
    $stmtAgents = $pdo->prepare("SELECT * FROM users WHERE agency_id = ? AND role = 'agent' ORDER BY created_at DESC");
    $stmtAgents->execute([$userId]);
    $myAgents = $stmtAgents->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid my-4">
        <h2><i class="fa-solid fa-building-user me-2"></i> <?php echo $userRole === 'admin' ? 'Agencies & Agents Overview' : 'My Agents Management'; ?></h2>
        <hr>

        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
            <!-- ADMIN VIEW: List of all Agencies with expandable Agents details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">Registered Agencies</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Agency Name</th>
                                    <th>Email</th>
                                    <th>Agents Count</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($agencies)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No registered agencies found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($agencies as $agency): ?>
                                        <tr>
                                            <td><strong>#<?php echo $agency['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($agency['username']); ?></td>
                                            <td><?php echo htmlspecialchars($agency['email']); ?></td>
                                            <td><span class="badge bg-info text-dark"><?php echo $agency['agent_count']; ?> Agents</span></td>
                                            <td>
                                                <?php if (!empty($agency['is_banned'])): ?>
                                                    <span class="badge bg-danger">Banned (Cascade)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo date('Y-m-d H:i', strtotime($agency['created_at'])); ?></small></td>
                                            <td class="text-center">
                                                <!-- Cascade Ban / Unban Form -->
                                                <form method="POST" action="agencies.php" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_agency_ban">
                                                    <input type="hidden" name="agency_id" value="<?php echo $agency['id']; ?>">
                                                    <input type="hidden" name="banned_status" value="<?php echo !empty($agency['is_banned']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo !empty($agency['is_banned']) ? 'btn-success' : 'btn-danger'; ?>" onclick="return confirm('<?php echo !empty($agency['is_banned']) ? 'Unban this agency and restore its agents?' : 'Ban this agency? All assigned agents will be automatically banned!'; ?>');">
                                                        <i class="fa-solid <?php echo !empty($agency['is_banned']) ? 'fa-user-check' : 'fa-user-slash'; ?> me-1"></i>
                                                        <?php echo !empty($agency['is_banned']) ? 'Unban Agency' : 'Ban Agency'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- AGENCY VIEW: List and Ban control of their assigned agents -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">My Assigned Agents</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Agent Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myAgents)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">No agents registered under your agency yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($myAgents as $agent): ?>
                                        <tr>
                                            <td><strong>#<?php echo $agent['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($agent['username']); ?></td>
                                            <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                            <td>
                                                <?php if (!empty($agent['is_banned'])): ?>
                                                    <span class="badge bg-danger">Banned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo date('Y-m-d H:i', strtotime($agent['created_at'])); ?></small></td>
                                            <td class="text-center">
                                                <form method="POST" action="agencies.php" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_agent_ban">
                                                    <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                                    <input type="hidden" name="banned_status" value="<?php echo !empty($agent['is_banned']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo !empty($agent['is_banned']) ? 'btn-success' : 'btn-danger'; ?>">
                                                        <i class="fa-solid <?php echo !empty($agent['is_banned']) ? 'fa-user-check' : 'fa-user-slash'; ?> me-1"></i>
                                                        <?php echo !empty($agent['is_banned']) ? 'Unban Agent' : 'Ban Agent'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>