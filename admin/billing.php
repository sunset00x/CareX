<?php
/**
 * Admin / Billing - Unified Itemized Invoicing & Charity Waiver Console
 */
$pageTitle = "Financial Billing & Insurance Ledger";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'billing']);

$db = Database::getConnection();
$error = '';

// ACTION HANDLER: CREATE ITEMIZED INVOICE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'create_invoice') {
        $patId     = (int)($_POST['patient_id'] ?? 0);
        $categories = $_POST['category'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        $costs     = $_POST['unit_cost'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if ($patId > 0 && !empty($categories)) {
            try {
                $db->beginTransaction();

                $invNumber = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
                $subtotal = 0;

                // Calculate subtotal
                for ($i = 0; $i < count($categories); $i++) {
                    $subtotal += ((float)$costs[$i] * (int)$quantities[$i]);
                }

                $stmtInv = $db->prepare("
                    INSERT INTO invoices (invoice_number, patient_id, subtotal_amount, net_payable_amount, due_balance, status) 
                    VALUES (?, ?, ?, ?, ?, 'Unpaid')
                ");
                $stmtInv->execute([$invNumber, $patId, $subtotal, $subtotal, $subtotal]);
                $invId = $db->lastInsertId();

                // Insert itemized line items
                $stmtItem = $db->prepare("INSERT INTO invoice_items (invoice_id, service_category, item_description, unit_cost, quantity, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
                for ($i = 0; $i < count($categories); $i++) {
                    $c = sanitize($categories[$i]);
                    $d = sanitize($descriptions[$i]);
                    $u = (float)$costs[$i];
                    $q = (int)$quantities[$i];
                    $t = $u * $q;
                    $stmtItem->execute([$invId, $c, $d, $u, $q, $t]);
                }

                $db->commit();
                setFlashMessage('success', "Itemized Invoice {$invNumber} created successfully.");
                header('Location: billing.php');
                exit();
            } catch (\Exception $e) {
                $db->rollBack();
                $error = "Invoice Creation Error: " . $e->getMessage();
            }
        }
    }
    // ACTION HANDLER: APPLY CHARITY WAIVER
    elseif ($action === 'apply_waiver') {
        $invId   = (int)($_POST['invoice_id'] ?? 0);
        $waiver  = (float)($_POST['waiver_amount'] ?? 0);
        $reason  = sanitize($_POST['reason'] ?? '');

        if ($invId > 0 && $waiver > 0 && !empty($reason)) {
            try {
                $db->beginTransaction();

                $stmtWaiver = $db->prepare("INSERT INTO charity_waivers (invoice_id, waived_by_user_id, waiver_amount, reason) VALUES (?, ?, ?, ?)");
                $stmtWaiver->execute([$invId, $_SESSION['user_id'], $waiver, $reason]);

                $stmtUpdInv = $db->prepare("
                    UPDATE invoices 
                    SET discount_waiver_amount = discount_waiver_amount + ?, 
                        net_payable_amount = GREATEST(0, net_payable_amount - ?),
                        due_balance = GREATEST(0, due_balance - ?)
                    WHERE id = ?
                ");
                $stmtUpdInv->execute([$waiver, $waiver, $waiver, $invId]);

                $db->commit();
                setFlashMessage('success', "Charity Waiver of " . formatCurrency($waiver) . " applied ethically.");
                header('Location: billing.php');
                exit();
            } catch (\Exception $e) {
                $db->rollBack();
                $error = "Waiver Error: " . $e->getMessage();
            }
        }
    }
}

$patients = $db->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$invoices = $db->query("
    SELECT i.*, u.name as patient_name 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    JOIN users u ON p.user_id = u.id 
    ORDER BY i.created_at DESC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Financial Billing & Revenue Ledger</h2>
                <p class="text-muted mb-0">Itemized bill generation, charity waivers, and installment deposit tracking.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="bi bi-receipt me-1"></i> Create Itemized Invoice
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient Name</th>
                                <th>Subtotal</th>
                                <th>Waiver / Discount</th>
                                <th>Net Payable</th>
                                <th>Due Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices): foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><code><?= sanitize($inv['invoice_number']) ?></code></td>
                                    <td class="fw-bold text-dark"><?= sanitize($inv['patient_name']) ?></td>
                                    <td><?= formatCurrency($inv['subtotal_amount']) ?></td>
                                    <td class="text-danger fw-bold">- <?= formatCurrency($inv['discount_waiver_amount']) ?></td>
                                    <td class="fw-bold text-dark"><?= formatCurrency($inv['net_payable_amount']) ?></td>
                                    <td class="fw-bold text-danger"><?= formatCurrency($inv['due_balance']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $inv['status'] === 'Paid' ? 'success' : ($inv['status'] === 'Partially Paid' ? 'warning text-dark' : 'danger') ?>">
                                            <?= $inv['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger fw-bold rounded-pill me-1" 
                                                onclick="openWaiverModal(<?= $inv['id'] ?>, '<?= sanitize($inv['invoice_number']) ?>', <?= $inv['due_balance'] ?>)">
                                            <i class="bi bi-heart-fill me-1"></i> Apply Charity Waiver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No invoices generated yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Itemized Invoice -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt text-primary me-2"></i>New Itemized Invoice Builder</h5>
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
                            <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <h6 class="fw-bold text-primary mb-3">Itemized Line Items</h6>
                    <div id="lineItemsContainer">
                        <div class="row g-2 mb-2 line-item-row">
                            <div class="col-md-3">
                                <select name="category[]" class="form-select" required>
                                    <option value="OPD Consultation">OPD Consultation</option>
                                    <option value="IPD Daily Bed">IPD Daily Bed</option>
                                    <option value="Pharmacy POS">Pharmacy POS</option>
                                    <option value="Diagnostic Lab">Diagnostic Lab</option>
                                    <option value="Surgery/Procedure">Surgery/Procedure</option>
                                </select>
                            </div>
                            <div class="col-md-4"><input type="text" name="description[]" class="form-control" placeholder="Description / Service Name" required></div>
                            <div class="col-md-2"><input type="number" step="0.01" name="unit_cost[]" class="form-control" placeholder="Unit Cost" required></div>
                            <div class="col-md-2"><input type="number" name="quantity[]" class="form-control" value="1" min="1" required></div>
                        </div>
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

<!-- Modal: Charity Waiver -->
<div class="modal fade" id="waiverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-heart-fill me-2"></i>Apply Charity Waiver / Relief</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="apply_waiver">
                    <input type="hidden" name="invoice_id" id="waiver_invoice_id">

                    <p class="text-muted small mb-3">Applying a charitable relief waiver reduces the patient's payable debt balance ethically.</p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Invoice Number</label>
                        <input type="text" id="waiver_invoice_num" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Waiver / Discount Amount *</label>
                        <input type="number" step="0.01" name="waiver_amount" class="form-control" placeholder="0.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ethical Justification / Reason *</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Destitute patient, Social Health Charity Waiver Grant" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Authorize Waiver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openWaiverModal(id, num, due) {
    document.getElementById('waiver_invoice_id').value = id;
    document.getElementById('waiver_invoice_num').value = num;
    var waiverModal = new bootstrap.Modal(document.getElementById('waiverModal'));
    waiverModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>