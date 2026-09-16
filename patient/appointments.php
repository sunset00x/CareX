<?php
/**
 * Patient - My Appointments History & Management
 */
$pageTitle = "My Appointments";
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

// Cancel Appointment Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    $token  = $_POST['csrf_token'] ?? '';

    if (verifyCSRFToken($token)) {
        // Ensure record belongs to logged in patient
        $stmtCheck = $db->prepare("SELECT appointment_number FROM appointments WHERE id = ? AND patient_id = ? AND status IN ('Pending', 'Confirmed')");
        $stmtCheck->execute([$apptId, $patientId]);
        $appt = $stmtCheck->fetch();

        if ($appt) {
            $stmtCancel = $db->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
            $stmtCancel->execute([$apptId]);
            logAudit($user['id'], 'Cancelled Appointment', 'Appointments', $apptId);
            setFlashMessage('success', "Appointment {$appt['appointment_number']} has been cancelled.");
        } else {
            setFlashMessage('danger', "Unable to cancel this appointment.");
        }
        header('Location: appointments.php');
        exit();
    }
}

// Fetch Appointments List
$stmtAppts = $db->prepare("
    SELECT a.*, u.name as doctor_name, d.specialization, dept.name as dept_name, d.consultation_fee
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmtAppts->execute([$patientId]);
$appointments = $stmtAppts->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">My Appointments History</h3>
            <a href="book-appointment.php" class="btn btn-primary rounded-pill px-4"><i class="bi bi-calendar-plus me-1"></i> Book New</a>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt Number</th>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Date & Time</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments)): foreach ($appointments as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['appointment_number']) ?></td>
                                    <td class="fw-semibold">Dr. <?= sanitize($row['doctor_name']) ?></td>
                                    <td><?= sanitize($row['dept_name']) ?></td>
                                    <td><?= formatDate($row['appointment_date']) ?><br><small class="text-muted"><?= formatTime($row['appointment_time']) ?></small></td>
                                    <td><?= sanitize($row['reason'] ?: 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = 'secondary';
                                        if ($row['status'] === 'Confirmed') $badgeClass = 'success';
                                        if ($row['status'] === 'Pending') $badgeClass = 'warning';
                                        if ($row['status'] === 'Completed') $badgeClass = 'info';
                                        if ($row['status'] === 'Cancelled') $badgeClass = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= sanitize($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (in_array($row['status'], ['Pending', 'Confirmed']) && $row['appointment_date'] >= date('Y-m-d')): ?>
                                            <form action="appointments.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No appointment history found.</td>
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