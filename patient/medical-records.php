<?php
/**
 * Patient Timeline Medical Records Interface
 */
$pageTitle = "My Medical Records";
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

// Fetch Patient Records in Timeline Chronological Sequence
$stmtRecords = $db->prepare("
    SELECT mr.*, u.name as doctor_name, d.specialization, dept.name as dept_name
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");
$stmtRecords->execute([$patientId]);
$records = $stmtRecords->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Electronic Medical History & Clinical EMR</h3>

        <div class="row">
            <div class="col-md-10 col-lg-8">
                <?php if (!empty($records)): ?>
                    <ul class="timeline">
                        <?php foreach ($records as $rec): ?>
                            <li class="timeline-item">
                                <div class="card border-0 shadow-sm rounded-4 mb-3">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <h5 class="fw-bold mb-0 text-primary"><?= sanitize($rec['doctor_name']) ?></h5>
                                            <small class="text-muted"><?= sanitize($rec['specialization']) ?> (<?= sanitize($rec['dept_name']) ?>)</small>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= formatDate($rec['created_at']) ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3 bg-light p-2 rounded">
                                            <div class="col-4"><strong>Blood Pressure:</strong> <?= sanitize($rec['blood_pressure'] ?: 'N/A') ?></div>
                                            <div class="col-4"><strong>Temperature:</strong> <?= sanitize($rec['temperature'] ?: 'N/A') ?></div>
                                            <div class="col-4"><strong>Weight:</strong> <?= sanitize($rec['weight'] ?: 'N/A') ?></div>
                                        </div>
                                        
                                        <h6 class="fw-bold text-secondary">Symptoms:</h6>
                                        <p class="text-muted"><?= nl2br(sanitize($rec['symptoms'])) ?></p>

                                        <h6 class="fw-bold text-secondary">Clinical Diagnosis:</h6>
                                        <p class="fw-semibold text-dark"><?= nl2br(sanitize($rec['diagnosis'])) ?></p>

                                        <h6 class="fw-bold text-secondary">Prescribed Treatment Plan:</h6>
                                        <p class="text-muted"><?= nl2br(sanitize($rec['treatment'])) ?></p>

                                        <?php if (!empty($rec['notes'])): ?>
                                            <h6 class="fw-bold text-secondary">Additional Notes:</h6>
                                            <p class="text-muted small"><?= nl2br(sanitize($rec['notes'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info py-4 text-center">No clinical EMR medical records available on file.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>