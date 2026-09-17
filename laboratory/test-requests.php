<?php
/**
 * Laboratory Worklist & Diagnostic LIS Processing Engine
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Diagnostic Lab Worklist Console";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'laboratory']);

$db = Database::getConnection();
$error = '';

// ACTION HANDLER: REGISTER SPECIMEN, UPDATE STATUS, LOG RESULTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = sanitize($_POST['action'] ?? '');

    // 1. REGISTER NEW SPECIMEN
    if ($action === 'register_specimen') {
        $patientId  = (int)($_POST['patient_id'] ?? 0);
        $doctorId   = (int)($_POST['doctor_id'] ?? 0);
        $category   = sanitize($_POST['test_category'] ?? 'Hematology');
        $testName   = sanitize($_POST['test_name'] ?? '');
        $sampleType = sanitize($_POST['sample_type'] ?? 'Whole Blood');
        $urgency    = sanitize($_POST['urgency'] ?? 'Routine');
        
        // Auto-generate barcode string
        $barcode = 'LAB-' . date('Ymd') . '-' . rand(1000, 9999);

        if ($patientId > 0 && $doctorId > 0 && !empty($testName)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO lab_test_orders 
                    (sample_barcode, patient_id, doctor_id, test_category, test_name, sample_type, status, urgency) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Requested', ?)
                ");
                $stmt->execute([$barcode, $patientId, $doctorId, $category, $testName, $sampleType, $urgency]);

                setFlashMessage('success', "Specimen registered successfully with barcode {$barcode}.");
                header('Location: test-requests.php');
                exit();
            } catch (\Exception $e) {
                $error = "Registration Error: " . $e->getMessage();
            }
        } else {
            $error = "Please select a patient, doctor, and specify the test name.";
        }
    } 
    // 2. SUBMIT RESULTS
    elseif ($action === 'submit_results') {
        $orderId    = (int)($_POST['order_id'] ?? 0);
        $resultVal  = sanitize($_POST['result_value'] ?? '');
        $refRange   = sanitize($_POST['reference_range'] ?? '');
        $isCritical = isset($_POST['is_critical']) ? 1 : 0;
        $techNotes  = sanitize($_POST['technician_notes'] ?? '');

        if ($orderId > 0 && !empty($resultVal)) {
            try {
                $stmt = $db->prepare("
                    UPDATE lab_test_orders 
                    SET result_value = ?, 
                        reference_range = ?, 
                        is_critical = ?, 
                        technician_notes = ?, 
                        status = 'Completed', 
                        verified_by_user_id = ?, 
                        completed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$resultVal, $refRange, $isCritical, $techNotes, $_SESSION['user_id'], $orderId]);
                setFlashMessage('success', "Diagnostic results verified and published to Patient EMR.");
                header('Location: test-requests.php');
                exit();
            } catch (\Exception $e) {
                $error = "Result Logging Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Active Worklist
$worklist = $db->query("
    SELECT lto.*, u_p.name as patient_name, u_d.name as doctor_name
    FROM lab_test_orders lto
    JOIN patients p ON lto.patient_id = p.id JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON lto.doctor_id = d.id JOIN users u_d ON d.user_id = u_d.id
    ORDER BY CASE WHEN lto.urgency = 'STAT Emergency' THEN 1 WHEN lto.urgency = 'Urgent' THEN 2 ELSE 3 END, lto.created_at DESC
")->fetchAll();

$patients = $db->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$doctors  = $db->query("SELECT d.id, u.name FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Laboratory Information System (LIS)</h2>
                <p class="text-muted mb-0">Specimen tracking, critical result flagging, and technician verification workflow.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                <i class="bi bi-plus-lg me-1"></i> Register New Specimen
            </button>
        </div>

        <!-- Worklist Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Barcode / Sample</th>
                                <th>Patient</th>
                                <th>Test & Category</th>
                                <th>Ordering Physician</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($worklist): foreach ($worklist as $lab): ?>
                                <tr class="<?= $lab['is_critical'] ? 'table-danger' : '' ?>">
                                    <td>
                                        <code><?= sanitize($lab['sample_barcode']) ?></code>
                                        <div class="small text-muted"><?= sanitize($lab['sample_type']) ?></div>
                                    </td>
                                    <td class="fw-bold text-dark"><?= sanitize($lab['patient_name']) ?></td>
                                    <td>
                                        <span class="fw-semibold text-primary"><?= sanitize($lab['test_name']) ?></span>
                                        <div class="small text-muted"><?= sanitize($lab['test_category']) ?></div>
                                    </td>
                                    <td>Dr. <?= sanitize($lab['doctor_name']) ?></td>
                                    <td>
                                        <?php if ($lab['urgency'] === 'STAT Emergency'): ?>
                                            <span class="badge bg-danger text-uppercase px-2 py-1"><i class="bi bi-lightning-fill me-1"></i>STAT Emergency</span>
                                        <?php elseif ($lab['urgency'] === 'Urgent'): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">Urgent</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-2 py-1">Routine</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $lab['status'] === 'Completed' ? 'success' : ($lab['status'] === 'In Testing' ? 'info text-dark' : 'primary') ?>">
                                            <?= $lab['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($lab['status'] !== 'Completed'): ?>
                                            <button class="btn btn-sm btn-outline-primary fw-bold rounded-pill me-1" 
                                                    onclick='openResultModal(<?= json_encode($lab, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                <i class="bi bi-pencil-square me-1"></i> Log Results
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success fw-bold rounded-pill" onclick="alert('Results verified on <?= $lab['completed_at'] ?>')">
                                                <i class="bi bi-check-circle me-1"></i> Verified
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No diagnostic specimens in current worklist queue.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Register New Specimen -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Register New Specimen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="test-requests.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="register_specimen">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select Patient...</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ordering Doctor *</label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Select Physician...</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>">Dr. <?= sanitize($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Test Category *</label>
                            <select name="test_category" class="form-select" required>
                                <option value="Hematology">Hematology</option>
                                <option value="Biochemistry">Biochemistry</option>
                                <option value="Microbiology">Microbiology</option>
                                <option value="Immunology">Immunology</option>
                                <option value="Pathology">Pathology</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Test Name / Panel *</label>
                            <input type="text" name="test_name" class="form-control" placeholder="e.g. Complete Blood Count (CBC), Lipid Profile" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specimen / Sample Type</label>
                            <select name="sample_type" class="form-select">
                                <option value="Whole Blood">Whole Blood (EDTA)</option>
                                <option value="Serum">Serum</option>
                                <option value="Urine">Urine</option>
                                <option value="Swab / Culture">Swab / Culture</option>
                                <option value="Tissue Biopsy">Tissue Biopsy</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority Level</label>
                            <select name="urgency" class="form-select">
                                <option value="Routine">Routine</option>
                                <option value="Urgent">Urgent</option>
                                <option value="STAT Emergency">STAT Emergency</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Register & Generate Barcode</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Log & Verify Lab Results -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-activity text-primary me-2"></i>Verify Diagnostic Test Findings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="test-requests.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="submit_results">
                    <input type="hidden" name="order_id" id="modal_order_id">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Patient</label>
                            <input type="text" id="modal_patient_name" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Test Name</label>
                            <input type="text" id="modal_test_name" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observed Result Value *</label>
                        <textarea name="result_value" class="form-control" rows="2" placeholder="e.g. Hemoglobin: 14.2 g/dL | WBC: 7,500 /mcL" required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Standard Reference Range</label>
                            <input type="text" name="reference_range" class="form-control" placeholder="e.g. 13.5 - 17.5 g/dL">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_critical" id="is_critical">
                                <label class="form-check-label fw-bold text-danger" for="is_critical">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Flag as Panic/Critical Value
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lab Technician Findings & Notes</label>
                        <textarea name="technician_notes" class="form-control" rows="2" placeholder="Notes on sample quality or equipment calibration..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Verify & Publish Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResultModal(data) {
    document.getElementById('modal_order_id').value = data.id;
    document.getElementById('modal_patient_name').value = data.patient_name;
    document.getElementById('modal_test_name').value = data.test_name;
    var resModal = new bootstrap.Modal(document.getElementById('resultModal'));
    resModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>