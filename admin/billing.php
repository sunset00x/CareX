<?php
ob_start();
/**
 * Admin - Financial Billing Ledger & Advanced Ethical Workflows
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Financial Billing Ledger";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } else {

        // 1. GENERATE ITEMIZED INVOICE
        if ($action === 'create_invoice') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            $amount    = (float)($_POST['total_amount'] ?? 0.00);
            $invNum    = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);

            if ($patientId > 0 && $amount > 0) {
                try {
                    $stmt = $db->prepare("INSERT INTO invoices (invoice_number, patient_id, total_amount, status) VALUES (?, ?, ?, 'Unpaid')");
                    $stmt->execute([$invNum, $patientId, $amount]);

                    logAudit($_SESSION['user_id'], "Generated Invoice {$invNum}", 'Billing');
                    setFlashMessage('success', "Invoice '{$invNum}' generated successfully.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Billing Error: " . $e->getMessage();
                }
            } else {
                $error = "Please select a valid patient and specify the total invoice amount.";
            }
        }

        // 2. RECORD PARTIAL PAYMENT / DEPOSIT
        elseif ($action === 'record_deposit') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            $amount    = (float)($_POST['deposit_amount'] ?? 0.00);
            $method    = sanitize($_POST['payment_method'] ?? 'Cash');

            if ($patientId > 0 && $amount > 0) {
                try {
                    $stmt = $db->prepare("INSERT INTO patient_deposits (patient_id, amount, payment_method) VALUES (?, ?, ?)");
                    $stmt->execute([$patientId, $amount, $method]);

                    logAudit($_SESSION['user_id'], "Recorded Deposit of " . formatCurrency($amount) . " for Patient #{$patientId}", 'Billing');
                    setFlashMessage('success', "Patient deposit recorded successfully.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Deposit Error: " . $e->getMessage();
                }
            } else {
                $error = "Please specify a valid deposit amount.";
            }
        }

        // 3. APPLY CHARITY WAIVER / DISCOUNT
        elseif ($action === 'apply_charity') {
            $invoiceId   = (int)($_POST['invoice_id'] ?? 0);
            $waiverAmount = (float)($_POST['waiver_amount'] ?? 0.00);
            $reason       = sanitize($_POST['reason'] ?? '');

            if ($invoiceId > 0 && $waiverAmount > 0) {
                try {
                    $db->beginTransaction();

                    $stmtW = $db->prepare("INSERT INTO charity_waivers (invoice_id, waiver_amount, reason, approved_by) VALUES (?, ?, ?, ?)");
                    $stmtW->execute([$invoiceId, $waiverAmount, $reason, $_SESSION['user_id']]);

                    $stmtU = $db->prepare("UPDATE invoices SET total_amount = GREATEST(0, total_amount - ?) WHERE id = ?");
                    $stmtU->execute([$waiverAmount, $invoiceId]);

                    $db->commit();
                    logAudit($_SESSION['user_id'], "Applied Charity Waiver of " . formatCurrency($waiverAmount) . " to Invoice #{$invoiceId}", 'Billing');
                    setFlashMessage('success', "Charity waiver applied successfully.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $db->rollBack();
                    $error = "Waiver Error: " . $e->getMessage();
                }
            }
        }

        // 4. MARK INVOICE AS PAID
        elseif ($action === 'mark_paid') {
            $invId = (int)($_POST['invoice_id'] ?? 0);
            if ($invId > 0) {
                try {
                    $stmt = $db->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
                    $stmt->execute([$invId]);

                    logAudit($_SESSION['user_id'], "Marked Invoice #{$invId} as Paid", 'Billing');
                    setFlashMessage('success', "Invoice marked as Paid.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Status Update Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Invoices with Patient Details
try {
    $invoices = $db->query("
        SELECT i.*, u.name as patient_name, u.email as patient_email
        FROM invoices i
        JOIN patients p ON i.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        ORDER BY i.created_at DESC
    ")->fetchAll();
} catch (\PDOException $e) {
    $invoices = [];
}

// Fetch Active Patients for Select Dropdowns
$patients = $db->query("
    SELECT p.id, u.name 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY u.name ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Financial Billing Ledger</h2>
                <p class="text-muted mb-0">Manage itemized patient invoices, partial deposits, insurance claims, and charity waivers.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#depositModal">
                    <i class="bi bi-wallet2 me-1"></i> Deposit Ledger
                </button>
                <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#newInvModal">
                    <i class="bi bi-receipt me-1"></i> Generate Invoice
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient Name</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><code><?= sanitize($inv['invoice_number'] ?? 'INV-000') ?></code></td>
                                    <td class="fw-bold text-dark"><?= sanitize($inv['patient_name'] ?? 'Unknown Patient') ?></td>
                                    <td class="fw-bold text-success"><?= formatCurrency($inv['total_amount'] ?? 0.00) ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($inv['status'] ?? '') === 'Paid' ? 'success' : (($inv['status'] ?? '') === 'Partial' ? 'warning text-dark' : 'danger') ?>">
                                            <?= sanitize($inv['status'] ?? 'Unpaid') ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= formatDate($inv['created_at'] ?? 'now') ?></small></td>
                                    <td class="text-end pe-4">
                                        <?php if (($inv['status'] ?? '') !== 'Paid'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning fw-bold rounded-pill me-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#charityModal"
                                                    data-id="<?= $inv['id'] ?>"
                                                    data-num="<?= sanitize($inv['invoice_number'] ?? '') ?>">
                                                <i class="bi bi-heart me-1"></i> Charity Waiver
                                            </button>

                                            <form action="billing.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success fw-bold rounded-pill">
                                                    Mark Paid
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-check-circle-fill text-success me-1"></i> Settled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No financial invoices generated.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Generate New Invoice -->
<div class="modal fade" id="newInvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Generate Itemized Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_invoice">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Total Amount *</label>
                        <input type="number" step="0.01" name="total_amount" class="form-control" placeholder="1000.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Generate Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Deposit Ledger -->
<div class="modal fade" id="depositModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 text-primary me-2"></i>Record Patient Deposit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="record_deposit">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deposit Amount *</label>
                        <input type="number" step="0.01" name="deposit_amount" class="form-control" placeholder="500.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Card">Credit / Debit Card</option>
                            <option value="Online">Online Transfer / eSewa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Record Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Apply Charity Waiver -->
<div class="modal fade" id="charityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-heart me-2"></i>Apply Charity Waiver / Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="apply_charity">
                    <input type="hidden" name="invoice_id" id="charity_invoice_id">

                    <p class="text-muted mb-3">Target Invoice: <strong id="charity_invoice_num" class="text-dark"></strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waiver Amount *</label>
                        <input type="number" step="0.01" name="waiver_amount" class="form-control" placeholder="200.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Waiver / Discount</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Financial hardship, ethical waiver, hospital policy..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4">Apply Waiver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const charityModal = document.getElementById('charityModal');
    if (charityModal) {
        charityModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('charity_invoice_id').value = btn.getAttribute('data-id');
            document.getElementById('charity_invoice_num').textContent = btn.getAttribute('data-num');
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>