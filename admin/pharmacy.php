<?php
/**
 * Admin - Pharmacy Inventory & Expiry Tracker
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Pharmacy Inventory & Expiry Control";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

$inventory = $db->query("
    SELECT *, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry 
    FROM pharmacy_inventory 
    ORDER BY expiry_date ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h2 class="fw-bold mb-1">Pharmacy & Medication Inventory</h2>
        <p class="text-muted mb-4">Monitor stock levels, batch numbers, and near-expiry drugs.</p>

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
                            <?php foreach ($inventory as $item): 
                                $isLowStock = $item['stock_quantity'] <= $item['reorder_level'];
                                $isExpiring = $item['days_to_expiry'] <= 30;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= sanitize($item['medicine_name']) ?></div>
                                        <code><?= sanitize($item['batch_number']) ?></code>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= sanitize($item['category']) ?></span></td>
                                    <td>
                                        <span class="fw-bold <?= $isLowStock ? 'text-danger' : 'text-dark' ?>">
                                            <?= $item['stock_quantity'] ?> units
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
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>