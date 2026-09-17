<?php
ob_start();
/**
 * Admin - Pharmacy Medicine Stock & Expiry Management
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Pharmacy Stock Inventory";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// POST ACTION HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } else {
        if ($action === 'add_medicine') {
            $brandName   = sanitize($_POST['brand_name'] ?? '');
            $genericName = sanitize($_POST['generic_name'] ?? '');
            $category    = sanitize($_POST['category'] ?? 'Tablet');
            $stockQty    = (int)($_POST['stock_quantity'] ?? 0);
            $unitPrice   = (float)($_POST['unit_price'] ?? 0.00);
            $expiryDate  = sanitize($_POST['expiry_date'] ?? '');

            if (!empty($brandName) && $stockQty >= 0) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO pharmacy_inventory (brand_name, generic_name, category, stock_quantity, unit_price, expiry_date) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$brandName, $genericName, $category, $stockQty, $unitPrice, $expiryDate]);

                    logAudit($_SESSION['user_id'], "Added Medicine {$brandName}", 'Pharmacy');
                    setFlashMessage('success', "Medicine '{$brandName}' added to inventory.");
                    header('Location: pharmacy.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Error adding medicine: " . $e->getMessage();
                }
            } else {
                $error = "Brand Name and stock quantity are mandatory.";
            }
        }
    }
}

// Safely fetch inventory regardless of column naming schema
try {
    $medicines = $db->query("SELECT * FROM pharmacy_inventory ORDER BY brand_name ASC")->fetchAll();
} catch (\PDOException $e) {
    // Fallback if schema uses 'medicine_name' instead of 'brand_name'
    $medicines = $db->query("SELECT *, medicine_name AS brand_name FROM pharmacy_inventory ORDER BY medicine_name ASC")->fetchAll();
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Pharmacy Stock Inventory</h2>
                <p class="text-muted mb-0">Track pharmaceutical supplies, unit pricing, stock levels, and expiry alerts.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addMedModal">
                <i class="bi bi-capsule me-1"></i> Add Medicine Stock
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Brand Name</th>
                                <th>Generic Formulation</th>
                                <th>Category</th>
                                <th>Stock Qty</th>
                                <th>Unit Price</th>
                                <th>Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($medicines)): foreach ($medicines as $m): 
                                $brand = $m['brand_name'] ?? $m['medicine_name'] ?? 'Unknown';
                                $qty   = $m['stock_quantity'] ?? $m['quantity'] ?? 0;
                                $price = $m['unit_price'] ?? $m['price'] ?? 0.00;
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= sanitize($brand) ?></td>
                                    <td><small class="text-muted"><?= sanitize($m['generic_name'] ?? 'N/A') ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><?= sanitize($m['category'] ?? 'General') ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $qty <= 10 ? 'danger' : 'success' ?> px-3 py-1 fs-6">
                                            <?= $qty ?> Units
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success"><?= formatCurrency($price) ?></td>
                                    <td>
                                        <?php if (!empty($m['expiry_date'])): ?>
                                            <small class="<?= (strtotime($m['expiry_date']) - time() < 2592000) ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                <?= formatDate($m['expiry_date']) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">N/A</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No medicines registered in inventory.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Medicine -->
<div class="modal fade" id="addMedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-capsule text-primary me-2"></i>Add Medicine Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="pharmacy.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_medicine">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Brand Name *</label>
                        <input type="text" name="brand_name" class="form-control" placeholder="e.g. Paracetamol, Amoxicillin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Generic Formulation</label>
                        <input type="text" name="generic_name" class="form-control" placeholder="e.g. Acetaminophen 500mg">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select">
                                <option value="Tablet">Tablet</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Syrup">Syrup / Liquid</option>
                                <option value="Injection">Injection</option>
                                <option value="Ointment">Ointment / Cream</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Stock Quantity *</label>
                            <input type="number" name="stock_quantity" class="form-control" value="100" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unit Price *</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" value="10.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>