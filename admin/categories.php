<?php
// admin/categories.php
// Admin Category Management
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

$message = '';
$error = '';

// Handle Category Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $message = 'Category added successfully.';
    } else {
        $error = 'Category name is required.';
    }
}

// Handle Category Deletion
if (isset($_GET['delete'])) {
    $catId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    $message = 'Category deleted successfully.';
}

// Fetch all categories
$categories = $pdo->query("SELECT c.*, COUNT(t.id) as ticket_count FROM categories c LEFT JOIN tickets t ON c.id = t.category_id GROUP BY c.id ORDER BY c.id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <h2><i class="fa-solid fa-folder me-2"></i> Manage Ticket Categories</h2>
        <hr>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="row">
            <!-- Add Category Form -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">Add New Category</div>
                    <div class="card-body">
                        <form method="POST" action="categories.php">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i> Add Category</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">Existing Categories</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle m-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 70px;">ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Tickets Associated</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No categories found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary">#<?php echo (int)$cat['id']; ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                                <td><span class="badge bg-info text-dark"><?php echo (int)$cat['ticket_count']; ?></span></td>
                                                <td class="text-end">
                                                    <a href="categories.php?delete=<?php echo (int)$cat['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category?');">
                                                        <i class="fa-solid fa-trash"></i>
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
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
