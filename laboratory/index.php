<?php
/**
 * Laboratory Dashboard Interface
 */
$pageTitle = "Laboratory Worklist";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('laboratory');

$user = currentUser();
$db = Database::getConnection();

// Fetch Pending and Completed Lab Statistics
$stmtPending = $db->query("SELECT COUNT(*) FROM lab_tests WHERE status IN ('Pending', 'In-Progress')");
$countPending = $stmtPending->fetchColumn();

$stmtCompleted = $db->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'Completed'");
$countCompleted = $stmtCompleted->fetchColumn();

// Fetch Lab Test Worklist Requests
$stmtWorklist = $db->query("
    SELECT lt.*, u_pat.name as patient_name, u_doc.name as doctor_name, p.patient_id as patient_code
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    JOIN users u_pat ON p.user_id = u_pat.id
    JOIN doctors d ON lt.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    ORDER BY lt.requested_at DESC
");
$worklist = $stmtWorklist->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Laboratory Worklist & Diagnostics</h2>
                <p class="text-muted">Process laboratory orders and upload diagnostic findings.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-warning-subtle text-warning me-3"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countPending ?></h3>
                            <small class="text-muted fw-semibold">Pending / Active Tests</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-success-subtle text-success me-3"><i class="bi bi-check-circle"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countCompleted ?></h3>
                            <small class="text-muted fw-semibold">Completed Reports</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Laboratory Test Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Test Order #</th>
                                <th>Patient</th>
                                <th>Ordering Doctor</th>
                                <th>Test Name</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($worklist)): foreach ($worklist as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($row['test_number']) ?></td>
                                    <td><?= sanitize($row['patient_name']) ?> <small class="text-muted">(<?= sanitize($row['patient_code']) ?>)</small></td>
                                    <td><?= sanitize($row['doctor_name']) ?></td>
                                    <td class="fw-semibold"><?= sanitize($row['test_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['priority'] == 'Urgent' ? 'danger' : ($row['priority'] == 'High' ? 'warning' : 'secondary') ?>">
                                            <?= sanitize($row['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $row['status'] == 'Completed' ? 'success' : 'info' ?>">
                                            <?= sanitize($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="test-requests.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Process Test</a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No diagnostic laboratory orders in queue.</td>
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