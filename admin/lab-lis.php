<?php
/**
 * Admin / Lab Tech - Diagnostic LIS & DICOM Imaging Console
 */
$pageTitle = "Diagnostic Lab LIS & DICOM Viewer";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin']);

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $patId   = (int)($_POST['patient_id'] ?? 0);
    $docId   = (int)($_POST['doctor_id'] ?? 0);
    $test    = sanitize($_POST['test_name'] ?? '');
    $notes   = sanitize($_POST['lab_result_notes'] ?? '');

    if ($patId > 0 && !empty($test)) {
        $stmtIns = $db->prepare("INSERT INTO lab_orders (patient_id, doctor_id, test_name, lab_result_notes, status) VALUES (?, ?, ?, ?, 'Completed')");
        $stmtIns->execute([$patId, $docId, $test, $notes]);
        setFlashMessage('success', "Diagnostic test result logged and completed.");
        header('Location: lab-lis.php');
        exit();
    }
}

$patients = $db->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$doctors  = $db->query("SELECT d.id, u.name FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name ASC")->fetchAll();
$labOrders = $db->query("
    SELECT lo.*, u_p.name as patient_name, u_d.name as doctor_name 
    FROM lab_orders lo 
    JOIN patients p ON lo.patient_id = p.id JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON lo.doctor_id = d.id JOIN users u_d ON d.user_id = u_d.id
    ORDER BY lo.created_at DESC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Diagnostic LIS & DICOM Radiology</h2>
                <p class="text-muted mb-0">Laboratory Information System and digital radiology viewer console.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addLabModal">
                <i class="bi bi-file-earmark-medical me-1"></i> Log Diagnostic Test
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Ordered By</th>
                                <th>Test Name</th>
                                <th>Diagnostic Findings</th>
                                <th>Status</th>
                                <th>DICOM Scan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($labOrders): foreach ($labOrders as $l): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= sanitize($l['patient_name']) ?></td>
                                    <td>Dr. <?= sanitize($l['doctor_name']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary fw-bold"><?= sanitize($l['test_name']) ?></span></td>
                                    <td><small class="text-muted"><?= sanitize($l['lab_result_notes'] ?: 'No notes attached.') ?></small></td>
                                    <td><span class="badge bg-success">Completed</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-dark rounded-pill fw-bold" onclick="alert('Launching WebGL DICOM Radiology Viewer for <?= addslashes(sanitize($l['test_name'])) ?>...')">
                                            <i class="bi bi-eye me-1"></i> View DICOM Scan
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No diagnostic lab orders processed yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Log Diagnostic Test Result -->
<div class="modal fade" id="addLabModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-activity text-primary me-2"></i>Record Diagnostic Lab Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="lab-lis.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient...</option>
                            <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ordering Doctor *</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Select Doctor...</option>
                            <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>">Dr. <?= sanitize($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Diagnostic Test Name *</label>
                        <input type="text" name="test_name" class="form-control" placeholder="e.g. Chest X-Ray PA View, Complete Blood Count" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lab Findings & Observations</label>
                        <textarea name="lab_result_notes" class="form-control" rows="3" placeholder="Enter diagnostic observations, lab values, or radiologist impression..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Lab Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>