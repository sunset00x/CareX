<?php

$pageTitle = "Invoices";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('billing');

$user = currentUser();
$db = Database::getConnection();

$action = sanitize($_GET['action'] ?? '');
$invoiceId = (int)($_GET['id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $discount  = (float)($_POST['discount'] ?? 0);
    $taxRate   = (float)($_POST['tax_rate'] ?? 0);
    $items     = $_POST['items'] ?? [];
    $token     = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } elseif (!$patientId || empty($items)) {
        $error = "Please select a patient and add at least one line item.";
    } else {
        try {
            $db->beginTransaction();

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += ((int)$item['quantity'] * (float)$item['unit_price']);
            }

            $taxAmount = ($subtotal - $discount) * ($taxRate / 100);
            $total = ($subtotal - $discount) + $taxAmount;

            $invNum = generateInvoiceNumber();
            $stmtInv = $db->prepare("INSERT INTO bills (invoice_number, patient_id, subtotal, discount, tax, total, paid_amount, due_amount, status) VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid')");
            $stmtInv->execute([$invNum, $patientId, $subtotal, $discount, $taxAmount, $total, $total]);
            $billId = $db->lastInsertId();

            $stmtItem = $db->prepare("INSERT INTO bill_items (bill_id, service_name, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                if (!empty($item['service_name'])) {
                    $itemTotal = (int)$item['quantity'] * (float)$item['unit_price'];
                    $stmtItem->execute([$billId, sanitize($item['service_name']), (int)$item['quantity'], (float)$item['unit_price'], $itemTotal]);
                }
            }

            $db->commit();
            logAudit($user['id'], 'Generated Invoice', 'Billing', $billId);
            setFlashMessage('success', "Invoice {$invNum} generated successfully!");
            header("Location: invoices.php?id={$billId}");
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Invoice creation error: " . $e->getMessage();
        }
    }
}

