<?php
/**
 * Advanced Dynamic Financial Billing & Invoice Engine
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Financial Billing";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// CSV EXPORT HANDLER
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExp = $db->query("
        SELECT b.invoice_number, u.name as patient_name, b.subtotal, b.tax, b.discount, b.total, b.paid_amount, b.due_amount, b.status, b.created_at
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        ORDER BY b.created_at DESC
    ");
    $exportData = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=careplus_invoices_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice Number', 'Patient Name', 'Subtotal', 'Tax', 'Discount', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status', 'Generated Date']);
    foreach ($exportData as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// ==========================================
// FORM ACTION HANDLERS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error. Operation cancelled.";
    } else {
        // 1. CREATE NEW DYNAMIC INVOICE
        if ($action === 'create_invoice') {
            $patientId  = (int)($_POST['patient_id'] ?? 0);
            $subtotal   = (float)($_POST['subtotal'] ?? 0);
            $tax        = (float)($_POST['tax'] ?? 0);
            $discount   = (float)($_POST['discount'] ?? 0);
            $paidAmount = (float)($_POST['paid_amount'] ?? 0);
            $notes      = sanitize($_POST['notes'] ?? '');

            $total = max(0, ($subtotal + $tax) - $discount);
            $due   = max(0, $total - $paidAmount);

            $status = 'Unpaid';
            if ($paidAmount >= $total && $total > 0) {
                $status = 'Paid';
            } elseif ($paidAmount > 0 && $paidAmount < $total) {
                $status = 'Partially Paid';
            }

            if ($patientId > 0 && $total >= 0) {
                try {
                    $invoiceNum = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
                    $stmtIns = $db->prepare("
                        INSERT INTO bills (patient_id, invoice_number, subtotal, tax, discount, total, paid_amount, due_amount, status, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$patientId, $invoiceNum, $subtotal, $tax, $discount, $total, $paidAmount, $due, $status, $notes]);
                    $billId = $db->lastInsertId();

                    logAudit($_SESSION['user_id'], "Generated Invoice #{$invoiceNum} for Patient #{$patientId}", 'Billing', $billId);
                    setFlashMessage('success', "Invoice {$invoiceNum} created successfully.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Invoice creation error: " . $e->getMessage();
                }
            } else {
                $error = "Please select a valid patient and enter valid billing amounts.";
            }
        }

        // 2. PROCESS PAYMENT FOR INVOICE
        elseif ($action === 'record_payment') {
            $billId  = (int)($_POST['bill_id'] ?? 0);
            $payAmt  = (float)($_POST['payment_amount'] ?? 0);

            if ($billId > 0 && $payAmt > 0) {
                try {
                    $db->beginTransaction();
                    $stmtB = $db->prepare("SELECT * FROM bills WHERE id = ? FOR UPDATE");
                    $stmtB->execute([$billId]);
                    $bill = $stmtB->fetch();

                    if ($bill) {
                        $newPaid = $bill['paid_amount'] + $payAmt;
                        $newDue  = max(0, $bill['total'] - $newPaid);
                        
                        $newStatus = 'Partially Paid';
                        if ($newDue <= 0) {
                            $newStatus = 'Paid';
                        }

                        $stmtUpd = $db->prepare("UPDATE bills SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?");
                        $stmtUpd->execute([$newPaid, $newDue, $newStatus, $billId]);

                        $db->commit();
                        logAudit($_SESSION['user_id'], "Recorded Payment of " . formatCurrency($payAmt) . " for Invoice #{$bill['invoice_number']}", 'Billing', $billId);
                        setFlashMessage('success', "Payment recorded successfully for Invoice {$bill['invoice_number']}.");
                        header('Location: billing.php');
                        exit();
                    }
                } catch (\Exception $e) {
                    $db->rollBack();
                    $error = "Payment recording error: " . $e->getMessage();
                }
            }
        }

        // 3. DELETE INVOICE RECORD
        elseif ($action === 'delete_invoice') {
            $billId = (int)($_POST['bill_id'] ?? 0);
            if ($billId > 0) {
                try {
                    $stmtDel = $db->prepare("DELETE FROM bills WHERE id = ?");
                    $stmtDel->execute([$billId]);
                    logAudit($_SESSION['user_id'], "Deleted Invoice Record #{$billId}", 'Billing');
                    setFlashMessage('success', "Invoice removed successfully.");
                    header('Location: billing.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Deletion error: " . $e->getMessage();
                }
            }
        }
    }
}

// ==========================================
// DYNAMIC REVENUE METRICS & DATA FETCHING
// ==========================================
$totalRevenue = (float)($db->query("SELECT SUM(paid_amount) FROM bills")->fetchColumn() ?: 0.00);
$totalDue     = (float)($db->query("SELECT SUM(due_amount) FROM bills WHERE status IN ('Unpaid', 'Partially Paid')")->fetchColumn() ?: 0.00);
$totalInvoices = (int)($db->query("SELECT COUNT(*) FROM bills")->fetchColumn() ?: 0);

$searchQuery = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$sql = "
    SELECT b.*, u.name as patient_name, u.email as patient_email, p.patient_id as patient_code
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (b.invoice_number LIKE ? OR u.name LIKE ? OR p.patient_id LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY b.created_at DESC";
$stmtBills = $db->prepare($sql);
$stmtBills->execute($params);
$invoices = $stmtBills->fetchAll();

// Fetch Patients for dynamic select dropdown
$allPatients = $db->query("
    SELECT p.id, p.patient_id, u.name 
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

        <!-- Title & Action Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-0">Financial Billing & Invoice Engine</h2>
                <p class="text-muted mb-0">Generate dynamic patient invoices, record payments, and monitor revenue accounts in real time.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                    <i class="bi bi-plus-circle me-1"></i> Create New Invoice
                </button>
                <a href="billing.php?export=csv" class="btn btn-outline-success fw-bold">
                    <i class="bi bi-filetype-csv me-1"></i> Export Ledger
                </a>
            </div>
        </div>

        <!-- Dynamic Real-Time KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white border border-success border-2 shadow-sm rounded-4">
                    <small class="text-success fw-bold"><i class="bi bi-cash-stack me-1"></i>TOTAL REVENUE COLLECTED</small>
                    <h3 class="fw-bold mb-0 text-dark"><?= formatCurrency($totalRevenue) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white border border-danger border-2 shadow-sm rounded-4">
                    <small class="text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i>OUTSTANDING RECEIVABLES</small>
                    <h3 class="fw-bold mb-0 text-danger"><?= formatCurrency($totalDue) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white border border-primary border-2 shadow-sm rounded-4">
                    <small class="text-primary fw-bold"><i class="bi bi-receipt me-1"></i>TOTAL INVOICES ISSUED</small>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totalInvoices ?> Records</h3>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="billing.php" class="row g-2">
                    <div class="col-md-7">
                        <input type="text" name="q" class="form-control" placeholder="Search invoice #, patient name, or patient ID..." value="<?= sanitize($searchQuery) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Payment Statuses</option>
                            <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="Partially Paid" <?= $statusFilter === 'Partially Paid' ? 'selected' : '' ?>>Partially Paid</option>
                            <option value="Unpaid" <?= $statusFilter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dynamic Invoice Ledger Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice Number</th>
                                <th>Patient Details</th>
                                <th>Subtotal</th>
                                <th>Tax / Discount</th>
                                <th>Total Amount</th>
                                <th>Paid</th>
                                <th>Due Balance</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): foreach ($invoices as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><code><?= sanitize($row['invoice_number']) ?></code></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($row['patient_name']) ?></div>
                                        <small class="text-muted">ID: <?= sanitize($row['patient_code']) ?></small>
                                    </td>
                                    <td><?= formatCurrency($row['subtotal']) ?></td>
                                    <td><small class="text-muted">+<?= formatCurrency($row['tax']) ?> / -<?= formatCurrency($row['discount']) ?></small></td>
                                    <td class="fw-bold text-dark"><?= formatCurrency($row['total']) ?></td>
                                    <td class="text-success fw-bold"><?= formatCurrency($row['paid_amount']) ?></td>
                                    <td class="text-danger fw-bold"><?= formatCurrency($row['due_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Partially Paid' ? 'warning' : 'danger') ?> px-3 py-2">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <?php if ($row['due_amount'] > 0): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item text-success fw-semibold"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#paymentModal"
                                                                data-id="<?= $row['id'] ?>"
                                                                data-num="<?= sanitize($row['invoice_number']) ?>"
                                                                data-due="<?= $row['due_amount'] ?>">
                                                            <i class="bi bi-cash-coin me-2"></i>Record Payment
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <button type="button" class="dropdown-item" onclick="printReceipt('<?= sanitize($row['invoice_number']) ?>', '<?= sanitize($row['patient_name']) ?>', '<?= $row['total'] ?>', '<?= $row['paid_amount'] ?>', '<?= $row['due_amount'] ?>', '<?= $row['status'] ?>')">
                                                        <i class="bi bi-printer me-2 text-primary"></i>Print Receipt
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="billing.php" method="POST" onsubmit="return confirm('Delete invoice <?= sanitize($row['invoice_number']) ?> permanently?');">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="delete_invoice">
                                                        <input type="hidden" name="bill_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash3 me-2"></i>Delete Invoice</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-receipt fs-2 d-block mb-2 text-secondary"></i>
                                        No billing invoices recorded yet. Click <strong>"Create New Invoice"</strong> above to generate real dynamic bills.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: CREATE DYNAMIC INVOICE               -->
<!-- ========================================== -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Generate Dynamic Patient Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_invoice">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Patient *</label>
                        <select name="patient_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach ($allPatients as $pt): ?>
                                <option value="<?= $pt['id'] ?>"><?= sanitize($pt['name']) ?> (ID: <?= sanitize($pt['patient_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subtotal Amount (NPR) *</label>
                            <input type="number" step="0.01" name="subtotal" id="inv_subtotal" class="form-control" placeholder="0.00" required oninput="calcTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tax (NPR)</label>
                            <input type="number" step="0.01" name="tax" id="inv_tax" class="form-control" value="0.00" oninput="calcTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Discount (NPR)</label>
                            <input type="number" step="0.01" name="discount" id="inv_discount" class="form-control" value="0.00" oninput="calcTotal()">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Initial Payment Received (NPR)</label>
                            <input type="number" step="0.01" name="paid_amount" id="inv_paid" class="form-control" value="0.00" oninput="calcTotal()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Calculated Total</label>
                            <input type="text" id="inv_total_display" class="form-control form-control-lg bg-light fw-bold text-success" value="NPR 0.00" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Billing Notes / Service Descriptions</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. OPD Consultation + Blood Test Fees"></textarea>
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

<!-- ========================================== -->
<!-- MODAL: RECORD PAYMENT                       -->
<!-- ========================================== -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack text-success me-2"></i>Record Invoice Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="billing.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="bill_id" id="pay_bill_id">

                    <p class="mb-1">Invoice: <strong id="pay_inv_num" class="text-primary"></strong></p>
                    <p class="text-muted mb-3">Outstanding Due: <strong id="pay_due_amt" class="text-danger"></strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enter Amount Received (NPR) *</label>
                        <input type="number" step="0.01" name="payment_amount" id="pay_amount_input" class="form-control form-control-lg" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcTotal() {
    const sub = parseFloat(document.getElementById('inv_subtotal').value) || 0;
    const tax = parseFloat(document.getElementById('inv_tax').value) || 0;
    const disc = parseFloat(document.getElementById('inv_discount').value) || 0;
    const total = Math.max(0, (sub + tax) - disc);
    document.getElementById('inv_total_display').value = 'NPR ' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    const payModal = document.getElementById('paymentModal');
    if (payModal) {
        payModal.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            const due = parseFloat(btn.getAttribute('data-due')) || 0;
            document.getElementById('pay_bill_id').value = btn.getAttribute('data-id');
            document.getElementById('pay_inv_num').textContent = btn.getAttribute('data-num');
            document.getElementById('pay_due_amt').textContent = 'NPR ' + due.toFixed(2);
            document.getElementById('pay_amount_input').value = due.toFixed(2);
        });
    }
});

function printReceipt(invNum, patient, total, paid, due, status) {
    const printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt - ${invNum}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                    .header { text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 10px; }
                    .details { margin: 20px 0; font-size: 14px; }
                    .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    .table th { background-color: #f8fafc; }
                    .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>CarePlus Hospital Management System</h2>
                    <p>Official Patient Invoice & Payment Receipt</p>
                </div>
                <div class="details">
                    <p><strong>Invoice Number:</strong> ${invNum}</p>
                    <p><strong>Patient Name:</strong> ${patient}</p>
                    <p><strong>Status:</strong> ${status}</p>
                </div>
                <table class="table">
                    <thead>
                        <tr><th>Description</th><th>Amount (NPR)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Total Billed Amount</td><td>NPR ${parseFloat(total).toFixed(2)}</td></tr>
                        <tr><td>Amount Paid</td><td>NPR ${parseFloat(paid).toFixed(2)}</td></tr>
                        <tr><td>Remaining Due Balance</td><td>NPR ${parseFloat(due).toFixed(2)}</td></tr>
                    </tbody>
                </table>
                <div class="footer">
                    <p>Thank you for choosing CarePlus Hospital.</p>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>