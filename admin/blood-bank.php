<?php
ob_start();
/**
 * Admin - Blood Bank Reserves & Donor Unit Tracking
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Blood Bank Reserves";
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
        if ($action === 'add_blood_unit') {
            $bloodGroup = sanitize($_POST['blood_group'] ?? '');
            $component  = sanitize($_POST['component'] ?? 'Whole Blood');
            $units      = (int)($_POST['units_available'] ?? 0);

            if (!empty($bloodGroup) && $units > 0) {
                try {
                    $stmt = $db->prepare("INSERT INTO blood_bank (blood_group, component, units_available) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + ?");
                    $stmt->execute([$bloodGroup, $component, $units, $units]);

                    logAudit($_SESSION['user_id'], "Added {$units} units of {$bloodGroup} ({$component})", 'Blood Bank');
                    setFlashMessage('success', "Added {$units} units of {$bloodGroup} to Blood Bank inventory.");
                    header('Location: blood-bank.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Error updating blood bank: " . $e->getMessage();
                }
            } else {
                $error = "Select a valid blood group and unit count.";
            }
        }
    }
}

// Fetch Reserves
$reserves = $db->query("SELECT * FROM blood_bank ORDER BY blood_group ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Blood Bank Inventory & Reserves</h2>
                <p class="text-muted mb-0">Track available blood bags, component separation, and emergency reserves.</p>
            </div>
            <button class="btn btn-danger fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addBloodModal">
                <i class="bi bi-droplet-fill me-1"></i> Add Blood Bag Stock
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Blood Group</th>
                                <th>Component Type</th>
                                <th>Available Stock</th>
                                <th>Status Alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reserves)): foreach ($reserves as $b): ?>
                                <tr>
                                    <td><span class="badge bg-danger fs-6 px-3 py-2"><?= sanitize($b['blood_group']) ?></span></td>
                                    <td class="fw-bold text-dark"><?= sanitize($b['component'] ?? 'Whole Blood') ?></td>
                                    <td class="fw-bold fs-5"><?= $b['units_available'] ?> Bag(s)</td>
                                    <td>
                                        <span class="badge bg-<?= $b['units_available'] <= 3 ? 'danger' : 'success' ?>">
                                            <?= $b['units_available'] <= 3 ? 'Low Stock Critical' : 'Sufficient Reserve' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No blood bags logged in reserve inventory.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Blood -->
<div class="modal fade" id="addBloodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-droplet-fill me-2"></i>Add Blood Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="blood-bank.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_blood_unit">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Blood Group *</label>
                        <select name="blood_group" class="form-select" required>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Blood Component</label>
                        <select name="component" class="form-select">
                            <option value="Whole Blood">Whole Blood</option>
                            <option value="Packed Red Blood Cells (PRBC)">Packed Red Blood Cells (PRBC)</option>
                            <option value="Fresh Frozen Plasma (FFP)">Fresh Frozen Plasma (FFP)</option>
                            <option value="Platelet Concentrate">Platelet Concentrate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Number of Bags / Units *</label>
                        <input type="number" name="units_available" class="form-control" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Add Reserve Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>