// Fetch Specific Invoice Details
$invoice = null;
$invoiceItems = [];
if ($invoiceId > 0) {
    $stmtInv = $db->prepare("
        SELECT b.*, u.name as patient_name, u.phone, p.patient_id as patient_code, p.address
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE b.id = ?
    ");
    $stmtInv->execute([$invoiceId]);
    $invoice = $stmtInv->fetch();

    if ($invoice) {
        $stmtItems = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmtItems->execute([$invoiceId]);
        $invoiceItems = $stmtItems->fetchAll();
    }
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <?php if ($invoice): ?>
            <!-- Printable Invoice View -->
            <div class="d-flex justify-content-between align-items-center mb-4 btn-print-hide">
                <a href="invoices.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Invoices</a>
                <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Print Invoice</button>
            </div>

            <div class="card border-0 shadow rounded-4 p-5 bg-white">
                <div class="d-flex justify-content-between border-bottom pb-4 mb-4">
                    <div>
                        <h2 class="fw-bold text-primary mb-0"><i class="bi bi-heart-pulse-fill me-2"></i>CareX Hospital</h2>
                        <p class="text-muted mb-0">Smart Healthcare Services</p>
                    </div>
                    <div class="text-end">
                        <h4 class="fw-bold">INVOICE</h4>
                        <span class="badge bg-secondary"><?= sanitize($invoice['invoice_number']) ?></span>
                        <p class="text-muted small mt-1">Date: <?= formatDate($invoice['created_at']) ?></p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="fw-bold text-secondary">Billed To:</h6>
                        <h5 class="fw-bold mb-0"><?= sanitize($invoice['patient_name']) ?></h5>
                        <p class="text-muted mb-0">ID: <?= sanitize($invoice['patient_code']) ?></p>
                        <p class="text-muted mb-0">Phone: <?= sanitize($invoice['phone']) ?></p>
                    </div>
                </div>

                <table class="table table-bordered mb-4">
                    <thead class="table-light">
                        <tr>
                            <th>Service Description</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoiceItems as $item): ?>
                            <tr>
                                <td><?= sanitize($item['service_name']) ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                                <td class="text-end fw-bold"><?= formatCurrency($item['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between py-1"><span>Subtotal:</span><span><?= formatCurrency($invoice['subtotal']) ?></span></div>
                        <div class="d-flex justify-content-between py-1 text-success"><span>Discount:</span><span>-<?= formatCurrency($invoice['discount']) ?></span></div>
                        <div class="d-flex justify-content-between py-1 text-muted"><span>Tax:</span><span>+<?= formatCurrency($invoice['tax']) ?></span></div>
                        <hr>
                        <div class="d-flex justify-content-between py-1 fw-bold fs-4 text-primary"><span>Total Amount:</span><span><?= formatCurrency($invoice['total']) ?></span></div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'create'): ?>
            <!-- Invoice Generator Form -->
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3">
                            <h4 class="fw-bold mb-0">Create Hospital Patient Invoice</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                            <form action="invoices.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Select Patient *</label>
                                    <select name="patient_id" class="form-select form-select-lg" required>
                                        <option value="">-- Select Billed Patient --</option>
                                        <?php
                                        $patients = $db->query("SELECT p.id, u.name, p.patient_id FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
                                        foreach ($patients as $p):
                                        ?>
                                            <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?> (<?= sanitize($p['patient_id']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <h5 class="fw-bold text-primary mb-3">Invoice Line Items</h5>
                                <div id="itemContainer">
                                    <div class="row g-2 mb-2 item-row">
                                        <div class="col-md-6"><input type="text" name="items[0][service_name]" class="form-control" placeholder="Service / Test / Fee Description" required></div>
                                        <div class="col-md-2"><input type="number" name="items[0][quantity]" class="form-control" value="1" min="1" required></div>
                                        <div class="col-md-3"><input type="number" step="0.01" name="items[0][unit_price]" class="form-control" placeholder="Price (NPR)" required></div>
                                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-item" disabled>X</button></div>
                                    </div>
                                </div>

                                <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-secondary mb-4"><i class="bi bi-plus-lg me-1"></i> Add Service Item</button>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Discount Amount (NPR)</label>
                                        <input type="number" step="0.01" name="discount" class="form-control" value="0.00">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Tax Rate (%)</label>
                                        <input type="number" step="0.01" name="tax_rate" class="form-control" value="13.00">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Generate Invoice</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                let itemIdx = 1;
                const container = document.getElementById('itemContainer');
                document.getElementById('addItemBtn').addEventListener('click', function() {
                    const row = document.createElement('div');
                    row.className = 'row g-2 mb-2 item-row';
                    row.innerHTML = `
                        <div class="col-md-6"><input type="text" name="items[${itemIdx}][service_name]" class="form-control" placeholder="Service Description" required></div>
                        <div class="col-md-2"><input type="number" name="items[${itemIdx}][quantity]" class="form-control" value="1" min="1" required></div>
                        <div class="col-md-3"><input type="number" step="0.01" name="items[${itemIdx}][unit_price]" class="form-control" placeholder="Price" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-item">X</button></div>
                    `;
                    container.appendChild(row);
                    itemIdx++;
                });

                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-item') && document.querySelectorAll('.item-row').length > 1) {
                        e.target.closest('.item-row').remove();
                    }
                });
            });
            </script>
        <?php else: ?>
            <!-- All Invoices List -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Invoices Directory</h3>
                <a href="invoices.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-circle me-1"></i> Create Invoice</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $invoices = $db->query("SELECT b.*, u.name as patient_name FROM bills b JOIN patients p ON b.patient_id = p.id JOIN users u ON p.user_id = u.id ORDER BY b.created_at DESC")->fetchAll();
                                if (!empty($invoices)): foreach ($invoices as $inv):
                                ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= sanitize($inv['invoice_number']) ?></td>
                                        <td><?= sanitize($inv['patient_name']) ?></td>
                                        <td class="fw-bold"><?= formatCurrency($inv['total']) ?></td>
                                        <td class="text-success"><?= formatCurrency($inv['paid_amount']) ?></td>
                                        <td class="text-danger"><?= formatCurrency($inv['due_amount']) ?></td>
                                        <td><span class="badge bg-<?= $inv['status'] == 'Paid' ? 'success' : 'danger' ?>"><?= sanitize($inv['status']) ?></span></td>
                                        <td><a href="invoices.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary">View / Print</a></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No invoices recorded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>