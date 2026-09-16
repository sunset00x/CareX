<?php
/**
 * Doctor - Manage Appointments & Consultation Status
 */
$pageTitle = "Doctor Appointments";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('doctor');

$user = currentUser();
$db = Database::getConnection()

// Fetch Doctor Record ID
$stmtD = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmtD->execute([$user['id']]);
$doctor = $stmtD->fetch();
$doctorId = $doctor['id'] ?? 0;

// Status Update Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $apptId    = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $token     = $_POST['csrf_token'] ?? '';

    if (verifyCSRFToken($token) && in_array($newStatus, ['Confirmed', 'Completed', 'Cancelled', 'No Show'])) {
        $stmtUpd = $db->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
        $stmtUpd->execute([$newStatus, $apptId, $doctorId]);

        // Notify Patient
        $stmtPatUser = $db->prepare("SELECT p.user_id, a.appointment_number FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?");
        $stmtPatUser->execute([$apptId]);
        $apptData = $stmtPatUser->fetch();
        if ($apptData) {
            createNotification($apptData['user_id'], 'Appointment Status Updated', "Your appointment {$apptData['appointment_number']} status is now: {$newStatus}.", 'info', $apptId);
        }

        logAudit($user['id'], "Updated Status to {$newStatus}", 'Appointments', $apptId);
        setFlashMessage('success', "Appointment status updated to {$newStatus}.");
        header('Location: appointments.php');
        exit();
    }
}

// Fetch Appointments List
$stmtAppts = $db->prepare("
    SELECT a.*, u.name as patient_name, u.phone, p.patient_id as patient_code, p.gender, p.blood_group
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$stmtAppts->execute([$doctorId]);
$appointments = $stmtAppts->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <h3 class="fw-bold mb-4">Patient Appointment Requests</h3>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt Number</th>
                                <th>Patient Name</th>
                                <th>Date & Time</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments)): foreach ($appointments as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['appointment_number']) ?></td>
                                    <td><?= sanitize($row['patient_name']) ?> <br><small class="text-muted"><?= sanitize($row['patient_code']) ?></small></td>
                                    <td><?= formatDate($row['appointment_date']) ?><br><small class="text-muted"><?= formatTime($row['appointment_time']) ?></small></td>
                                    <td><?= sanitize($row['reason'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Confirmed' ? 'success' : ($row['status'] == 'Completed' ? 'info' : ($row['status'] == 'Cancelled' ? 'danger' : 'warning')) ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Manage</button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="medical-records.php?action=create&appointment_id=<?= $row['id'] ?>&patient_id=<?= $row['patient_id'] ?>">
                                                        <i class="bi bi-file-earmark-medical me-2 text-primary"></i>Create EMR Record
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="prescriptions.php?action=create&appointment_id=<?= $row['id'] ?>&patient_id=<?= $row['patient_id'] ?>">
                                                        <i class="bi bi-capsule me-2 text-success"></i>Create Prescription
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="lab-requests.php?action=create&appointment_id=<?= $row['id'] ?>&patient_id=<?= $row['patient_id'] ?>">
                                                        <i class="bi bi-virus me-2 text-warning"></i>Order Lab Test
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="appointments.php" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" name="status" value="Confirmed" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>Mark Confirmed</button>
                                                        <button type="submit" name="status" value="Completed" class="dropdown-item text-info"><i class="bi bi-check2-all me-2"></i>Mark Completed</button>
                                                        <button type="submit" name="status" value="Cancelled" class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>Mark Cancelled</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No appointments found.</td>
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