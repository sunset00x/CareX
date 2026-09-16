<?php
/**
 * Doctor - Patient Roster & EMR Histories
 */
$pageTitle = "My Patients";
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

// Fetch Unique Patients associated with this doctor
$stmtPatients = $db->prepare("
    SELECT DISTINCT p.*, u.name, u.email, u.phone, u.profile_image
    FROM patients p
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY u.name ASC
");
$stmtPatients->execute([$doctorId]);
$patients = $stmtPatients->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Assigned Patients Roster</h3>

        <div class="row g-4">
            <?php if (!empty($patients)): foreach ($patients as $pat): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($pat['profile_image']) ?>" class="rounded-circle border me-3 object-fit-cover" width="60" height="60">
                                <div>
                                    <h5 class="fw-bold mb-0"><?= sanitize($pat['name']) ?></h5>
                                    <span class="badge bg-secondary"><?= sanitize($pat['patient_id']) ?></span>
                                </div>
                            </div>
                            <p class="mb-1"><strong>Gender / Blood:</strong> <?= ucfirst(sanitize($pat['gender'])) ?> / <?= sanitize($pat['blood_group'] ?: 'N/A') ?></p>
                            <p class="mb-1"><strong>DOB:</strong> <?= formatDate($pat['date_of_birth']) ?></p>
                            <p class="mb-3"><strong>Phone:</strong> <?= sanitize($pat['phone'] ?: 'N/A') ?></p>

                            <a href="medical-records.php?patient_id=<?= $pat['id'] ?>" class="btn btn-outline-primary w-100 rounded-pill">
                                <i class="bi bi-journal-medical me-1"></i> View EMR History
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-4">No patients assigned to your care list yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>