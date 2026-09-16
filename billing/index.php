<?php
/**
 * Billing Dashboard Interface
 */
$pageTitle = "Billing & Revenue Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('billing');

$user = currentUser();
$db = Database::getConnection();

// Financial Summaries
$stmtRev = $db->query("SELECT SUM(paid_amount) FROM bills");
$totalRevenue = $stmtRev->fetchColumn() ?: 0.00;

$stmtDue = $db->query("SELECT SUM(due_amount) FROM bills WHERE status IN ('Unpaid', 'Partially Paid')");
$totalDue = $stmtDue->fetchColumn() ?: 0.00;

// Fetch Recent Invoices
$stmtInvoices = $db->query("
    SELECT b.*, u.name as patient_name, p.patient_id as patient_code
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    ORDER BY b.created_at DESC LIMIT 10
");
$invoices = $stmtInvoices->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Financial & Invoice Management</h2>
                <p class="text-muted">Manage hospital invoicing, services, and payments.</p>
            </div>
            <a href="invoices.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-circle me-2"></i>Create New Invoice</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-success-subtle text-success me-3"><i class="bi bi-currency-dollar"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= formatCurrency($totalRevenue) ?></h3>
                            <small class="text-muted fw-semibold">Total Revenue Collected</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-danger-subtle text-danger me-3"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= formatCurrency($totalDue) ?></h3>
                            <small class="text-muted fw-semibold">Total Outstanding Receivables</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Recent Patient Invoices</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient Name</th>
                                <th>Total Bill</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): foreach ($invoices as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['invoice_number']) ?></td>
                                    <td><?= sanitize($row['patient_name']) ?> <small class="text-muted">(<?= sanitize($row['patient_code']) ?>)</small></td>
                                    <td class="fw-bold"><?= formatCurrency($row['total']) ?></td>
                                    <td class="text-success"><?= formatCurrency($row['paid_amount']) ?></td>
                                    <td class="text-danger"><?= formatCurrency($row['due_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Paid' ? 'success' : ($row['status'] == 'Partially Paid' ? 'warning' : 'danger') ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="invoices.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No billing invoices recorded.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>