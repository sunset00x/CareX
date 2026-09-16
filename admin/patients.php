<?php
/**
 * Admin - Patient Roster Directory
 */
$pageTitle = "Manage Patients";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$search = sanitize($_GET['q'] ?? '');

$sql = "
    SELECT p.*, u.name, u.email, u.phone, u.status, u.profile_image,
           (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as appt_count
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR p.patient_id LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">Registered Patients</h3>
                <p class="text-muted mb-0">View all registered patients and their details.</p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="patients.php" class="row g-2">
                    <div class="col-md-10">
                        <input type="text" name="q" class="form-control" placeholder="Search patient by name, ID, email, or phone..." value="<?= sanitize($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient ID</th>
                                <th>Full Name</th>
                                <th>Email / Phone</th>
                                <th>Gender / Blood</th>
                                <th>DOB</th>
                                <th>Appointments</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($patients)): foreach ($patients as $p): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= sanitize($p['patient_id']) ?></span></td>
                                    <td class="fw-bold text-dark"><?= sanitize($p['name']) ?></td>
                                    <td>
                                        <div class="text-dark"><?= sanitize($p['email']) ?></div>
                                        <small class="text-muted"><?= sanitize($p['phone'] ?: 'No Phone') ?></small>
                                    </td>
                                    <td><?= ucfirst(sanitize($p['gender'])) ?> / <span class="fw-bold text-danger"><?= sanitize($p['blood_group'] ?: 'N/A') ?></span></td>
                                    <td><?= formatDate($p['date_of_birth']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $p['appt_count'] ?> Bookings</span></td>
                                    <td><span class="badge bg-<?= $p['status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst(sanitize($p['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No patient records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>