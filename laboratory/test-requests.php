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

// ACTION HANDLER: UPDATE TEST STATUS & LOG RESULTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = sanitize($_POST['action'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($orderId > 0) {
        if ($action === 'update_status') {
            $newStatus = sanitize($_POST['status'] ?? 'In Testing');
            $stmt = $db->prepare("UPDATE lab_test_orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            setFlashMessage('success', "Sample status updated to {$newStatus}.");
        } elseif ($action === 'submit_results') {
            $resultVal = sanitize($_POST['result_value'] ?? '');
            $refRange  = sanitize($_POST['reference_range'] ?? '');
            $isCritical = isset($_POST['is_critical']) ? 1 : 0;
            $techNotes  = sanitize($_POST['technician_notes'] ?? '');

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
        }
        header('Location: test-requests.php');
        exit();
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
                                            <button class="btn btn-sm btn-success fw-bold rounded-pill" onclick="alert('Results verified by Tech #<?= $lab['verified_by_user_id'] ?> on <?= $lab['completed_at'] ?>')">
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
                        <textarea name="result_value" class="form-control" rows="2" placeholder="e.g. Hemoglobin: 14.2 g/dL | WBC: 7,500 /mcL | Platelets: 250,000 /mcL" required></textarea>
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
                        <textarea name="technician_notes" class="form-control" rows="2" placeholder="Notes on sample quality, equipment calibration, or morphology..."></textarea>
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