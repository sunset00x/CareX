<?php
ob_start();

$pageTitle = "Pharmacy POS & Dispensing";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'pharmacist']);

$db = Database::getConnection();
$error = '';

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS pharmacy_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_no VARCHAR(50) NOT NULL UNIQUE,
            patient_id INT DEFAULT 0,
            medicine_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'Cash',
            dispensed_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (\PDOException $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } else {
        if ($action === 'process_sale') {
            $patientId  = (int)($_POST['patient_id'] ?? 0);
            $medicineId = (int)($_POST['medicine_id'] ?? 0);
            $quantity   = (int)($_POST['quantity'] ?? 0);
            $payMethod  = sanitize($_POST['payment_method'] ?? 'Cash');

            if ($medicineId > 0 && $quantity > 0) {
                try {
                    $db->beginTransaction();

                    $stmtMed = $db->prepare("SELECT * FROM pharmacy_inventory WHERE id = ? FOR UPDATE");
                    $stmtMed->execute([$medicineId]);
                    $med = $stmtMed->fetch();

                    if (!$med) {
                        $stmtMedAlt = $db->prepare("SELECT * FROM pharmacy WHERE id = ? FOR UPDATE");
                        $stmtMedAlt->execute([$medicineId]);
                        $med = $stmtMedAlt->fetch();
                    }

                    if (!$med) {
                        throw new \Exception("Medicine record not found.");
                    }

                    $currentStock = $med['stock_quantity'] ?? $med['quantity'] ?? $med['stock'] ?? 0;
                    $unitPrice    = $med['unit_price'] ?? $med['price'] ?? $med['rate'] ?? 0.00;
                    $brandName    = $med['brand_name'] ?? $med['medicine_name'] ?? $med['name'] ?? 'Medicine';

                    if ($currentStock < $quantity) {
                        throw new \Exception("Insufficient stock! Available: {$currentStock} units.");
                    }

                    $totalCost = $unitPrice * $quantity;

                    try {
                        $stmtDeduct = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                        $stmtDeduct->execute([$quantity, $medicineId]);
                    } catch (\PDOException $e) {
                        try {
                            $stmtDeduct = $db->prepare("UPDATE pharmacy SET quantity = GREATEST(0, quantity - ?) WHERE id = ?");
                            $stmtDeduct->execute([$quantity, $medicineId]);
                        } catch (\PDOException $ex) {
                            $stmtDeduct = $db->prepare("UPDATE medicines SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                            $stmtDeduct->execute([$quantity, $medicineId]);
                        }
                    }

                    $receiptNo = 'REC-' . date('Ymd') . '-' . rand(1000, 9999);
                    $dispensedBy = $_SESSION['user_id'] ?? 1;

                    $stmtSales = $db->prepare("
                        INSERT INTO pharmacy_sales 
                        (receipt_no, patient_id, medicine_id, quantity, unit_price, total_amount, payment_method, dispensed_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtSales->execute([$receiptNo, $patientId, $medicineId, $quantity, $unitPrice, $totalCost, $payMethod, $dispensedBy]);

                    if ($patientId > 0) {
                        $possibleColumns = ['total_amount', 'amount', 'grand_total', 'total'];
                        foreach ($possibleColumns as $col) {
                            try {
                                $stmtInv = $db->prepare("INSERT INTO invoices (invoice_number, patient_id, {$col}, status) VALUES (?, ?, ?, 'Paid')");
                                $stmtInv->execute([$receiptNo, $patientId, $totalCost]);
                                break;
                            } catch (\PDOException $e) {
                                continue;
                            }
                        }
                    }

                    $db->commit();
                    logAudit($dispensedBy, "Dispensed {$quantity} x {$brandName} (Total: " . formatCurrency($totalCost) . ")", 'Pharmacy POS');
                    setFlashMessage('success', "Sale completed successfully! Total: " . formatCurrency($totalCost));
                    
                    header('Location: pharmacy-pos.php?print_receipt=' . urlencode($receiptNo));
                    exit();

                } catch (\Exception $e) {
                    $db->rollBack();
                    $error = "POS Transaction Failed: " . $e->getMessage();
                }
            } else {
                $error = "Please select a valid medicine and quantity.";
            }
        }
    }
}

$medicines = [];
$tableNames = ['pharmacy_inventory', 'pharmacy', 'medicines'];

foreach ($tableNames as $table) {
    try {
        $rawMeds = $db->query("SELECT * FROM {$table}")->fetchAll();
        
        if (!empty($rawMeds)) {
            foreach ($rawMeds as $m) {
                $id    = $m['id'] ?? 0;
                $name  = $m['brand_name'] ?? $m['medicine_name'] ?? $m['name'] ?? $m['title'] ?? 'Unknown Medicine';
                $stock = $m['stock_quantity'] ?? $m['quantity'] ?? $m['stock'] ?? $m['qty'] ?? 0;
                $price = $m['unit_price'] ?? $m['price'] ?? $m['rate'] ?? $m['cost'] ?? 0.00;

                $medicines[] = [
                    'id'            => $id,
                    'display_name'  => $name,
                    'display_stock' => (int)$stock,
                    'display_price' => (float)$price
                ];
            }
            break; 
        }
    } catch (\PDOException $e) {
        continue;
    }
}

$patients = [];
try {
    $patients = $db->query("
        SELECT p.id, u.name 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY u.name ASC
    ")->fetchAll();
} catch (\PDOException $e) {
    $patients = [];
}

$printReceiptNo = sanitize($_GET['print_receipt'] ?? '');
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <?php if (!empty($printReceiptNo)): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center mb-4">
                <div>
                    <i class="bi bi-check-circle-fill me-2"></i> Sale recorded! Receipt ID: <strong><?= $printReceiptNo ?></strong>
                </div>
                <a href="pharmacy-receipt.php?receipt_no=<?= urlencode($printReceiptNo) ?>" target="_blank" class="btn btn-sm btn-success fw-bold rounded-pill px-3">
                    <i class="bi bi-printer me-1"></i> Print Receipt Now
                </a>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Pharmacy Point of Sale (POS)</h2>
                <p class="text-muted mb-0">Dispense prescription medicines, auto-deduct stock, and issue payment receipts.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-cart-check me-2"></i>Dispense & Checkout</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="pharmacy-pos.php" method="POST" id="posForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="process_sale">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Patient (Optional / Walk-in)</label>
                                <select name="patient_id" class="form-select">
                                    <option value="0">-- Walk-In Customer --</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Medicine / Product *</label>
                                <select name="medicine_id" id="medicine_select" class="form-select" required>
                                    <option value="">Choose medicine stock...</option>
                                    <?php if (!empty($medicines)): foreach ($medicines as $m): ?>
                                        <option value="<?= $m['id'] ?>" 
                                                data-price="<?= $m['display_price'] ?>" 
                                                data-stock="<?= $m['display_stock'] ?>">
                                            <?= sanitize($m['display_name']) ?> — <?= formatCurrency($m['display_price']) ?> (Stock: <?= $m['display_stock'] ?>)
                                        </option>
                                    <?php endforeach; else: ?>
                                        <option value="" disabled>No medicines found in inventory</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Quantity to Dispense *</label>
                                    <input type="number" name="quantity" id="dispense_qty" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card / Digital Payment</option>
                                    </select>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Unit Price:</span>
                                    <span class="fw-bold" id="unit_price_text">NPR 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold fs-5 text-dark">Total Amount Due:</span>
                                    <span class="fw-bold fs-4 text-success" id="total_amount_text">NPR 0.00</span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill">
                                <i class="bi bi-printer me-2"></i> Complete Sale & Issue Receipt
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-secondary"><i class="bi bi-box-seam me-2"></i>Pharmacy Stock List</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Stock</th>
                                        <th class="text-end pe-3">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medicines)): foreach ($medicines as $m): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?= sanitize($m['display_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $m['display_stock'] <= 10 ? 'danger' : 'success' ?>">
                                                    <?= $m['display_stock'] ?> Units
                                                </span>
                                            </td>
                                            <td class="text-end pe-3 fw-bold text-success"><?= formatCurrency($m['display_price']) ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No medicines registered in inventory.</td></tr>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const medSelect = document.getElementById('medicine_select');
    const qtyInput  = document.getElementById('dispense_qty');
    const unitPriceTxt  = document.getElementById('unit_price_text');
    const totalAmountTxt = document.getElementById('total_amount_text');

    function updateCalculations() {
        const selectedOption = medSelect.options[medSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            unitPriceTxt.textContent = 'NPR 0.00';
            totalAmountTxt.textContent = 'NPR 0.00';
            return;
        }

        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const qty   = parseInt(qtyInput.value) || 1;
        const total = price * qty;

        unitPriceTxt.textContent = 'NPR ' + price.toFixed(2);
        totalAmountTxt.textContent = 'NPR ' + total.toFixed(2);
    }

    medSelect.addEventListener('change', updateCalculations);
    qtyInput.addEventListener('input', updateCalculations);

    <?php if (!empty($printReceiptNo)): ?>
        window.open('pharmacy-receipt.php?receipt_no=<?= urlencode($printReceiptNo) ?>', '_blank');
    <?php endif; ?>
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>