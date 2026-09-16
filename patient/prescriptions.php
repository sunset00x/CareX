<?php
/**
 * Patient - My Prescriptions Listing & Detailed Modal
 */
$pageTitle = "My Prescriptions";
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

// Fetch Prescriptions
$stmtPresc = $db->prepare("
    SELECT pr.*, u.name as doctor_name, d.specialization
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
");
$stmtPresc->execute([$patientId]);
$prescriptions = $stmtPresc->fetchAll();

// Fetch Line items for detail display
$details = [];
if (isset($_GET['view_id'])) {
    $viewId = (int)$_GET['view_id'];
    $stmtItems = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
    $stmtItems->execute([$viewId]);
    $details = $stmtItems->fetchAll();
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">My Medical Prescriptions</h3>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rx Number</th>
                                <th>Prescribing Doctor</th>
                                <th>Date Issued</th>
                                <th>Instructions</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($prescriptions)): foreach ($prescriptions as $rx): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= sanitize($rx['prescription_number']) ?></td>
                                    <td class="fw-semibold">Dr. <?= sanitize($rx['doctor_name']) ?></td>
                                    <td><?= formatDate($rx['created_at']) ?></td>
                                    <td><?= sanitize($rx['instructions'] ?: 'Standard dosage guidelines apply.') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rxModal<?= $rx['id'] ?>">
                                            <i class="bi bi-eye me-1"></i> View Rx Items
                                        </button>

                                        <!-- RX Modal -->
                                        <div class="modal fade" id="rxModal<?= $rx['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header border-0 bg-light">
                                                        <h5 class="modal-title fw-bold">Prescription <?= sanitize($rx['prescription_number']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                                            <div><strong>Doctor:</strong> Dr. <?= sanitize($rx['doctor_name']) ?></div>
                                                            <div><strong>Date:</strong> <?= formatDate($rx['created_at']) ?></div>
                                                        </div>
                                                        <table class="table table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Medicine</th>
                                                                    <th>Dosage</th>
                                                                    <th>Frequency</th>
                                                                    <th>Duration</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $stmtItms = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                                                                $stmtItms->execute([$rx['id']]);
                                                                $items = $stmtItms->fetchAll();
                                                                foreach ($items as $itm):
                                                                ?>
                                                                    <tr>
                                                                        <td class="fw-bold"><?= sanitize($itm['medicine_name']) ?></td>
                                                                        <td><?= sanitize($itm['dosage']) ?></td>
                                                                        <td><?= sanitize($itm['frequency']) ?></td>
                                                                        <td><?= sanitize($itm['duration']) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No prescriptions recorded.</td>
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