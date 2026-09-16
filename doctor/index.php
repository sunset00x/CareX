<?php
/**
 * Doctor Dashboard Interface
 */
$pageTitle = "Doctor Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('doctor');

$user = currentUser();
$db = Database::getConnection();

// Fetch Doctor Record ID
$stmtD = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmtD->execute([$user['id']]);
$doctor = $stmtD->fetch();
$doctorId = $doctor['id'] ?? 0;

// KPI Aggregation Queries
$stmtToday = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURRENT_DATE() AND status IN ('Pending', 'Confirmed')");
$stmtToday->execute([$doctorId]);
$countToday = $stmtToday->fetchColumn();

$stmtTotalPatients = $db->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
$stmtTotalPatients->execute([$doctorId]);
$countPatients = $stmtTotalPatients->fetchColumn();

$stmtPendingLab = $db->prepare("SELECT COUNT(*) FROM lab_tests WHERE doctor_id = ? AND status IN ('Pending', 'In-Progress')");
$stmtPendingLab->execute([$doctorId]);
$countPendingLab = $stmtPendingLab->fetchColumn();

// Fetch Today's Appointment Schedule
$stmtSchedule = $db->prepare("
    SELECT a.*, u.name as patient_name, u.phone, p.patient_id as patient_code, p.gender, p.blood_group
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = CURRENT_DATE()
    ORDER BY a.appointment_time ASC
");
$stmtSchedule->execute([$doctorId]);
$todayList = $stmtSchedule->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Doctor Portal - Welcome Dr. <?= sanitize($user['name']) ?></h2>
                <p class="text-muted">Manage your daily appointments and clinical records.</p>
            </div>
        </div>

        <!-- Dashboard Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-primary-subtle text-primary me-3"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countToday ?></h3>
                            <small class="text-muted fw-semibold">Appointments Today</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-success-subtle text-success me-3"><i class="bi bi-people"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countPatients ?></h3>
                            <small class="text-muted fw-semibold">Total Unique Patients</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-warning-subtle text-warning me-3"><i class="bi bi-virus"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countPendingLab ?></h3>
                            <small class="text-muted fw-semibold">Pending Lab Results</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Schedule Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Today's Consultation Schedule (<?= date('M d, Y') ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Gender / Blood</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($todayList)): foreach ($todayList as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= formatTime($row['appointment_time']) ?></td>
                                    <td><span class="badge bg-secondary"><?= sanitize($row['patient_code']) ?></span></td>
                                    <td class="fw-semibold"><?= sanitize($row['patient_name']) ?></td>
                                    <td><?= ucfirst(sanitize($row['gender'])) ?> (<?= sanitize($row['blood_group'] ?: 'N/A') ?>)</td>
                                    <td><?= sanitize($row['reason']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Confirmed' ? 'success' : ($row['status'] == 'Completed' ? 'info' : 'warning') ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="medical-records.php?action=create&appointment_id=<?= $row['id'] ?>&patient_id=<?= $row['patient_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-file-medical me-1"></i> Add Record
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No patient consultations scheduled for today.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>