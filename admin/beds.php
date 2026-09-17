<?php
/**
 * Admin - IPD Bed Management Matrix
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "IPD Bed Allocation Matrix";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

// Action: Update Bed Status / Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $bedId  = (int)($_POST['bed_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'Available');
    $patId  = (int)($_POST['patient_id'] ?? 0);

    $stmtUpd = $db->prepare("UPDATE hospital_beds SET status = ?, assigned_patient_id = ? WHERE id = ?");
    $stmtUpd->execute([$status, ($status === 'Occupied' ? $patId : NULL), $bedId]);
    setFlashMessage('success', "Bed #{$bedId} status updated to {$status}.");
    header('Location: beds.php');
    exit();
}

$beds = $db->query("
    SELECT b.*, u.name as patient_name 
    FROM hospital_beds b 
    LEFT JOIN patients p ON b.assigned_patient_id = p.id 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY b.ward_type ASC, b.bed_number ASC
")->fetchAll();

$patients = $db->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">In-Patient Bed Matrix</h2>
                <p class="text-muted mb-0">Real-time occupancy tracking for ICU, General, and Private wards.</p>
            </div>
        </div>

        <!-- Visual Bed Grid -->
        <div class="row g-3">
            <?php foreach ($beds as $b): 
                $badgeColor = $b['status'] === 'Available' ? 'success' : ($b['status'] === 'Occupied' ? 'danger' : 'warning');
            ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-<?= $badgeColor ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-dark"><?= sanitize($b['ward_type']) ?></span>
                                <span class="badge bg-<?= $badgeColor ?>"><?= $b['status'] ?></span>
                            </div>
                            <h5 class="fw-bold mb-1"><?= sanitize($b['bed_number']) ?></h5>
                            <small class="text-muted d-block mb-2">Rate: <?= formatCurrency($b['daily_charge']) ?> / day</small>
                            
                            <?php if ($b['status'] === 'Occupied'): ?>
                                <p class="small mb-3 text-primary fw-bold"><i class="bi bi-person-fill me-1"></i><?= sanitize($b['patient_name']) ?></p>
                            <?php else: ?>
                                <p class="small mb-3 text-muted">Ready for allocation</p>
                            <?php endif; ?>

                            <!-- Update Trigger -->
                            <form action="beds.php" method="POST" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="Available" <?= $b['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                    <option value="Occupied" <?= $b['status'] === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                                    <option value="Cleaning" <?= $b['status'] === 'Cleaning' ? 'selected' : '' ?>>Cleaning</option>
                                    <option value="Maintenance" <?= $b['status'] === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>