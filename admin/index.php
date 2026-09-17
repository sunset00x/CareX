<?php
/**
 * Master Operational Analytics Dashboard
 * CareX Smart Hospital Management System
 */
$pageTitle = "Admin Master Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

// Core System Metrics
$totalPatients = (int)($db->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?: 0);
$totalDoctors  = (int)($db->query("SELECT COUNT(*) FROM doctors")->fetchColumn() ?: 0);
$todayAppts    = (int)($db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn() ?: 0);

// Advanced Module Metrics
$occupiedBeds  = (int)($db->query("SELECT COUNT(*) FROM hospital_beds WHERE status = 'Occupied'")->fetchColumn() ?: 0);
$totalBeds     = (int)($db->query("SELECT COUNT(*) FROM hospital_beds")->fetchColumn() ?: 1);
$bedOccupancy  = round(($occupiedBeds / $totalBeds) * 100);

$lowStockDrugs = (int)($db->query("SELECT COUNT(*) FROM pharmacy_inventory WHERE stock_quantity <= reorder_level")->fetchColumn() ?: 0);
$criticalBlood = (int)($db->query("SELECT COUNT(*) FROM blood_bank WHERE units_available < 5")->fetchColumn() ?: 0);

// Recent Appointments Queue
$recentAppts = $db->query("
    SELECT a.*, u_pat.name as patient_name, u_doc.name as doctor_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_pat ON p.user_id = u_pat.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <!-- Welcome Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Master Operations Control</h2>
                <p class="text-muted mb-0">Real-time hospital metrics, bed occupancy, pharmacy alerts, and clinical workflows.</p>
            </div>
            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fw-bold">
                <i class="bi bi-circle-fill fs-6 me-1 text-success"></i> All Systems Operational
            </span>
        </div>

        <!-- Emergency & Critical Inventory Alerts -->
        <?php if ($lowStockDrugs > 0 || $criticalBlood > 0): ?>
            <div class="row g-3 mb-4">
                <?php if ($lowStockDrugs > 0): ?>
                    <div class="col-md-6">
                        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-0">
                            <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
                            <div>
                                <strong class="d-block">Pharmacy Inventory Warning</strong>
                                <small>There are <strong><?= $lowStockDrugs ?></strong> medicine items at or below reorder levels. <a href="pharmacy.php" class="fw-bold text-dark">View Inventory</a></small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($criticalBlood > 0): ?>
                    <div class="col-md-6">
                        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-0">
                            <i class="bi bi-droplet-half fs-3 me-3 text-danger"></i>
                            <div>
                                <strong class="d-block">Critical Blood Reserve Alert</strong>
                                <small><strong><?= $criticalBlood ?></strong> blood types are at critical storage levels. <a href="blood-bank.php" class="fw-bold text-dark">Check Blood Reserves</a></small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Top Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary text-white">
                    <small class="text-white-50 fw-bold text-uppercase">Today's Appointments</small>
                    <h2 class="fw-bold my-1"><?= $todayAppts ?></h2>
                    <small class="text-white-50"><a href="appointments.php" class="text-white text-decoration-none">Manage Schedule &rarr;</a></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-dark text-white">
                    <small class="text-white-50 fw-bold text-uppercase">IPD Bed Occupancy</small>
                    <h2 class="fw-bold my-1"><?= $bedOccupancy ?>%</h2>
                    <small class="text-white-50"><?= $occupiedBeds ?> of <?= $totalBeds ?> beds assigned. <a href="beds.php" class="text-white text-decoration-none">Bed Matrix &rarr;</a></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border">
                    <small class="text-muted fw-bold text-uppercase">Total Patients</small>
                    <h2 class="fw-bold my-1 text-dark"><?= number_format($totalPatients) ?></h2>
                    <small class="text-muted"><a href="users.php?role=patient" class="text-primary text-decoration-none">View Registry &rarr;</a></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border">
                    <small class="text-muted fw-bold text-uppercase">Active Doctors</small>
                    <h2 class="fw-bold my-1 text-dark"><?= $totalDoctors ?></h2>
                    <small class="text-muted"><a href="doctors.php" class="text-primary text-decoration-none">Manage Roster &rarr;</a></small>
                </div>
            </div>
        </div>

        <!-- Quick Module Launchpad -->
        <h5 class="fw-bold mb-3">Operational Launchpad</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="beds.php" class="card border-0 shadow-sm rounded-4 text-decoration-none p-3 h-100 border-start border-4 border-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-hospital fs-2 text-info me-3"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">IPD Ward Matrix</h6>
                            <small class="text-muted">Bed allocations & ICU status</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="pharmacy.php" class="card border-0 shadow-sm rounded-4 text-decoration-none p-3 h-100 border-start border-4 border-warning">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-capsule fs-2 text-warning me-3"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Pharmacy Inventory</h6>
                            <small class="text-muted">Drug stock & expiry tracking</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="blood-bank.php" class="card border-0 shadow-sm rounded-4 text-decoration-none p-3 h-100 border-start border-4 border-danger">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-droplet-fill fs-2 text-danger me-3"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Blood Bank Reserve</h6>
                            <small class="text-muted">Units available by group</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="doctor-schedules.php" class="card border-0 shadow-sm rounded-4 text-decoration-none p-3 h-100 border-start border-4 border-success">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-clock fs-2 text-success me-3"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Doctor Shifts</h6>
                            <small class="text-muted">Working hours & slot limits</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Appointments Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Consultation Requests</h5>
                <a href="appointments.php" class="btn btn-sm btn-outline-primary fw-bold rounded-pill">View All Appointments</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appointment No</th>
                                <th>Patient</th>
                                <th>Attending Physician</th>
                                <th>Date & Time Slot</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAppts)): foreach ($recentAppts as $a): ?>
                                <tr>
                                    <td><code><?= sanitize($a['appointment_number']) ?></code></td>
                                    <td class="fw-bold text-dark"><?= sanitize($a['patient_name']) ?></td>
                                    <td>Dr. <?= sanitize($a['doctor_name']) ?></td>
                                    <td><?= formatDate($a['appointment_date']) ?> | <?= formatTime($a['appointment_time']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['status'] === 'Confirmed' ? 'success' : ($a['status'] === 'Completed' ? 'info' : 'warning') ?>">
                                            <?= sanitize($a['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No appointments recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>