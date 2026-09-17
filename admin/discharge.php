<?php
/**
 * Admin / Doctor - Comprehensive Patient Discharge Engine
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Patient Discharge Summarizer";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'doctor']);

$db = Database::getConnection();
$error = '';

// Fetch System Settings for Dynamic Branding
$sysSettings = $db->query("SELECT * FROM system_settings WHERE id = 1")->fetch() ?: [];
$hospName    = $sysSettings['hospital_name'] ?? 'CarePlus Smart Hospital';
$hospPhone   = $sysSettings['emergency_phone'] ?? '+977-9800000000';
$hospAddress = $sysSettings['address'] ?? 'Biratnagar-4, Koshi Province, Nepal';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $patId         = (int)($_POST['patient_id'] ?? 0);
    $bedId         = (int)($_POST['bed_id'] ?? 0);
    $docId         = (int)($_POST['doctor_id'] ?? 0);
    $admDate       = $_POST['admission_date'] ?? date('Y-m-d H:i');
    $disDate       = $_POST['discharge_date'] ?? date('Y-m-d H:i');
    $admitDiag     = sanitize($_POST['admitting_diagnosis'] ?? '');
    $finalDiag     = sanitize($_POST['final_diagnosis'] ?? '');
    $procedures    = sanitize($_POST['procedures_performed'] ?? '');
    $treatment     = sanitize($_POST['treatment_summary'] ?? '');
    $meds          = sanitize($_POST['discharge_medications'] ?? '');
    $restrictions  = sanitize($_POST['diet_activity_restrictions'] ?? '');
    $redFlags      = sanitize($_POST['emergency_red_flags'] ?? '');
    $followUp      = sanitize($_POST['follow_up_instructions'] ?? '');

    if ($patId > 0 && !empty($finalDiag)) {
        try {
            $db->beginTransaction();

            $stmtIns = $db->prepare("
                INSERT INTO discharge_summaries 
                (patient_id, bed_id, attending_doctor_id, admission_date, discharge_date, final_diagnosis, treatment_summary, discharge_medications, follow_up_instructions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([
                $patId, 
                $bedId ?: NULL, 
                $docId, 
                $admDate, 
                $disDate, 
                "Admitting: {$admitDiag} | Final: {$finalDiag} | Procedures: {$procedures}", 
                "Course: {$treatment} | Restrictions: {$restrictions}", 
                $meds, 
                "Follow-Up: {$followUp} | Red Flags: {$redFlags}"
            ]);

            // Release IPD Bed if assigned
            if ($bedId > 0) {
                $stmtBed = $db->prepare("UPDATE hospital_beds SET status = 'Cleaning', assigned_patient_id = NULL WHERE id = ?");
                $stmtBed->execute([$bedId]);
            }

            $db->commit();
            setFlashMessage('success', "Discharge summary generated and bed released for sanitization.");
            header('Location: discharge.php');
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Discharge Processing Error: " . $e->getMessage();
        }
    } else {
        $error = "Please select a patient and fill in the final diagnosis.";
    }
}

$patients  = $db->query("SELECT p.id, u.name, p.dob, p.gender, p.blood_group FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$doctors   = $db->query("SELECT d.id, u.name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$beds      = $db->query("SELECT id, bed_number, ward_type FROM hospital_beds WHERE status = 'Occupied'")->fetchAll();
$summaries = $db->query("
    SELECT ds.*, u_p.name as patient_name, u_d.name as doctor_name 
    FROM discharge_summaries ds 
    JOIN patients p ON ds.patient_id = p.id JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON ds.attending_doctor_id = d.id JOIN users u_d ON d.user_id = u_d.id
    ORDER BY ds.created_at DESC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Comprehensive Patient Discharge Engine</h2>
                <p class="text-muted mb-0">Generate medical discharge summaries with physical signature lines and auto-released bed inventory.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addDischargeModal">
                <i class="bi bi-file-earmark-medical me-1"></i> Issue Discharge Summary
            </button>
        </div>

        <!-- Discharge Records Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient Name</th>
                                <th>Attending Physician</th>
                                <th>Admission / Discharge</th>
                                <th>Diagnosis Summary</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($summaries): foreach ($summaries as $s): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= sanitize($s['patient_name']) ?></td>
                                    <td>Dr. <?= sanitize($s['doctor_name']) ?></td>
                                    <td><small><?= formatDate($s['admission_date']) ?> &rarr; <?= formatDate($s['discharge_date']) ?></small></td>
                                    <td><small><?= sanitize($s['final_diagnosis']) ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary fw-bold rounded-pill" 
                                                onclick='openPrintModal(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-printer me-1"></i> Print Official Document
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No discharge summaries recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Comprehensive Discharge Letter Builder -->
<div class="modal fade" id="addDischargeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-check text-primary me-2"></i>Generate Discharge Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="discharge.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <!-- Patient & Doctor Selection -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select Patient...</option>
                                <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?> (<?= $p['gender'] ?>, <?= $p['blood_group'] ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Attending Doctor *</label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Select Doctor...</option>
                                <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>">Dr. <?= sanitize($d['name']) ?> (<?= $d['specialization'] ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- IPD Ward & Dates -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Occupied IPD Bed</label>
                            <select name="bed_id" class="form-select">
                                <option value="">Outpatient / None</option>
                                <?php foreach ($beds as $b): ?><option value="<?= $b['id'] ?>"><?= sanitize($b['bed_number']) ?> (<?= $b['ward_type'] ?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Admission Date & Time</label>
                            <input type="datetime-local" name="admission_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Discharge Date & Time</label>
                            <input type="datetime-local" name="discharge_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>

                    <!-- Diagnoses -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admitting Diagnosis *</label>
                            <input type="text" name="admitting_diagnosis" class="form-control" placeholder="e.g. Acute Abdominal Pain" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Final / Confirmed Diagnosis *</label>
                            <input type="text" name="final_diagnosis" class="form-control" placeholder="e.g. Acute Appendicitis" required>
                        </div>
                    </div>

                    <!-- Procedures & Hospital Course -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Procedures & Operations Performed</label>
                        <input type="text" name="procedures_performed" class="form-control" placeholder="e.g. Laparoscopic Appendectomy on 2026-09-15">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hospital Course & Treatment Summary</label>
                        <textarea name="treatment_summary" class="form-control" rows="2" placeholder="Summary of clinical course, IV medications, and recovery..."></textarea>
                    </div>

                    <!-- Discharge Medications -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Discharge Medications (Rx) *</label>
                        <textarea name="discharge_medications" class="form-control" rows="2" placeholder="e.g. Tab. Ciprofloxacin 500mg BD (5 days)&#10;Tab. Paracetamol 500mg TDS PRN" required></textarea>
                    </div>

                    <!-- Post-Care Restrictions & Red Flags -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Dietary & Activity Restrictions</label>
                            <input type="text" name="diet_activity_restrictions" class="form-control" placeholder="e.g. Light soft diet; avoid strenuous lifting for 2 weeks">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Red Flags (Seek ER if)</label>
                            <input type="text" name="emergency_red_flags" class="form-control" placeholder="e.g. Fever > 101°F, persistent vomiting, wound bleeding">
                        </div>
                    </div>

                    <!-- Follow Up -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Follow-Up Instructions</label>
                        <input type="text" name="follow_up_instructions" class="form-control" placeholder="e.g. OPD review with Dr. Sharma after 7 days for suture removal">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Generate & Release Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Printable Official Summary View -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-printer text-primary me-2"></i>Official Medical Discharge Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-5" id="printableArea">
                
                <!-- Dynamic Hospital Branding Header -->
                <div class="text-center mb-4 pb-3 border-bottom border-2 border-primary">
                    <h2 class="fw-bold text-primary mb-1"><?= sanitize($hospName) ?></h2>
                    <p class="text-muted small mb-0"><?= sanitize($hospAddress) ?> | Emergency Line: <?= sanitize($hospPhone) ?></p>
                    <span class="badge bg-primary text-white text-uppercase px-3 py-1 mt-2">Official Medical Discharge Summary</span>
                </div>

                <!-- Patient & Admission Info Grid -->
                <div class="row g-3 mb-4 p-3 bg-light rounded border">
                    <div class="col-6"><strong>Patient Name:</strong> <span id="print_patient_name" class="text-dark fw-bold"></span></div>
                    <div class="col-6 text-end"><strong>Attending Doctor:</strong> Dr. <span id="print_doctor_name" class="text-dark fw-bold"></span></div>
                    <div class="col-6"><strong>Admission:</strong> <span id="print_admission_date"></span></div>
                    <div class="col-6 text-end"><strong>Discharge:</strong> <span id="print_discharge_date"></span></div>
                </div>

                <!-- Clinical Content -->
                <div class="mb-3">
                    <strong class="d-block text-uppercase text-primary small">Clinical Diagnoses & Procedures</strong>
                    <div id="print_final_diagnosis" class="p-2 bg-light rounded border text-dark"></div>
                </div>

                <div class="mb-3">
                    <strong class="d-block text-uppercase text-primary small">Hospital Treatment & Course Summary</strong>
                    <div id="print_treatment_summary" class="p-2 bg-light rounded border text-dark"></div>
                </div>

                <div class="mb-3">
                    <strong class="d-block text-uppercase text-primary small">Discharge Medications (Rx)</strong>
                    <div id="print_discharge_medications" class="p-2 bg-light rounded border text-dark fw-bold"></div>
                </div>

                <div class="mb-4">
                    <strong class="d-block text-uppercase text-primary small">Follow-Up & Emergency Red Flags</strong>
                    <div id="print_follow_up" class="p-2 bg-light rounded border text-dark"></div>
                </div>

                <!-- Physical Signature Lines -->
                <div class="row mt-5 pt-4">
                    <div class="col-6 text-center">
                        <div class="border-top border-dark w-75 mx-auto pt-2">
                            <strong class="d-block">Patient / Caregiver Signature</strong>
                            <small class="text-muted">Acknowledged Instructions</small>
                        </div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="border-top border-dark w-75 mx-auto pt-2">
                            <strong class="d-block">Attending Physician Signature</strong>
                            <small class="text-muted">Stamp & Authorization</small>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="triggerPrint()">
                    <i class="bi bi-printer me-1"></i> Print Official Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openPrintModal(data) {
    document.getElementById('print_patient_name').innerText = data.patient_name || 'N/A';
    document.getElementById('print_doctor_name').innerText = data.doctor_name || 'N/A';
    document.getElementById('print_admission_date').innerText = data.admission_date || 'N/A';
    document.getElementById('print_discharge_date').innerText = data.discharge_date || 'N/A';
    document.getElementById('print_final_diagnosis').innerText = data.final_diagnosis || 'N/A';
    document.getElementById('print_treatment_summary').innerText = data.treatment_summary || 'None recorded';
    document.getElementById('print_discharge_medications').innerText = data.discharge_medications || 'None prescribed';
    document.getElementById('print_follow_up').innerText = data.follow_up_instructions || 'None provided';

    var printModal = new bootstrap.Modal(document.getElementById('printModal'));
    printModal.show();
}

function triggerPrint() {
    var printContents = document.getElementById('printableArea').innerHTML;
    var originalContents = document.body.innerHTML;

    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>