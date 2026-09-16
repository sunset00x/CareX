<?php
/**
 * Patient - Find & Search Doctors
 */
$pageTitle = "Find a Doctor";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$db = Database::getConnection();

// Filtering Parameters
$search = sanitize($_GET['search'] ?? '');
$deptId = (int)($_GET['department_id'] ?? 0);

// Base Query
$sql = "
    SELECT d.*, u.name, u.email, u.phone, u.profile_image, dept.name as department_name 
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE u.status = 'active'
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($deptId > 0) {
    $sql .= " AND d.department_id = ?";
    $params[] = $deptId;
}

$sql .= " ORDER BY u.name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Fetch Departments for Filter Dropdown
$departments = $db->query("SELECT * FROM departments WHERE status = 'active'")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h3 class="fw-bold mb-4">Find a Specialist Doctor</h3>

        <!-- Search & Filter Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="GET" action="find-doctor.php" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Search by Name / Specialization</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Doctor name, specialization..." value="<?= sanitize($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Filter by Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= $deptId == $dept['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Doctor Directory Cards Grid -->
        <div class="row g-4">
            <?php if (!empty($doctors)): foreach ($doctors as $doc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4 text-center">
                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($doc['profile_image']) ?>" alt="Doctor" class="rounded-circle border mb-3 object-fit-cover" width="90" height="90">
                            <h5 class="fw-bold mb-1">Dr. <?= sanitize($doc['name']) ?></h5>
                            <span class="badge bg-primary-subtle text-primary mb-2"><?= sanitize($doc['department_name']) ?></span>
                            <p class="text-muted small mb-2"><?= sanitize($doc['qualification']) ?> • <?= $doc['experience'] ?> Years Exp.</p>
                            <p class="fw-bold text-success mb-3">Fee: <?= formatCurrency($doc['consultation_fee']) ?></p>
                            
                            <a href="book-appointment.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-outline-primary w-100 rounded-pill">
                                <i class="bi bi-calendar-plus me-1"></i> Book Appointment
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12">
                    <div class="alert alert-info py-4 text-center">No doctors matched your search criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>