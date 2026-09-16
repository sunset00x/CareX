<?php
/**
 * Admin - Global Appointment Management & Status Control
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Manage Appointments";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// Update Status Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $apptId    = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $token     = $_POST['csrf_token'] ?? '';

    if (verifyCSRFToken($token) && in_array($newStatus, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'])) {
        $stmtUpd = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmtUpd->execute([$newStatus, $apptId]);

        logAudit($_SESSION['user_id'], "Admin Updated Appointment #{$apptId} Status to {$newStatus}", 'Appointments', $apptId);
        setFlashMessage('success', "Appointment status updated to '{$newStatus}'.");
        header('Location: appointments.php');
        exit();
    }
}

// Search & Filter Parameters
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
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">Master Appointments Directory</h3>
                <p class="text-muted mb-0">Review and modify patient bookings across all departments.</p>
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

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt Number</th>
                                <th>Patient</th>
                                <th>Doctor / Dept</th>
                                <th>Date & Time</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments)): foreach ($appointments as $a): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($a['appointment_number']) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($a['patient_name']) ?></div>
                                        <small class="text-muted"><?= sanitize($a['patient_code']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">Dr. <?= sanitize($a['doctor_name']) ?></div>
                                        <small class="text-muted"><?= sanitize($a['dept_name']) ?></small>
                                    </td>
                                    <td><?= formatDate($a['appointment_date']) ?><br><small class="text-muted"><?= formatTime($a['appointment_time']) ?></small></td>
                                    <td><?= sanitize($a['reason'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $a['status'] === 'Confirmed' ? 'success' : ($a['status'] === 'Completed' ? 'info' : ($a['status'] === 'Cancelled' ? 'danger' : 'warning')) ?>">
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
                                <tr><td colspan="7" class="text-center py-4 text-muted">No appointments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>