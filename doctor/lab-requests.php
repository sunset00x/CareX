<?php
/**
 * Doctor - Order Laboratory Examinations
 */
$pageTitle = "Lab Orders";
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

$action = sanitize($_GET['action'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId   = (int)($_POST['patient_id'] ?? 0);
    $apptId      = (int)($_POST['appointment_id'] ?? 0) ?: null;
    $testName    = sanitize($_POST['test_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $priority    = sanitize($_POST['priority'] ?? 'Normal');
    $token       = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification Token Failed.";
    } elseif (!$patientId || empty($testName)) {
        $error = "Please select a patient and enter test title.";
    } else {
        try {
            $labNum = generateLabTestNumber();
            $stmtIns = $db->prepare("INSERT INTO lab_tests (test_number, patient_id, doctor_id, appointment_id, test_name, description, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmtIns->execute([$labNum, $patientId, $doctorId, $apptId, $testName, $description, $priority]);
            $labId = $db->lastInsertId();

            logAudit($user['id'], 'Ordered Lab Test', 'Laboratory', $labId);
            setFlashMessage('success', "Lab test order {$labNum} submitted successfully!");
            header('Location: lab-requests.php');
            exit();
        } catch (\Exception $e) {
            $error = "Lab order creation error: " . $e->getMessage();
        }
    }
}

// Fetch Orders
$stmtOrders = $db->prepare("
    SELECT lt.*, u.name as patient_name, p.patient_id as patient_code
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE lt.doctor_id = ?
    ORDER BY lt.requested_at DESC
");
$stmtOrders->execute([$doctorId]);
$orders = $stmtOrders->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <?php if ($action === 'create'): ?>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3">
                            <h4 class="fw-bold mb-0">Order Laboratory Diagnostics</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                            <form action="lab-requests.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int)($_GET['appointment_id'] ?? 0) ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Select Patient *</label>
                                    <select name="patient_id" class="form-select form-select-lg" required>
                                        <?php
                                        $patients = $db->query("SELECT p.id, u.name, p.patient_id FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
                                        foreach ($patients as $p):
                                            $selected = ((int)($_GET['patient_id'] ?? 0) == $p['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $p['id'] ?>" <?= $selected ?>><?= sanitize($p['name']) ?> (<?= sanitize($p['patient_id']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Test Name / Panel *</label>
                                    <input type="text" name="test_name" class="form-control" placeholder="e.g. Complete Blood Count (CBC), Lipid Profile" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Priority Level *</label>
                                    <select name="priority" class="form-select" required>
                                        <option value="Normal">Normal</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent STAT</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Clinical Diagnostic Notes / Instructions</label>
                                    <textarea name="description" class="form-control" rows="3"></textarea>
                                </div>

                                <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Submit Lab Request Order</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Requested Laboratory Orders</h3>
                <a href="lab-requests.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-circle me-1"></i> Order Lab Test</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Patient</th>
                                    <th>Test Panel</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders)): foreach ($orders as $lab): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= sanitize($lab['test_number']) ?></td>
                                        <td class="fw-semibold"><?= sanitize($lab['patient_name']) ?> <small class="text-muted">(<?= sanitize($lab['patient_code']) ?>)</small></td>
                                        <td><?= sanitize($lab['test_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $lab['priority'] == 'Urgent' ? 'danger' : ($lab['priority'] == 'High' ? 'warning' : 'secondary') ?>">
                                                <?= sanitize($lab['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $lab['status'] == 'Completed' ? 'success' : 'info' ?>">
                                                <?= sanitize($lab['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No lab orders placed.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>