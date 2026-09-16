<?php
/**
 * Doctor - Electronic Medical Records (EMR) Creation & Review
 */
$pageTitle = "Medical Records";
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

// Create Medical Record Submission Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $apptId    = (int)($_POST['appointment_id'] ?? 0) ?: null;
    $symptoms  = sanitize($_POST['symptoms'] ?? '');
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $treatment = sanitize($_POST['treatment'] ?? '');
    $notes     = sanitize($_POST['notes'] ?? '');
    $bp        = sanitize($_POST['blood_pressure'] ?? '');
    $temp      = sanitize($_POST['temperature'] ?? '');
    $weight    = sanitize($_POST['weight'] ?? '');
    $token     = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification Token Failed.";
    } elseif (!$patientId || empty($symptoms) || empty($diagnosis) || empty($treatment)) {
        $error = "Please fill in symptoms, diagnosis, and treatment plan.";
    } else {
        try {
            $stmtIns = $db->prepare("
                INSERT INTO medical_records (patient_id, doctor_id, appointment_id, symptoms, diagnosis, treatment, notes, blood_pressure, temperature, weight) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$patientId, $doctorId, $apptId, $symptoms, $diagnosis, $treatment, $notes, $bp, $temp, $weight]);
            $recId = $db->lastInsertId();

            if ($apptId) {
                $db->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?")->execute([$apptId]);
            }

            // Notify Patient
            $stmtPatUser = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
            $stmtPatUser->execute([$patientId]);
            $patUserId = $stmtPatUser->fetchColumn();
            createNotification($patUserId, 'New Clinical EMR Entry', "Dr. {$user['name']} uploaded a new medical record.", 'info', $recId);

            logAudit($user['id'], 'Created Medical Record', 'EMR', $recId);
            setFlashMessage('success', 'Medical record added successfully!');
            header('Location: medical-records.php');
            exit();
        } catch (\Exception $e) {
            $error = "Record creation error: " . $e->getMessage();
        }
    }
}

// Filter Patient List
$filterPatientId = (int)($_GET['patient_id'] ?? 0);
$sql = "
    SELECT mr.*, u.name as patient_name, p.patient_id as patient_code
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE mr.doctor_id = ?
";
$params = [$doctorId];

if ($filterPatientId > 0) {
    $sql .= " AND mr.patient_id = ?";
    $params[] = $filterPatientId;
}
$sql .= " ORDER BY mr.created_at DESC";

$stmtRecs = $db->prepare($sql);
$stmtRecs->execute($params);
$records = $stmtRecs->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <?php if ($action === 'create'): ?>
            <div class="row justify-content-center">
                <div class="col-md-9">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3">
                            <h4 class="fw-bold mb-0">Create Patient Clinical EMR Record</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                            <form action="medical-records.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int)($_GET['appointment_id'] ?? 0) ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Select Patient *</label>
                                    <select name="patient_id" class="form-select" required>
                                        <?php
                                        $patients = $db->query("SELECT p.id, u.name, p.patient_id FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
                                        foreach ($patients as $p):
                                            $selected = ((int)($_GET['patient_id'] ?? 0) == $p['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $p['id'] ?>" <?= $selected ?>><?= sanitize($p['name']) ?> (<?= sanitize($p['patient_id']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Blood Pressure</label>
                                        <input type="text" name="blood_pressure" class="form-control" placeholder="120/80 mmHg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Temperature</label>
                                        <input type="text" name="temperature" class="form-control" placeholder="98.6 F">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Weight</label>
                                        <input type="text" name="weight" class="form-control" placeholder="70 kg">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Chief Symptoms *</label>
                                    <textarea name="symptoms" class="form-control" rows="2" required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Diagnosis *</label>
                                    <textarea name="diagnosis" class="form-control" rows="2" required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Treatment Plan *</label>
                                    <textarea name="treatment" class="form-control" rows="3" required></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Additional Doctor Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Save EMR Clinical Record</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Clinical EMR Records</h3>
                <a href="medical-records.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-circle me-1"></i> Create Record</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Diagnosis</th>
                                    <th>Treatment</th>
                                    <th>Vitals (BP/Temp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($records)): foreach ($records as $r): ?>
                                    <tr>
                                        <td><?= formatDate($r['created_at']) ?></td>
                                        <td class="fw-bold"><?= sanitize($r['patient_name']) ?> <br><small class="text-muted"><?= sanitize($r['patient_code']) ?></small></td>
                                        <td class="fw-semibold text-primary"><?= sanitize($r['diagnosis']) ?></td>
                                        <td><?= sanitize($r['treatment']) ?></td>
                                        <td><small>BP: <?= sanitize($r['blood_pressure'] ?: 'N/A') ?><br>Temp: <?= sanitize($r['temperature'] ?: 'N/A') ?></small></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No medical records created.</td>
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