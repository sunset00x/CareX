<?php
/**
 * Admin - User Accounts & Role Management CRUD
 */
$pageTitle = "Users & Roles";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// Toggle User Status Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $userId    = (int)($_POST['user_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? 'active');
    $token     = $_POST['csrf_token'] ?? '';

    if (verifyCSRFToken($token) && $userId > 0) {
        $stmtStatus = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmtStatus->execute([$newStatus, $userId]);
        logAudit($_SESSION['user_id'], "Changed User #{$userId} Status to {$newStatus}", 'Users', $userId);
        setFlashMessage('success', 'User status updated successfully.');
        header('Location: users.php');
        exit();
    }
}

// Fetch Users List
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <h3 class="fw-bold mb-4">System User Directory & Access Roles</h3>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="fw-bold"><?= sanitize($u['name']) ?></td>
                                    <td><?= sanitize($u['email']) ?></td>
                                    <td><span class="badge bg-primary text-capitalize"><?= sanitize($u['role']) ?></span></td>
                                    <td><?= sanitize($u['phone'] ?: 'N/A') ?></td>
                                    <td><span class="badge bg-<?= $u['status'] == 'active' ? 'success' : 'danger' ?>"><?= ucfirst(sanitize($u['status'])) ?></span></td>
                                    <td>
                                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                            <form action="users.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <button type="submit" name="status" value="suspended" class="btn btn-sm btn-outline-danger">Suspend</button>
                                                <?php else: ?>
                                                    <button type="submit" name="status" value="active" class="btn btn-sm btn-outline-success">Activate</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Current Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>