<?php
/**
 * Billing Staff - Payment Ledger & Recording
 */
$pageTitle = "Payments";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('billing');

$user = currentUser();
$db = Database::getConnection();

$error = ''

// Record Payment Submission Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billId  = (int)($_POST['bill_id'] ?? 0);
    $amount  = (float)($_POST['amount'] ?? 0);
    $method  = sanitize($_POST['payment_method'] ?? 'Cash');
    $ref     = sanitize($_POST['transaction_reference'] ?? '');
    $token   = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification Token Failed.";
    } elseif (!$billId || $amount <= 0) {
        $error = "Select a valid invoice and enter a positive payment amount.";
    } else {
        try {
            $db->beginTransaction();

            $stmtBill = $db->prepare("SELECT patient_id, total, paid_amount, due_amount FROM bills WHERE id = ?");
            $stmtBill->execute([$billId]);
            $bill = $stmtBill->fetch();

            if ($bill) {
                $newPaid = $bill['paid_amount'] + $amount;
                $newDue  = max(0, $bill['total'] - $newPaid);
                $newStatus = ($newDue == 0) ? 'Paid' : 'Partially Paid';

                $stmtPay = $db->prepare("INSERT INTO payments (bill_id, patient_id, amount, payment_method, transaction_reference, status) VALUES (?, ?, ?, ?, ?, 'Success')");
                $stmtPay->execute([$billId, $bill['patient_id'], $amount, $method, $ref]);

                $stmtUpd = $db->prepare("UPDATE bills SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?");
                $stmtUpd->execute([$newPaid, $newDue, $newStatus, $billId]);

                $db->commit();
                logAudit($user['id'], 'Recorded Payment', 'Billing', $billId);
                setFlashMessage('success', 'Payment recorded successfully!');
                header('Location: payments.php');
                exit();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Payment record failure: " . $e->getMessage();
        }
    }
}

// Fetch Payment History Ledger
$payments = $db->query("
    SELECT py.*, b.invoice_number, u.name as patient_name
    FROM payments py
    JOIN bills b ON py.bill_id = b.id
    JOIN patients p ON py.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    ORDER BY py.payment_date DESC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="row g-4">
            <!-- Payment Form -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Record Payment</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                        <form action="payments.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Outstanding Invoice</label>
                                <select name="bill_id" class="form-select" required>
                                    <option value="">-- Unpaid / Partial Bills --</option>
                                    <?php
                                    $unpaid = $db->query("SELECT b.id, b.invoice_number, b.due_amount, u.name FROM bills b JOIN patients p ON b.patient_id = p.id JOIN users u ON p.user_id = u.id WHERE b.status IN ('Unpaid', 'Partially Paid')")->fetchAll();
                                    foreach ($unpaid as $u):
                                    ?>
                                        <option value="<?= $u['id'] ?>"><?= sanitize($u['invoice_number']) ?> - <?= sanitize($u['name']) ?> (Due: <?= formatCurrency($u['due_amount']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Amount Paid (NPR)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card / POS</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Online Payment">Online Mobile Wallet</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Transaction Reference #</label>
                                <input type="text" name="transaction_reference" class="form-control" placeholder="TXN-99001">
                            </div>

                            <button type="submit" class="btn btn-success w-100 fw-bold">Record Payment Ledger</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Payment Ledger List -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Payment Ledger Logs</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoice #</th>
                                        <th>Patient</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($payments)): foreach ($payments as $py): ?>
                                        <tr>
                                            <td><?= formatDate($py['payment_date']) ?></td>
                                            <td class="fw-bold text-primary"><?= sanitize($py['invoice_number']) ?></td>
                                            <td><?= sanitize($py['patient_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= sanitize($py['payment_method']) ?></span></td>
                                            <td class="fw-bold text-success"><?= formatCurrency($py['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No payments recorded.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>