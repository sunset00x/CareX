<?php
/**
 * Admin - Pharmacy Inventory & Stock Entry Console
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Pharmacy Inventory & Expiry Control";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS (ADD PHARMACY STOCK)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = sanitize($_POST['action'] ?? '');

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Validation Error.";
    } elseif ($action === 'add_stock') {
        $medName   = sanitize($_POST['medicine_name'] ?? '');
        $batchNum  = strtoupper(sanitize($_POST['batch_number'] ?? ''));
        $category  = sanitize($_POST['category'] ?? 'General');
        $quantity  = (int)($_POST['stock_quantity'] ?? 0);
        $price     = (float)($_POST['unit_price'] ?? 0.00);
        $expiry    = $_POST['expiry_date'] ?? '';
        $reorder   = (int)($_POST['reorder_level'] ?? 20);

        if (empty($medName) || empty($batchNum) || empty($expiry) || $quantity <= 0) {
            $error = "Please fill in all medicine details, valid quantity, and expiry date.";
        } else {
            try {
                $stmtIns = $db->prepare("
                    INSERT INTO pharmacy_inventory 
                    (medicine_name, batch_number, category, stock_quantity, unit_price, expiry_date, reorder_level) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$medName, $batchNum, $category, $quantity, $price, $expiry, $reorder]);

                logAudit($_SESSION['user_id'], "Added Pharmacy Stock '{$medName}' Batch {$batchNum}", 'Pharmacy');
                setFlashMessage('success', "Stock entry for '{$medName}' added successfully.");
                header('Location: pharmacy.php');
                exit();
            } catch (\Exception $e) {
                $error = "Stock Creation Error: " . $e->getMessage();
            }
        }
    }
}

// Data Queries
$inventory = $db->query("
    SELECT *, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry 
    FROM pharmacy_inventory 
    ORDER BY expiry_date ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger shadow-sm"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Pharmacy & Medication Inventory</h2>
                <p class="text-muted mb-0">Monitor stock levels, batch numbers, unit prices, and near-expiry drugs.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStockModal">
                <i class="bi bi-capsule-solid me-1"></i> Add Medicine Stock
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine & Batch</th>
                                <th>Category</th>
                                <th>Stock Quantity</th>
                                <th>Unit Price</th>
                                <th>Expiry Date</th>
                                <th>Status Alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($inventory)): foreach ($inventory as $item): 
                                $isLowStock = $item['stock_quantity'] <= $item['reorder_level'];
                                $isExpiring = $item['days_to_expiry'] <= 30;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($item['medicine_name']) ?></div>
                                        <code><?= sanitize($item['batch_number']) ?></code>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= sanitize($item['category']) ?></span></td>
                                    <td>
                                        <span class="fw-bold <?= $isLowStock ? 'text-danger' : 'text-dark' ?>">
                                            <?= number_format($item['stock_quantity']) ?> units
                                        </span>
                                    </td>
                                    <td><?= formatCurrency($item['unit_price']) ?></td>
                                    <td><?= formatDate($item['expiry_date']) ?></td>
                                    <td>
                                        <?php if ($isExpiring): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Expiring Soon</span>
                                        <?php elseif ($isLowStock): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-arrow-down-circle me-1"></i>Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Optimal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No pharmacy inventory items logged yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New Medicine Stock -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-capsule text-primary me-2"></i>Add Pharmacy Stock Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="pharmacy.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_stock">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Medicine Name *</label>
                        <input type="text" name="medicine_name" class="form-control" placeholder="e.g. Paracetamol 500mg, Amoxicillin" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Batch Number *</label>
                            <input type="text" name="batch_number" class="form-control" placeholder="e.g. BAT-2026-09" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g. Antibiotic, Analgesic" value="General">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Stock Quantity *</label>
                            <input type="number" name="stock_quantity" class="form-control" value="100" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unit Price *</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" value="10.00" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Low-Stock Alert Level</label>
                            <input type="number" name="reorder_level" class="form-control" value="20" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Add Inventory Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>