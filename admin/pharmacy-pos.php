<?php
/**
 * Admin / Pharmacist - Point of Sale & Dispensing Counter
 */
$pageTitle = "Pharmacy POS Counter";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin']);

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $medId = (int)($_POST['medicine_id'] ?? 0);
    $qty   = (int)($_POST['dispense_qty'] ?? 1);

    if ($medId > 0 && $qty > 0) {
        $stmtDeduct = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
        $stmtDeduct->execute([$qty, $medId]);
        setFlashMessage('success', "Dispensed {$qty} units. Stock inventory updated automatically.");
        header('Location: pharmacy-pos.php');
        exit();
    }
}

$medicines = $db->query("SELECT * FROM pharmacy_inventory WHERE stock_quantity > 0 ORDER BY medicine_name ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <h2 class="fw-bold mb-1">Pharmacy Dispensing POS</h2>
        <p class="text-muted mb-4">Direct medication checkout and barcode scan stock deduction counter.</p>

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-cart-check text-primary me-2"></i>Checkout Dispense Counter</h5>
                    <form action="pharmacy-pos.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Medicine / Scan Barcode *</label>
                            <select name="medicine_id" class="form-select form-select-lg" required>
                                <option value="">Search Medicine or Batch...</option>
                                <?php foreach ($medicines as $m): ?>
                                    <option value="<?= $m['id'] ?>">
                                        <?= sanitize($m['medicine_name']) ?> (Batch: <?= $m['batch_number'] ?> | Stock: <?= $m['stock_quantity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Quantity to Dispense *</label>
                            <input type="number" name="dispense_qty" class="form-control form-control-lg" value="1" min="1" required>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Complete Dispense & Deduct Stock
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">Active Stock Ready for Checkout</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Batch</th>
                                        <th>Available Stock</th>
                                        <th>Unit Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicines as $m): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= sanitize($m['medicine_name']) ?></td>
                                            <td><code><?= sanitize($m['batch_number']) ?></code></td>
                                            <td><span class="badge bg-primary px-3 py-1"><?= $m['stock_quantity'] ?> units</span></td>
                                            <td><?= formatCurrency($m['unit_price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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