<?php
/**
 * Admin - Appointments Directory & Conflict-Free Booking Engine
 * CareX Smart Hospital Management System
 */
$pageTitle = "Manage Appointments";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// CREATE APPOINTMENT WITH CONFLICT GUARD
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = sanitize($_POST['action'] ?? '');

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error.";
    } else {
        if ($action === 'create_appointment') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            $doctorId  = (int)($_POST['doctor_id'] ?? 0);
            $apptDate  = $_POST['appointment_date'] ?? '';
            $apptTime  = $_POST['appointment_time'] ?? '';
            $reason    = sanitize($_POST['reason'] ?? '');

            if ($patientId > 0 && $doctorId > 0 && !empty($apptDate) && !empty($apptTime)) {
                // 1. Calculate Day of Week
                $dayOfWeek = date('l', strtotime($apptDate)); // e.g., 'Friday'

                // 2. Check Doctor Working Hours & Leave Status
                $stmtSched = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND available_day = ? AND status = 'active'");
                $stmtSched->execute([$doctorId, $dayOfWeek]);
                $schedule = $stmtSched->fetch();

                if (!$schedule) {
                    $error = "Booking Conflict: Doctor is either On Leave or has no working shift scheduled on {$dayOfWeek}s.";
                } else {
                    $shiftStart = $schedule['start_time'];
                    $shiftEnd   = $schedule['end_time'];

                    if ($apptTime < $shiftStart || $apptTime >= $shiftEnd) {
                        $error = "Booking Conflict: Selected time ({$apptTime}) is outside doctor's shift hours (" . date('h:i A', strtotime($shiftStart)) . " - " . date('h:i A', strtotime($shiftEnd)) . ").";
                    } else {
                        // 3. Double Booking Check (No overlapping slots)
                        $stmtCheck = $db->prepare("
                            SELECT COUNT(*) FROM appointments 
                            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('Pending', 'Confirmed')
                        ");
                        $stmtCheck->execute([$doctorId, $apptDate, $apptTime]);
                        $conflictCount = (int)$stmtCheck->fetchColumn();

                        if ($conflictCount > 0) {
                            $error = "Double-Booking Blocked: This doctor already has an active appointment at {$apptTime} on {$apptDate}.";
                        } else {
                            // Safe to book!
                            try {
                                $apptNum = 'APT-' . date('Ymd') . '-' . rand(1000, 9999);
                                $stmtIns = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_number, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Confirmed')");
                                $stmtIns->execute([$patientId, $doctorId, $apptNum, $apptDate, $apptTime, $reason]);

                                logAudit($_SESSION['user_id'], "Booked Conflict-Free Appointment {$apptNum}", 'Appointments');
                                setFlashMessage('success', "Appointment {$apptNum} successfully booked.");
                                header('Location: appointments.php');
                                exit();
                            } catch (\Exception $e) {
                                $error = "Booking Error: " . $e->getMessage();
                            }
                        }
                    }
                }
            } else {
                $error = "Please complete all appointment fields.";
            }
        }
        // Update Status
        elseif ($action === 'update_status') {
            $apptId    = (int)($_POST['appointment_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? '');

            if ($apptId > 0 && in_array($newStatus, ['Pending', 'Confirmed', 'Completed', 'Cancelled'])) {
                $stmtUpd = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $stmtUpd->execute([$newStatus, $apptId]);

                logAudit($_SESSION['user_id'], "Updated Appointment #{$apptId} Status to {$newStatus}", 'Appointments', $apptId);
                setFlashMessage('success', "Appointment status updated to '{$newStatus}'.");
                header('Location: appointments.php');
                exit();
            }
        }
    }
}

// Data Queries
$statusFilter = sanitize($_GET['status'] ?? '');
$searchQuery  = sanitize($_GET['q'] ?? '');

$sql = "
    SELECT a.*, 
           u_pat.name as patient_name, p.patient_id as patient_code,
           u_doc.name as doctor_name, dept.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_pat ON p.user_id = u_pat.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (a.appointment_number LIKE ? OR u_pat.name LIKE ? OR u_doc.name LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmtAppts = $db->prepare($sql);
$stmtAppts->execute($params);
$appointments = $stmtAppts->fetchAll();

// Fetch Patients & Doctors for dropdowns
$allPatients = $db->query("SELECT p.id, p.patient_id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$allDoctors  = $db->query("SELECT d.id, u.name, dept.name as dept_name FROM doctors d JOIN users u ON d.user_id = u.id JOIN departments dept ON d.department_id = dept.id ORDER BY u.name ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger shadow-sm border-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= sanitize($error) ?></div><?php endif; ?>

        <!-- Title & Action Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-0">Appointments & Scheduling Console</h2>
                <p class="text-muted mb-0">Manage patient consultations with real-time shift checking and double-booking conflict guards.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="doctor-schedules.php" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-clock-history me-1"></i> Doctor Shift Rosters
                </a>
                <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#bookApptModal">
                    <i class="bi bi-calendar-plus me-1"></i> New Booking
                </button>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="appointments.php" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="q" class="form-control" placeholder="Search appointment number, patient, or doctor..." value="<?= sanitize($searchQuery) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Confirmed" <?= $statusFilter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt Number</th>
                                <th>Patient</th>
                                <th>Doctor & Department</th>
                                <th>Date & Time Slot</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments)): foreach ($appointments as $a): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><code><?= sanitize($a['appointment_number']) ?></code></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($a['patient_name']) ?></div>
                                        <small class="text-muted">ID: <?= sanitize($a['patient_code']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">Dr. <?= sanitize($a['doctor_name']) ?></div>
                                        <small class="text-muted"><?= sanitize($a['dept_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= formatDate($a['appointment_date']) ?></span><br>
                                        <small class="text-primary font-monospace"><?= formatTime($a['appointment_time']) ?></small>
                                    </td>
                                    <td><?= sanitize($a['reason'] ?: 'Routine Checkup') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['status'] === 'Confirmed' ? 'success' : ($a['status'] === 'Completed' ? 'info' : ($a['status'] === 'Cancelled' ? 'danger' : 'warning')) ?> px-3 py-1">
                                            <?= sanitize($a['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="appointments.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <option value="Pending" <?= $a['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Confirmed" <?= $a['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="Completed" <?= $a['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $a['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No appointments found matching filter.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Booking with Real-Time Validation -->
<div class="modal fade" id="bookApptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-check text-primary me-2"></i>Conflict-Free Booking Engine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="appointments.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_appointment">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach ($allPatients as $pt): ?>
                                <option value="<?= $pt['id'] ?>"><?= sanitize($pt['name']) ?> (ID: <?= sanitize($pt['patient_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Attending Doctor *</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">-- Choose Doctor --</option>
                            <?php foreach ($allDoctors as $dc): ?>
                                <option value="<?= $dc['id'] ?>">Dr. <?= sanitize($dc['name']) ?> (<?= sanitize($dc['dept_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date *</label>
                            <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Time Slot *</label>
                            <input type="time" name="appointment_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason / Symptoms</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Primary complaint..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Check Availability & Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>