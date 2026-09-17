<?php
ob_start();
$pageTitle = "Pharmacy Sales History";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'pharmacist']);

$db = Database::getConnection();

$sales = [];
try {
    $sales = $db->query("
        SELECT ps.*, 
               COALESCE(u.name, 'Walk-In Customer') as patient_name,
               COALESCE(pi.brand_name, pi.medicine_name, 'Medicine') as medicine_name
        FROM pharmacy_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN pharmacy_inventory pi ON ps.medicine_id = pi.id
        ORDER BY ps.created_at DESC
    ")->fetchAll();
} catch (\PDOException $e) {
    $sales = [];
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Pharmacy Sales History</h2>
                <p class="text-muted mb-0">Complete record of all dispensed medicines and transaction receipts.</p>
            </div>
            <a href="pharmacy-pos.php" class="btn btn-primary fw-bold rounded-pill px-4">
                <i class="bi bi-cart-plus me-1"></i> Open POS Checkout
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt #</th>
                                <th>Patient</th>
                                <th>Medicine</th>
                                <th>Qty</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sales)): foreach ($sales as $s): ?>
                                <tr>
                                    <td><code><?= sanitize($s['receipt_no']) ?></code></td>
                                    <td class="fw-bold text-dark"><?= sanitize($s['patient_name']) ?></td>
                                    <td><?= sanitize($s['medicine_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $s['quantity'] ?> Units</span></td>
                                    <td class="fw-bold text-success"><?= formatCurrency($s['total_amount']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= sanitize($s['payment_method']) ?></span></td>
                                    <td><small class="text-muted"><?= formatDate($s['created_at']) ?></small></td>
                                    <td class="text-end pe-4">
                                        <a href="pharmacy-receipt.php?receipt_no=<?= urlencode($s['receipt_no']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                            <i class="bi bi-receipt me-1"></i> View Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No pharmacy sales recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>