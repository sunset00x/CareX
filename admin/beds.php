<?php
/**
 * Admin - IPD Bed Management Matrix & Bed Registration
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "IPD Bed Allocation Matrix";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS (CREATE & STATUS UPDATES)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = sanitize($_POST['action'] ?? 'update_status');

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Validation Error.";
    } else {
        // 1. ADD NEW BED
        if ($action === 'add_bed') {
            $bedNum   = strtoupper(sanitize($_POST['bed_number'] ?? ''));
            $wardType = sanitize($_POST['ward_type'] ?? 'General Ward');
            $charge   = (float)($_POST['daily_charge'] ?? 500.00);

            if (empty($bedNum)) {
                $error = "Bed number/code is required.";
            } else {
                try {
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM hospital_beds WHERE bed_number = ?");
                    $stmtCheck->execute([$bedNum]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $error = "Bed number '{$bedNum}' already exists in the system.";
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO hospital_beds (bed_number, ward_type, daily_charge, status) VALUES (?, ?, ?, 'Available')");
                        $stmtIns->execute([$bedNum, $wardType, $charge]);

                        logAudit($_SESSION['user_id'], "Added New Bed '{$bedNum}' ({$wardType})", 'Beds');
                        setFlashMessage('success', "New bed '{$bedNum}' registered successfully.");
                        header('Location: beds.php');
                        exit();
                    }
                } catch (\Exception $e) {
                    $error = "Bed Creation Error: " . $e->getMessage();
                }
            }
        }
        // 2. UPDATE BED STATUS
        elseif ($action === 'update_status') {
            $bedId  = (int)($_POST['bed_id'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'Available');
            $patId  = (int)($_POST['patient_id'] ?? 0);

            if ($bedId > 0) {
                $stmtUpd = $db->prepare("UPDATE hospital_beds SET status = ?, assigned_patient_id = ? WHERE id = ?");
                $stmtUpd->execute([$status, ($status === 'Occupied' ? $patId : NULL), $bedId]);

                logAudit($_SESSION['user_id'], "Updated Bed #{$bedId} Status to {$status}", 'Beds', $bedId);
                setFlashMessage('success', "Bed #{$bedId} status updated to {$status}.");
                header('Location: beds.php');
                exit();
            }
        }
    }
}

// Data Queries
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
        <?php if (!empty($error)): ?><div class="alert alert-danger shadow-sm"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">In-Patient Bed Matrix</h2>
                <p class="text-muted mb-0">Real-time occupancy tracking and bed unit registration for hospital wards.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addBedModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Bed Unit
            </button>
        </div>

        <!-- Visual Bed Grid -->
        <div class="row g-3">
            <?php if (!empty($beds)): foreach ($beds as $b): 
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
                                <p class="small mb-3 text-primary fw-bold"><i class="bi bi-person-fill me-1"></i><?= sanitize($b['patient_name'] ?: 'Assigned Patient') ?></p>
                            <?php else: ?>
                                <p class="small mb-3 text-muted">Ready for allocation</p>
                            <?php endif; ?>

                            <!-- Update Status Trigger -->
                            <form action="beds.php" method="POST" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_status">
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
            <?php endforeach; else: ?>
                <div class="col-12 text-center py-5 text-muted">No beds registered. Click "Add New Bed Unit" to configure wards.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Add New Bed Unit -->
<div class="modal fade" id="addBedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-hospital text-primary me-2"></i>Register New Bed Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="beds.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_bed">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Number / Code *</label>
                        <input type="text" name="bed_number" class="form-control" placeholder="e.g. BED-ICU-03, BED-GEN-105" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ward Category *</label>
                        <select name="ward_type" class="form-select" required>
                            <option value="General Ward">General Ward</option>
                            <option value="ICU">ICU (Intensive Care Unit)</option>
                            <option value="CCU">CCU (Coronary Care Unit)</option>
                            <option value="Private Deluxe">Private Deluxe Room</option>
                            <option value="Emergency">Emergency Room Bed</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Daily Charge Rate (Per Day) *</label>
                        <input type="number" step="0.01" name="daily_charge" class="form-control" value="800.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Bed Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>