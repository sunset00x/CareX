<?php
/**
 * Admin - Financial Invoices Ledger
 */
$pageTitle = "Financial Billing";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

$totalRevenue = (float)($db->query("SELECT SUM(paid_amount) FROM bills")->fetchColumn() ?: 0.00);
$totalDue     = (float)($db->query("SELECT SUM(due_amount) FROM bills WHERE status IN ('Unpaid', 'Partially Paid')")->fetchColumn() ?: 0.00);

$invoices = $db->query("
    SELECT b.*, u.name as patient_name, p.patient_id as patient_code
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    ORDER BY b.created_at DESC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Financial & Invoice Management</h3>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white border">
                    <small class="text-muted fw-bold">TOTAL REVENUE COLLECTED</small>
                    <h3 class="fw-bold mb-0 text-success"><?= formatCurrency($totalRevenue) ?></h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white border">
                    <small class="text-muted fw-bold">OUTSTANDING DUE AMOUNT</small>
                    <h3 class="fw-bold mb-0 text-danger"><?= formatCurrency($totalDue) ?></h3>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Master Patient Invoices</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): foreach ($invoices as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['invoice_number']) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($row['patient_name']) ?></div>
                                        <small class="text-muted"><?= sanitize($row['patient_code']) ?></small>
                                    </td>
                                    <td class="fw-bold"><?= formatCurrency($row['total']) ?></td>
                                    <td class="text-success fw-semibold"><?= formatCurrency($row['paid_amount']) ?></td>
                                    <td class="text-danger fw-semibold"><?= formatCurrency($row['due_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Partially Paid' ? 'warning' : 'danger') ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No billing invoices found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>