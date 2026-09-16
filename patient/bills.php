<?php
/**
 * Patient - Invoices & Billing Ledger
 */
$pageTitle = "Invoices & Bills";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$user = currentUser()
$db = Database::getConnection();

// Fetch Patient ID
$stmtP = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmtP->execute([$user['id']]);
$patient = $stmtP->fetch();
$patientId = $patient['id'] ?? 0;

// Fetch Invoices
$stmtBills = $db->prepare("SELECT * FROM bills WHERE patient_id = ? ORDER BY created_at DESC");
$stmtBills->execute([$patientId]);
$bills = $stmtBills->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Invoices & Payment History</h3>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Subtotal</th>
                                <th>Tax / Discount</th>
                                <th>Total Bill</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($bills)): foreach ($bills as $b): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($b['invoice_number']) ?></td>
                                    <td><?= formatDate($b['created_at']) ?></td>
                                    <td><?= formatCurrency($b['subtotal']) ?></td>
                                    <td>+<?= formatCurrency($b['tax']) ?> / -<?= formatCurrency($b['discount']) ?></td>
                                    <td class="fw-bold"><?= formatCurrency($b['total']) ?></td>
                                    <td class="text-success fw-semibold"><?= formatCurrency($b['paid_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $b['status'] === 'Paid' ? 'success' : 'danger' ?>">
                                            <?= sanitize($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#billModal<?= $b['id'] ?>">
                                            <i class="bi bi-receipt me-1"></i> View Invoice
                                        </button>

                                        <!-- Invoice Modal -->
                                        <div class="modal fade" id="billModal<?= $b['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header border-0 bg-light">
                                                        <h5 class="modal-title fw-bold">Invoice <?= sanitize($b['invoice_number']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <ul class="list-group list-group-flush mb-3">
                                                            <?php
                                                            $stmtItems = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
                                                            $stmtItems->execute([$b['id']]);
                                                            $items = $stmtItems->fetchAll();
                                                            foreach ($items as $item):
                                                            ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                                    <div>
                                                                        <strong><?= sanitize($item['service_name']) ?></strong>
                                                                        <br><small class="text-muted">Qty: <?= $item['quantity'] ?> x <?= formatCurrency($item['unit_price']) ?></small>
                                                                    </div>
                                                                    <span class="fw-bold"><?= formatCurrency($item['total']) ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                        <div class="border-top pt-2">
                                                            <div class="d-flex justify-content-between"><span>Subtotal:</span><span><?= formatCurrency($b['subtotal']) ?></span></div>
                                                            <div class="d-flex justify-content-between text-success"><span>Discount:</span><span>-<?= formatCurrency($b['discount']) ?></span></div>
                                                            <div class="d-flex justify-content-between text-muted"><span>Tax:</span><span>+<?= formatCurrency($b['tax']) ?></span></div>
                                                            <hr>
                                                            <div class="d-flex justify-content-between fw-bold fs-5"><span>Total:</span><span><?= formatCurrency($b['total']) ?></span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No financial invoices found.</td>
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