<?php
/**
 * Patient Dashboard Interface
 */
$pageTitle = "Patient Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$user = currentUser();
$db = Database::getConnection();

// Fetch Patient Identity ID
$stmtP = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmtP->execute([$user['id']]);
$patient = $stmtP->fetch();
$patientId = $patient['id'] ?? 0;

// KPI Aggregation Queries
$stmtUpcoming = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date >= CURRENT_DATE() AND status IN ('Pending', 'Confirmed')");
$stmtUpcoming->execute([$patientId]);
$countUpcoming = $stmtUpcoming->fetchColumn();

$stmtEmr = $db->prepare("SELECT COUNT(*) FROM medical_records WHERE patient_id = ?");
$stmtEmr->execute([$patientId]);
$countEmr = $stmtEmr->fetchColumn();

$stmtPresc = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?");
$stmtPresc->execute([$patientId]);
$countPresc = $stmtPresc->fetchColumn();

$stmtBills = $db->prepare("SELECT COUNT(*) FROM bills WHERE patient_id = ? AND status IN ('Unpaid', 'Partially Paid')");
$stmtBills->execute([$patientId]);
$countBills = $stmtBills->fetchColumn();

// Fetch Upcoming Appointments list
$stmtAppts = $db->prepare("
    SELECT a.*, d.specialization, u.name as doctor_name, dept.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURRENT_DATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5
");
$stmtAppts->execute([$patientId]);
$upcomingList = $stmtAppts->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Welcome, <?= sanitize($user['name']) ?></h2>
                <p class="text-muted">Here is your personal medical portal overview.</p>
            </div>
            <a href="book-appointment.php" class="btn btn-primary rounded-pill px-4"><i class="bi bi-calendar-plus me-2"></i>Book New Appointment</a>
        </div>

        <!-- Dashboard KPI Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-primary-subtle text-primary me-3"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countUpcoming ?></h3>
                            <small class="text-muted fw-semibold">Upcoming Appts</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-info-subtle text-info me-3"><i class="bi bi-journal-medical"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countEmr ?></h3>
                            <small class="text-muted fw-semibold">Medical Records</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-success-subtle text-success me-3"><i class="bi bi-capsule"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countPresc ?></h3>
                            <small class="text-muted fw-semibold">Prescriptions</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-warning-subtle text-warning me-3"><i class="bi bi-receipt"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countBills ?></h3>
                            <small class="text-muted fw-semibold">Pending Invoices</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments Data Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Upcoming Appointments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt Number</th>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($upcomingList)): foreach ($upcomingList as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['appointment_number']) ?></td>
                                    <td><?= sanitize($row['doctor_name']) ?></td>
                                    <td><?= sanitize($row['dept_name']) ?></td>
                                    <td><?= formatDate($row['appointment_date']) ?> at <?= formatTime($row['appointment_time']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Confirmed' ? 'success' : 'warning' ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="appointments.php" class="btn btn-sm btn-outline-secondary">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No upcoming appointments scheduled.</td>
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