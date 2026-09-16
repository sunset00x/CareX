<?php
/**
 * Doctor Availability Schedule Manager
 */
$pageTitle = "Manage Availability";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('doctor');

$user = currentUser();
$db = Database::getConnection();

// Fetch Doctor Record ID
$stmtD = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmtD->execute([$user['id']]);
$doctor = $stmtD->fetch();
$doctorId = $doctor['id'];

$error = '';

// Handle Add/Update Availability Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day          = sanitize($_POST['day_of_week'] ?? '');
    $startTime    = sanitize($_POST['start_time'] ?? '');
    $endTime      = sanitize($_POST['end_time'] ?? '');
    $slotDuration = (int)($_POST['slot_duration'] ?? 20);
    $token        = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } elseif (empty($day) || empty($startTime) || empty($endTime)) {
        $error = "Please fill in all mandatory schedule fields.";
    } elseif (strtotime($startTime) >= strtotime($endTime)) {
        $error = "Start time must be strictly before End time.";
    } else {
        try {
            // Check existing slot for this day
            $stmtCheck = $db->prepare("SELECT id FROM doctor_availability WHERE doctor_id = ? AND day_of_week = ?");
            $stmtCheck->execute([$doctorId, $day]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $stmtUpd = $db->prepare("UPDATE doctor_availability SET start_time = ?, end_time = ?, slot_duration = ?, status = 'active' WHERE id = ?");
                $stmtUpd->execute([$startTime, $endTime, $slotDuration, $existing['id']]);
                setFlashMessage('success', "Availability updated for {$day}.");
            } else {
                $stmtIns = $db->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, slot_duration, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmtIns->execute([$doctorId, $day, $startTime, $endTime, $slotDuration]);
                setFlashMessage('success', "Availability slot saved for {$day}.");
            }
            logAudit($user['id'], 'Updated Availability', 'Doctor Schedule', $doctorId);
            header('Location: availability.php');
            exit();
        } catch (\Exception $e) {
            $error = "Schedule operation error: " . $e->getMessage();
        }
    }
}

// Fetch Current Active Availability Schedule
$stmtAvail = $db->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$stmtAvail->execute([$doctorId]);
$availabilities = $stmtAvail->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Manage Clinical Working Hours</h3>

        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="row g-4">
            <!-- Schedule Setter Form -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Set Working Hours</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="availability.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Day of Week</label>
                                <select name="day_of_week" class="form-select" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Slot Duration (Minutes)</label>
                                <select name="slot_duration" class="form-select" required>
                                    <option value="15">15 Minutes</option>
                                    <option value="20" selected>20 Minutes</option>
                                    <option value="30">30 Minutes</option>
                                    <option value="45">45 Minutes</option>
                                    <option value="60">60 Minutes</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">Save Availability Slot</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Active Schedule Table -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Current Weekly Schedule</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Day</th>
                                    <th>Hours</th>
                                    <th>Slot Length</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($availabilities)): foreach ($availabilities as $row): ?>
                                    <tr>
                                        <td class="fw-bold"><?= sanitize($row['day_of_week']) ?></td>
                                        <td><?= formatTime($row['start_time']) ?> - <?= formatTime($row['end_time']) ?></td>
                                        <td><?= $row['slot_duration'] ?> mins</td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No availability schedules configured.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>