<?php
/**
 * Admin - Security & Operational Audit Trail
 */
$pageTitle = "System Audit Logs";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

$logs = $db->query("
    SELECT a.*, u.name as user_name, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC LIMIT 100
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-1">System Security & Audit Logs</h3>
        <p class="text-muted mb-4">Activity history and security logs.</p>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action Performed</th>
                                <th>Module</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): foreach ($logs as $log): ?>
                                <tr>
                                    <td><small class="text-muted"><?= formatDate($log['created_at']) ?> <?= formatTime($log['created_at']) ?></small></td>
                                    <td class="fw-bold"><?= sanitize($log['user_name'] ?: 'System / Guest') ?></td>
                                    <td><span class="badge bg-light text-dark border text-capitalize"><?= sanitize($log['role'] ?: 'N/A') ?></span></td>
                                    <td class="fw-semibold text-dark"><?= sanitize($log['action']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= sanitize($log['module']) ?></span></td>
                                    <td><code><?= sanitize($log['ip_address']) ?></code></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No security logs recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>