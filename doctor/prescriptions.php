<?php
/**
 * Doctor - Dynamic Prescription Builder Engine
 */
$pageTitle = "Prescriptions";
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

// Prescription Save Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId    = (int)($_POST['patient_id'] ?? 0);
    $apptId       = (int)($_POST['appointment_id'] ?? 0) ?: null;
    $instructions = sanitize($_POST['instructions'] ?? '');
    $medicines    = $_POST['medicines'] ?? [];
    $token        = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification Token Failed.";
    } elseif (!$patientId || empty($medicines)) {
        $error = "Please select a patient and add at least one medicine item.";
    } else {
        try {
            $db->beginTransaction();

            $rxNum = generatePrescriptionNumber();
            $stmtHeader = $db->prepare("INSERT INTO prescriptions (prescription_number, patient_id, doctor_id, appointment_id, instructions) VALUES (?, ?, ?, ?, ?)");
            $stmtHeader->execute([$rxNum, $patientId, $doctorId, $apptId, $instructions]);
            $rxId = $db->lastInsertId();

            $stmtItem = $db->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($medicines as $med) {
                if (!empty($med['name'])) {
                    $stmtItem->execute([
                        $rxId,
                        sanitize($med['name']),
                        sanitize($med['dosage']),
                        sanitize($med['frequency']),
                        sanitize($med['duration']),
                        sanitize($med['notes'] ?? '')
                    ]);
                }
            }

            $db->commit();

            // Notify Patient
            $stmtPatUser = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
            $stmtPatUser->execute([$patientId]);
            $patUserId = $stmtPatUser->fetchColumn();
            createNotification($patUserId, 'Prescription Generated', "Rx {$rxNum} issued by Dr. {$user['name']}.", 'success', $rxId);

            logAudit($user['id'], 'Created Prescription', 'Prescriptions', $rxId);
            setFlashMessage('success', "Prescription {$rxNum} issued successfully!");
            header('Location: prescriptions.php');
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Prescription error: " . $e->getMessage();
        }
    }
}

// Fetch Prescriptions List
$stmtRxList = $db->prepare("
    SELECT pr.*, u.name as patient_name, p.patient_id as patient_code
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE pr.doctor_id = ?
    ORDER BY pr.created_at DESC
");
$stmtRxList->execute([$doctorId]);
$prescriptions = $stmtRxList->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <?php if ($action === 'create'): ?>
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3">
                            <h4 class="fw-bold mb-0">Create Digital Prescription</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                            <form action="prescriptions.php" method="POST" id="rxForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int)($_GET['appointment_id'] ?? 0) ?>">

                                <div class="mb-4">
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

                                <h5 class="fw-bold text-primary mb-3">Prescribed Medications</h5>
                                <div id="medContainer">
                                    <div class="row g-2 mb-2 med-row">
                                        <div class="col-md-3"><input type="text" name="medicines[0][name]" class="form-control" placeholder="Medicine Name (e.g. Paracetamol)" required></div>
                                        <div class="col-md-2"><input type="text" name="medicines[0][dosage]" class="form-control" placeholder="Dosage (500mg)" required></div>
                                        <div class="col-md-3"><input type="text" name="medicines[0][frequency]" class="form-control" placeholder="Frequency (3x daily)" required></div>
                                        <div class="col-md-2"><input type="text" name="medicines[0][duration]" class="form-control" placeholder="Duration (5 days)" required></div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row" disabled>Remove</button></div>
                                    </div>
                                </div>

                                <button type="button" id="addMedBtn" class="btn btn-sm btn-outline-secondary mb-4"><i class="bi bi-plus-lg me-1"></i> Add Another Medicine Item</button>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">General Prescription Instructions</label>
                                    <textarea name="instructions" class="form-control" rows="2" placeholder="Take after meals, avoid alcohol..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">Issue Digital Prescription</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                let medIdx = 1;
                const container = document.getElementById('medContainer');
                document.getElementById('addMedBtn').addEventListener('click', function() {
                    const row = document.createElement('div');
                    row.className = 'row g-2 mb-2 med-row';
                    row.innerHTML = `
                        <div class="col-md-3"><input type="text" name="medicines[${medIdx}][name]" class="form-control" placeholder="Medicine Name" required></div>
                        <div class="col-md-2"><input type="text" name="medicines[${medIdx}][dosage]" class="form-control" placeholder="Dosage" required></div>
                        <div class="col-md-3"><input type="text" name="medicines[${medIdx}][frequency]" class="form-control" placeholder="Frequency" required></div>
                        <div class="col-md-2"><input type="text" name="medicines[${medIdx}][duration]" class="form-control" placeholder="Duration" required></div>
                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row">Remove</button></div>
                    `;
                    container.appendChild(row);
                    medIdx++;
                });

                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-row') && document.querySelectorAll('.med-row').length > 1) {
                        e.target.closest('.med-row').remove();
                    }
                });
            });
            </script>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Issued Prescriptions</h3>
                <a href="prescriptions.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-circle me-1"></i> Issue Rx</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rx Number</th>
                                    <th>Patient</th>
                                    <th>Date Issued</th>
                                    <th>Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($prescriptions)): foreach ($prescriptions as $rx): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= sanitize($rx['prescription_number']) ?></td>
                                        <td class="fw-semibold"><?= sanitize($rx['patient_name']) ?> <small class="text-muted">(<?= sanitize($rx['patient_code']) ?>)</small></td>
                                        <td><?= formatDate($rx['created_at']) ?></td>
                                        <td><?= sanitize($rx['instructions'] ?: 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No prescriptions issued.</td>
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