<?php
// admin/users.php
// User Management Page with DataTables Integration
session_start();
require_once __DIR__ . '/../includes/config.php';

// Ensure user is authorized as Admin (Agents/Users are strictly forbidden)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /admin/dashboard.php");
    exit;
}

$message = '';
$error = '';

// Process Role Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newRole      = trim($_POST['role'] ?? '');
    $allowedRoles = ['user', 'agent', 'admin'];

    if ($targetUserId <= 0 || !in_array($newRole, $allowedRoles, true)) {
        $error = "Invalid user or role selection.";
    } elseif ($targetUserId === (int)$_SESSION['user_id'] && $newRole !== 'admin') {
        $error = "You cannot demote your own active Admin account.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $targetUserId]);
            $message = "User role updated successfully.";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch all users
$stmtUsers = $pdo->query("SELECT id, username, email, role, auth_provider, two_factor_enabled, created_at FROM users ORDER BY id DESC");
$users = $stmtUsers->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<main class="main-content">
    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fa-solid fa-users-gear me-2"></i> User Management</h2>
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
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Auth Provider</th>
                                <th>2FA</th>
                                <th>Role</th>
                                <th>Registered At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo (int)$u['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($u['username'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars(strtoupper($u['auth_provider'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['two_factor_enabled']): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-shield-halved me-1"></i> Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match($u['role']) {
                                            'admin' => 'bg-danger',
                                            'agent' => 'bg-warning text-dark',
                                            default => 'bg-info text-dark'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($u['role']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                                    <td class="text-end">
                                        <!-- Role Update Form -->
                                        <form method="POST" action="users.php" class="d-inline-flex gap-2">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <select name="role" class="form-select form-select-sm" style="width: auto;">
                                                <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                <option value="agent" <?php echo $u['role'] === 'agent' ? 'selected' : ''; ?>>Agent (Agency)</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Change role for this user?');">
                                                <i class="fa-solid fa-floppy-disk"></i>
                                            </button>
                                        </form>
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
            "order": [[ 0, "desc" ]],
            "pageLength": 10,
            "language": {
                "search": "Filter users:"
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>