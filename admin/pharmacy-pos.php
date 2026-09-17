<?php
ob_start();

$pageTitle = "Pharmacy POS & Dispensing";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'pharmacist']);

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } else {

        // PROCESS PHARMACY SALE / DISPENSE
        if ($action === 'process_sale') {
            $patientId  = (int)($_POST['patient_id'] ?? 0);
            $medicineId = (int)($_POST['medicine_id'] ?? 0);
            $quantity   = (int)($_POST['quantity'] ?? 0);
            $payMethod  = sanitize($_POST['payment_method'] ?? 'Cash');

            if ($medicineId > 0 && $quantity > 0) {
                try {
                    $db->beginTransaction();

                    // 1. Fetch & Check Stock
                    $stmtMed = $db->prepare("SELECT * FROM pharmacy_inventory WHERE id = ? FOR UPDATE");
                    $stmtMed->execute([$medicineId]);
                    $med = $stmtMed->fetch();

                    if (!$med) {
                        throw new \Exception("Medicine record not found.");
                    }

                    $currentStock = $med['stock_quantity'] ?? $med['quantity'] ?? 0;
                    $unitPrice    = $med['unit_price'] ?? $med['price'] ?? 0.00;
                    $brandName    = $med['brand_name'] ?? $med['medicine_name'] ?? 'Medicine';

                    if ($currentStock < $quantity) {
                        throw new \Exception("Insufficient stock! Available: {$currentStock} units.");
                    }

                    $totalCost = $unitPrice * $quantity;

                    // 2. Deduct Inventory Stock
                    $stmtDeduct = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                    $stmtDeduct->execute([$quantity, $medicineId]);

                    // 3. Create Patient Invoice if patient assigned
                    if ($patientId > 0) {
                        $invNum = 'PHARM-' . date('Ymd') . '-' . rand(1000, 9999);
                        $stmtInv = $db->prepare("INSERT INTO invoices (invoice_number, patient_id, total_amount, status) VALUES (?, ?, ?, 'Paid')");
                        $stmtInv->execute([$invNum, $patientId, $totalCost]);
                    }

                    $db->commit();
                    logAudit($_SESSION['user_id'], "Dispensed {$quantity} x {$brandName} (Total: " . formatCurrency($totalCost) . ")", 'Pharmacy POS');
                    setFlashMessage('success', "Sale completed successfully! Total: " . formatCurrency($totalCost));
                    header('Location: pharmacy-pos.php');
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

// ==========================================
// SAFE FETCH ALL MEDICINES FOR DROPDOWN
// ==========================================
$medicines = [];
try {
    $medicines = $db->query("
        SELECT *, 
               COALESCE(brand_name, medicine_name, 'Medicine') as display_name,
               COALESCE(stock_quantity, quantity, 0) as display_stock,
               COALESCE(unit_price, price, 0.00) as display_price
        FROM pharmacy_inventory 
        ORDER BY display_name ASC
    ")->fetchAll();
} catch (\PDOException $e) {
    $medicines = [];
}

// Fetch Active Patients
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
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Pharmacy Point of Sale (POS)</h2>
                <p class="text-muted mb-0">Dispense prescription medicines, auto-deduct stock, and issue payment receipts.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: POS Checkout Form -->
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

                            <!-- Calculation Display -->
                            <div class="p-3 bg-light rounded-3 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Unit Price:</span>
                                    <span class="fw-bold" id="unit_price_text">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold fs-5 text-dark">Total Amount Due:</span>
                                    <span class="fw-bold fs-4 text-success" id="total_amount_text">$0.00</span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill">
                                <i class="bi bi-printer me-2"></i> Complete Sale & Issue Receipt
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Side: Quick Inventory Look-up -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-secondary"><i class="bi bi-box-seam me-2"></i>Quick Stock Look-up</h5>
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
            unitPriceTxt.textContent = '$0.00';
            totalAmountTxt.textContent = '$0.00';
            return;
        }

        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const qty   = parseInt(qtyInput.value) || 1;
        const total = price * qty;

        unitPriceTxt.textContent = '$' + price.toFixed(2);
        totalAmountTxt.textContent = '$' + total.toFixed(2);
    }

    medSelect.addEventListener('change', updateCalculations);
    qtyInput.addEventListener('input', updateCalculations);
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>