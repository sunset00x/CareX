<?php
/**
 * Admin - Doctor Shift & Schedule Roster Management
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Doctor Schedules & Rosters";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// SAVE / UPDATE DOCTOR SCHEDULE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error.";
    } else {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'save_schedule') {
            $doctorId = (int)($_POST['doctor_id'] ?? 0);
            $day      = sanitize($_POST['available_day'] ?? '');
            $startTime = $_POST['start_time'] ?? '';
            $endTime   = $_POST['end_time'] ?? '';
            $duration  = (int)($_POST['slot_duration_mins'] ?? 20);
            $status    = sanitize($_POST['status'] ?? 'active');

            if ($doctorId > 0 && !empty($day) && !empty($startTime) && !empty($endTime)) {
                try {
                    // Check existing schedule for same doctor & day
                    $stmtCheck = $db->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND available_day = ?");
                    $stmtCheck->execute([$doctorId, $day]);
                    $existing = $stmtCheck->fetch();

                    if ($existing) {
                        $stmtUpd = $db->prepare("UPDATE doctor_schedules SET start_time = ?, end_time = ?, slot_duration_mins = ?, status = ? WHERE id = ?");
                        $stmtUpd->execute([$startTime, $endTime, $duration, $status, $existing['id']]);
                        setFlashMessage('success', "Updated {$day} schedule for Doctor.");
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO doctor_schedules (doctor_id, available_day, start_time, end_time, slot_duration_mins, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtIns->execute([$doctorId, $day, $startTime, $endTime, $duration, $status]);
                        setFlashMessage('success', "Added new {$day} shift for Doctor.");
                    }

                    logAudit($_SESSION['user_id'], "Configured Doctor #{$doctorId} Shift for {$day}", 'Schedules');
                    header('Location: doctor-schedules.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Schedule error: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all shift details.";
            }
        }
    }
}

// Fetch all doctors and their active schedules
$doctors = $db->query("
    SELECT d.id as doctor_table_id, d.doctor_id as doc_code, d.specialization, u.name as doctor_name, dept.name as dept_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY u.name ASC
")->fetchAll();

$schedules = $db->query("
    SELECT ds.*, u.name as doctor_name, dept.name as dept_name
    FROM doctor_schedules ds
    JOIN doctors d ON ds.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY u.name ASC, FIELD(available_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Doctor Shift & Schedule Roster</h2>
                <p class="text-muted mb-0">Define available shift hours and slot durations per doctor to prevent booking conflicts.</p>
            </div>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="bi bi-calendar-plus me-1"></i> Add Shift Schedule
            </button>
        </div>

        <!-- Roster Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Doctor Name</th>
                                <th>Department</th>
                                <th>Working Day</th>
                                <th>Shift Hours</th>
                                <th>Slot Duration</th>
                                <th>Shift Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($schedules)): foreach ($schedules as $s): ?>
                                <tr>
                                    <td class="fw-bold text-dark">Dr. <?= sanitize($s['doctor_name']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= sanitize($s['dept_name']) ?></span></td>
                                    <td><span class="badge bg-dark px-3 py-2"><?= sanitize($s['available_day']) ?></span></td>
                                    <td class="fw-semibold">
                                        <?= date('h:i A', strtotime($s['start_time'])) ?> – <?= date('h:i A', strtotime($s['end_time'])) ?>
                                    </td>
                                    <td><?= $s['slot_duration_mins'] ?> Mins / Patient</td>
                                    <td>
                                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= $s['status'] === 'active' ? 'Active Shift' : 'On Leave / Unavailable' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No shifts set up yet. Click "Add Shift Schedule" to set doctor working hours.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Shift Schedule -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Configure Doctor Working Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="doctor-schedules.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="save_schedule">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Doctor *</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">-- Choose Doctor --</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?= $doc['doctor_table_id'] ?>">Dr. <?= sanitize($doc['doctor_name']) ?> (<?= sanitize($doc['dept_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Available Day *</label>
                        <select name="available_day" class="form-select" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" value="09:00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Time *</label>
                            <input type="time" name="end_time" class="form-control" value="17:00" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Slot Duration (Minutes)</label>
                            <input type="number" name="slot_duration_mins" class="form-control" value="20" min="5" step="5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Shift Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active Shift</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Shift Roster</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>