<?php
/**
 * Laboratory Staff - Diagnostic Processing & Result Upload
 */
$pageTitle = "Process Laboratory Test";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('laboratory');

$user = currentUser();
$db = Database::getConnection();

$testId = (int)($_GET['id'] ?? 0);
$error = '';

// Handle Test Result Submission & Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testId     = (int)($_POST['lab_test_id'] ?? 0);
    $result     = sanitize($_POST['result'] ?? '');
    $refRange   = sanitize($_POST['reference_range'] ?? '');
    $notes      = sanitize($_POST['notes'] ?? '');
    $token      = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Validation Failed.";
    } elseif (!$testId || empty($result)) {
        $error = "Please fill in test findings result.";
    } else {
        try {
            $db->beginTransaction();

            // Insert Lab Report Outcome
            $stmtIns = $db->prepare("INSERT INTO lab_reports (lab_test_id, technician_id, result, reference_range, notes) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->execute([$testId, $user['id'], $result, $refRange, $notes]);

            // Mark Test Order Completed
            $db->prepare("UPDATE lab_tests SET status = 'Completed', completed_at = NOW() WHERE id = ?")->execute([$testId]);

            // Notify Patient & Doctor
            $stmtInfo = $db->prepare("SELECT lt.test_number, p.user_id as patient_user_id, d.user_id as doc_user_id FROM lab_tests lt JOIN patients p ON lt.patient_id = p.id JOIN doctors d ON lt.doctor_id = d.id WHERE lt.id = ?");
            $stmtInfo->execute([$testId]);
            $info = $stmtInfo->fetch();

            if ($info) {
                createNotification($info['patient_user_id'], 'Lab Report Ready', "Diagnostic report for {$info['test_number']} is uploaded.", 'success', $testId);
                createNotification($info['doc_user_id'], 'Lab Report Ready', "Report for test {$info['test_number']} completed by laboratory.", 'info', $testId);
            }

            $db->commit();
            logAudit($user['id'], 'Uploaded Lab Result', 'Laboratory', $testId);

            setFlashMessage('success', 'Diagnostic lab report recorded and published!');
            header('Location: index.php');
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Lab processing failure: " . $e->getMessage();
        }
    }
}

// Fetch Specific Test Details
$stmtT = $db->prepare("
    SELECT lt.*, u_pat.name as patient_name, u_doc.name as doctor_name, p.patient_id as patient_code
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    JOIN users u_pat ON p.user_id = u_pat.id
    JOIN doctors d ON lt.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    WHERE lt.id = ?
");
$stmtT->execute([$testId]);
$test = $stmtT->fetch();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="fw-bold mb-0">Process Laboratory Diagnostic Findings</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                        <?php if ($test): ?>
                            <div class="row bg-light p-3 rounded mb-4">
                                <div class="col-md-4"><strong>Test Order #:</strong> <?= sanitize($test['test_number']) ?></div>
                                <div class="col-md-4"><strong>Patient:</strong> <?= sanitize($test['patient_name']) ?> (<?= sanitize($test['patient_code']) ?>)</div>
                                <div class="col-md-4"><strong>Ordering Doctor:</strong> Dr. <?= sanitize($test['doctor_name']) ?></div>
                                <div class="col-12 mt-2"><strong>Test Requested:</strong> <span class="fw-bold text-primary"><?= sanitize($test['test_name']) ?></span></div>
                            </div>

                            <form action="test-requests.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="lab_test_id" value="<?= $test['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Diagnostic Test Result Findings *</label>
                                    <textarea name="result" class="form-control" rows="4" placeholder="Enter full quantitative and qualitative result analysis..." required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Reference Range Standards</label>
                                    <textarea name="reference_range" class="form-control" rows="2" placeholder="e.g. Fasting Glucose: 70-99 mg/dL"></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Technician Observational Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">Publish Final Diagnostic Report</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">Invalid laboratory test record selected.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>