<?php
// admin/users.php
// User Management Page with DataTables, Agency Assignment, Auto-Assign Control, and Ban System
session_start();
require_once __DIR__ . '/../includes/config.php';

// Ensure user is authorized as Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /admin/dashboard.php");
    exit;
}

$message = '';
$error = '';

// Process Ban / Unban Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_ban') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId <= 0) {
        $error = "Invalid user selection.";
    } elseif ($targetUserId === (int)$_SESSION['user_id']) {
        $error = "You cannot ban your own active Admin account.";
    } else {
        try {
            // Toggle ban status
            $stmt = $pdo->prepare("UPDATE users SET is_banned = CASE WHEN is_banned = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User status updated successfully.";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Process Role, Agency & Auto-Assign Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newRole      = trim($_POST['role'] ?? '');
    // If new role is 'agency' or 'admin', agency_id must be NULL
    $agencyId     = (!empty($_POST['agency_id']) && !in_array($newRole, ['agency', 'admin'], true)) ? (int)$_POST['agency_id'] : null;
    $autoAssign   = isset($_POST['auto_assign_tickets']) ? 1 : 0;
    $allowedRoles = ['user', 'agent', 'agency', 'admin'];

    if ($targetUserId <= 0 || !in_array($newRole, $allowedRoles, true)) {
        $error = "Invalid user or role selection.";
    } elseif ($targetUserId === (int)$_SESSION['user_id'] && $newRole !== 'admin') {
        $error = "You cannot demote your own active Admin account.";
    } else {
        try {
            // Update role, agency assignment, and auto-assign setting
            $stmt = $pdo->prepare("UPDATE users SET role = ?, agency_id = ?, auto_assign_tickets = ? WHERE id = ?");
            $stmt->execute([$newRole, $agencyId, $autoAssign, $targetUserId]);
            $message = "User settings updated successfully.";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch all registered agencies for the dropdown list
$stmtAgencies = $pdo->query("SELECT id, username FROM users WHERE role = 'agency' ORDER BY username ASC");
$agenciesList = $stmtAgencies->fetchAll();

// Fetch all users with their associated agency name and ban status
$stmtUsers = $pdo->query("
    SELECT u.id, u.username, u.email, u.role, u.agency_id, u.auto_assign_tickets, u.is_banned, u.auth_provider, u.two_factor_enabled, u.created_at,
           ag.username AS agency_name
    FROM users u
    LEFT JOIN users ag ON u.agency_id = ag.id
    ORDER BY u.id DESC
");
$users = $stmtUsers->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<main class="main-content">
    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fa-solid fa-users-gear me-2"></i> User & Agency Management</h2>
        </div>
        <hr>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>Username / Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Assigned Agency</th>
                                <th>Auto-Assign</th>
                                <th>Registered At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['username'] ?? 'N/A'); ?></strong>
                                        <?php if (!empty($u['is_banned'])): ?>
                                            <span class="badge bg-danger ms-1">BANNED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php
                                        $badgeStyle = match($u['role']) {
                                            'admin'  => 'class="badge bg-danger"',
                                            'agency' => 'class="badge bg-primary text-white"',
                                            'agent'  => 'class="badge bg-warning text-dark"',
                                            default  => 'class="badge bg-info text-dark"'
                                        };
                                        ?>
                                        <span <?php echo $badgeStyle; ?>><?php echo strtoupper($u['role']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'agency'): ?>
                                            <span class="text-muted small"><em>N/A (Is Agency)</em></span>
                                        <?php elseif ($u['agency_name']): ?>
                                            <span class="badge bg-secondary"><i class="fa-solid fa-building me-1"></i> <?php echo htmlspecialchars($u['agency_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Independent / None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['auto_assign_tickets'])): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-robot me-1"></i> Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))); ?></small></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <!-- Role & Agency & Auto-Assign Form -->
                                            <form method="POST" action="users.php" class="d-inline-flex align-items-center gap-2 m-0">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                
                                                <!-- Select Role -->
                                                <select name="role" class="form-select form-select-sm role-select" style="width: auto;" data-user-id="<?php echo (int)$u['id']; ?>">
                                                    <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="agent" <?php echo $u['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                                                    <option value="agency" <?php echo $u['role'] === 'agency' ? 'selected' : ''; ?>>Agency</option>
                                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>

                                                <!-- Select Agency (Hidden/Disabled if Role is Agency or Admin) -->
                                                <select name="agency_id" id="agency_select_<?php echo (int)$u['id']; ?>" class="form-select form-select-sm agency-select" style="width: auto; <?php echo in_array($u['role'], ['agency', 'admin'], true) ? 'display: none;' : ''; ?>">
                                                    <option value="">-- No Agency --</option>
                                                    <?php foreach ($agenciesList as $ag): ?>
                                                        <!-- Prevent agency from selecting itself -->
                                                        <?php if ((int)$ag['id'] !== (int)$u['id']): ?>
                                                            <option value="<?php echo $ag['id']; ?>" <?php echo (int)$u['agency_id'] === (int)$ag['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($ag['username']); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>

                                                <!-- Toggle Auto-Assign -->
                                                <div class="form-check form-switch m-0" title="Toggle Auto-Assign">
                                                    <input class="form-check-input" type="checkbox" name="auto_assign_tickets" value="1" <?php echo !empty($u['auto_assign_tickets']) ? 'checked' : ''; ?>>
                                                </div>

                                                <button type="submit" class="btn btn-primary btn-sm" title="Save Changes" onclick="return confirm('Update settings for this user?');">
                                                    <i class="fa-solid fa-floppy-disk"></i>
                                                </button>
                                            </form>

                                            <!-- Ban / Unban Form Button -->
                                            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" action="users.php" class="m-0">
                                                    <input type="hidden" name="action" value="toggle_ban">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo !empty($u['is_banned']) ? 'btn-success' : 'btn-danger'; ?>" title="<?php echo !empty($u['is_banned']) ? 'Unban User' : 'Ban User'; ?>" onclick="return confirm('Are you sure you want to change the ban status for this user?');">
                                                        <i class="fa-solid <?php echo !empty($u['is_banned']) ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            "order": [[ 5, "desc" ]],
            "pageLength": 10,
            "language": {
                "search": "Filter users:"
            }
        });

        // Toggle visibility of the Agency select dropdown based on role selection
        $('.role-select').on('change', function() {
            const userId = $(this).data('user-id');
            const selectedRole = $(this).val();
            const agencySelect = $('#agency_select_' + userId);

            if (selectedRole === 'agency' || selectedRole === 'admin') {
                agencySelect.val('').hide();
            } else {
                agencySelect.show();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
