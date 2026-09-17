<?php
ob_start();
/**
 * Admin - IPD Bed Matrix & Inpatient Admission Management
 * CareX Smart Hospital Management System
 */
$pageTitle = "IPD Bed Matrix";
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
        // ADD NEW BED
        if ($action === 'add_bed') {
            $bedNumber = sanitize($_POST['bed_number'] ?? '');
            $ward      = sanitize($_POST['ward'] ?? 'General Ward');
            $bedType   = sanitize($_POST['bed_type'] ?? 'Standard');
            $dailyRate = (float)($_POST['daily_rate'] ?? 0.00);

            if (!empty($bedNumber)) {
                try {
                    $stmt = $db->prepare("INSERT INTO hospital_beds (bed_number, ward, bed_type, daily_rate, status) VALUES (?, ?, ?, ?, 'Available')");
                    $stmt->execute([$bedNumber, $ward, $bedType, $dailyRate]);

                    logAudit($_SESSION['user_id'], "Added Bed {$bedNumber} ({$ward})", 'Beds');
                    setFlashMessage('success', "Bed '{$bedNumber}' created successfully.");
                    header('Location: beds.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Error adding bed: " . $e->getMessage();
                }
            } else {
                $error = "Bed number is mandatory.";
            }
        }
        // TOGGLE BED STATUS
        elseif ($action === 'update_bed_status') {
            $bedId     = (int)($_POST['bed_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? 'Available');

            if ($bedId > 0) {
                $stmt = $db->prepare("UPDATE hospital_beds SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $bedId]);

                logAudit($_SESSION['user_id'], "Updated Bed #{$bedId} status to {$newStatus}", 'Beds');
                setFlashMessage('success', "Bed status updated to '{$newStatus}'.");
                header('Location: beds.php');
                exit();
            }
        }
    }
}

// Safely fetch beds with fallback if 'ward' column does not exist
try {
    $beds = $db->query("SELECT * FROM hospital_beds ORDER BY ward ASC, bed_number ASC")->fetchAll();
} catch (\PDOException $e) {
    $beds = $db->query("SELECT * FROM hospital_beds ORDER BY bed_number ASC")->fetchAll();
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Inpatient (IPD) Bed Matrix</h2>
                <p class="text-muted mb-0">Track ward occupancy, bed availability, and daily room rates.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addBedModal">
                <i class="bi bi-plus-lg me-1"></i> Add Hospital Bed
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Bed Number</th>
                                <th>Ward</th>
                                <th>Type</th>
                                <th>Daily Charge</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($beds)): foreach ($beds as $b): ?>
                                <tr>
                                    <td><code class="fs-6"><?= sanitize($b['bed_number'] ?? $b['bed_code'] ?? 'N/A') ?></code></td>
                                    <td class="fw-bold text-dark"><?= sanitize($b['ward'] ?? $b['ward_name'] ?? 'General Ward') ?></td>
                                    <td><?= sanitize($b['bed_type'] ?? 'Standard') ?></td>
                                    <td class="fw-bold text-success"><?= formatCurrency($b['daily_rate'] ?? $b['charge'] ?? 0.00) ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($b['status'] ?? 'Available') === 'Available' ? 'success' : (($b['status'] ?? '') === 'Occupied' ? 'danger' : 'warning text-dark') ?>">
                                            <?= sanitize($b['status'] ?? 'Available') ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="beds.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_bed_status">
                                            <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto me-1" onchange="this.form.submit()">
                                                <option value="Available" <?= ($b['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                                                <option value="Occupied" <?= ($b['status'] ?? '') === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                                                <option value="Maintenance" <?= ($b['status'] ?? '') === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                <option value="Cleaning" <?= ($b['status'] ?? '') === 'Cleaning' ? 'selected' : '' ?>>Cleaning</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No beds registered in IPD matrix.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Bed -->
<div class="modal fade" id="addBedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-hospital text-primary me-2"></i>Add Hospital Bed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="beds.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_bed">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Number / Code *</label>
                        <input type="text" name="bed_number" class="form-control" placeholder="e.g. ICU-01, GW-102" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ward / Department</label>
                        <select name="ward" class="form-select">
                            <option value="General Ward">General Ward</option>
                            <option value="ICU">ICU (Intensive Care Unit)</option>
                            <option value="CCU">CCU (Coronary Care Unit)</option>
                            <option value="Maternity Ward">Maternity Ward</option>
                            <option value="Pediatric Ward">Pediatric Ward</option>
                            <option value="Private Cabin">Private Cabin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Type</label>
                        <select name="bed_type" class="form-select">
                            <option value="Standard">Standard Electric</option>
                            <option value="ICU Ventilator">ICU Ventilator Bed</option>
                            <option value="Semi-Private">Semi-Private</option>
                            <option value="VIP Cabin">VIP Deluxe Cabin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Daily Rate / Charge *</label>
                        <input type="number" step="0.01" name="daily_rate" class="form-control" value="1500.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Register Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>