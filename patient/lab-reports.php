<?php
/**
 * Patient - Laboratory Diagnostics Reports
 */
$pageTitle = "My Lab Reports";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$user = currentUser();
$db = Database::getConnection();

// Fetch Patient ID
$stmtP = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmtP->execute([$user['id']]);
$patient = $stmtP->fetch();
$patientId = $patient['id'] ?? 0;

// Fetch Lab Test Reports
$stmtLabs = $db->prepare("
    SELECT lt.*, lr.result, lr.reference_range, lr.notes, lr.created_at as report_date, u.name as doctor_name
    FROM lab_tests lt
    LEFT JOIN lab_reports lr ON lt.id = lr.lab_test_id
    JOIN doctors d ON lt.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE lt.patient_id = ?
    ORDER BY lt.requested_at DESC
");
$stmtLabs->execute([$patientId]);
$reports = $stmtLabs->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Laboratory Diagnostic Reports</h3>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Test Code</th>
                                <th>Test Name</th>
                                <th>Ordering Doctor</th>
                                <th>Requested Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reports)): foreach ($reports as $lab): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($lab['test_number']) ?></td>
                                    <td class="fw-semibold"><?= sanitize($lab['test_name']) ?></td>
                                    <td>Dr. <?= sanitize($lab['doctor_name']) ?></td>
                                    <td><?= formatDate($lab['requested_at']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $lab['status'] === 'Completed' ? 'success' : 'warning' ?>">
                                            <?= sanitize($lab['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($lab['status'] === 'Completed'): ?>
                                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#labModal<?= $lab['id'] ?>">
                                                <i class="bi bi-file-earmark-text me-1"></i> View Result
                                            </button>

                                            <!-- Lab Result Modal -->
                                            <div class="modal fade" id="labModal<?= $lab['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header border-0 bg-light">
                                                            <h5 class="modal-title fw-bold">Lab Result: <?= sanitize($lab['test_name']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-secondary">Diagnostic Result Findings:</label>
                                                                <div class="p-3 bg-light rounded font-monospace"><?= nl2br(sanitize($lab['result'])) ?></div>
                                                            </div>
                                                            <?php if (!empty($lab['reference_range'])): ?>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-secondary">Reference Range:</label>
                                                                    <div class="p-2 border rounded small"><?= nl2br(sanitize($lab['reference_range'])) ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Pending Result</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No laboratory orders on record.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>