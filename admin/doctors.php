<?php
/**
 * Admin - Doctor Profiles & License Management CRUD
 */
$pageTitle = "Manage Doctors";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';
$action = sanitize($_GET['action'] ?? '');

// Create Doctor Account Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name'] ?? '');
    $email        = sanitize($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $phone        = sanitize($_POST['phone'] ?? '');
    $deptId       = (int)($_POST['department_id'] ?? 0);
    $spec         = sanitize($_POST['specialization'] ?? '');
    $qual         = sanitize($_POST['qualification'] ?? '');
    $exp          = (int)($_POST['experience'] ?? 0);
    $fee          = (float)($_POST['consultation_fee'] ?? 0);
    $license      = sanitize($_POST['license_number'] ?? '');
    $token        = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } elseif (empty($name) || empty($email) || empty($password) || !$deptId || empty($license)) {
        $error = "Please fill in all mandatory doctor fields.";
    } else {
        try {
            $db->beginTransaction();

            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmtU = $db->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, 'doctor', ?, 'active')");
            $stmtU->execute([$name, $email, $hashed, $phone]);
            $userId = $db->lastInsertId();

            $docCode = generateDoctorID();
            $stmtD = $db->prepare("INSERT INTO doctors (user_id, doctor_id, department_id, specialization, qualification, experience, consultation_fee, license_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtD->execute([$userId, $docCode, $deptId, $spec, $qual, $exp, $fee, $license]);

            $db->commit();
            logAudit($_SESSION['user_id'], 'Added New Doctor', 'Doctors', $userId);
            setFlashMessage('success', "Doctor Dr. {$name} registered successfully!");
            header('Location: doctors.php');
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Doctor addition error: " . $e->getMessage();
        }
    }
}

// Fetch Doctors
$doctors = $db->query("
    SELECT d.*, u.name, u.email, u.phone, dept.name as dept_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY u.name ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <?php if ($action === 'create'): ?>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3">
                            <h4 class="fw-bold mb-0">Register New Specialist Doctor</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                            <form action="doctors.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Full Name *</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email Address *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Password *</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phone Number</label>
                                        <input type="text" name="phone" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Department *</label>
                                        <select name="department_id" class="form-select" required>
                                            <option value="">Select Department</option>
                                            <?php
                                            $depts = $db->query("SELECT * FROM departments WHERE status='active'")->fetchAll();
                                            foreach ($depts as $dept):
                                            ?>
                                                <option value="<?= $dept['id'] ?>"><?= sanitize($dept['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Specialization *</label>
                                        <input type="text" name="specialization" class="form-control" placeholder="Cardiology, General Practice..." required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Qualifications</label>
                                        <input type="text" name="qualification" class="form-control" placeholder="MBBS, MD">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Experience (Yrs)</label>
                                        <input type="number" name="experience" class="form-control" value="5">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Consultation Fee</label>
                                        <input type="number" step="0.01" name="consultation_fee" class="form-control" value="1000.00">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Medical License # *</label>
                                        <input type="text" name="license_number" class="form-control" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Register Doctor</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Medical Staff & Doctors Directory</h3>
                <a href="doctors.php?action=create" class="btn btn-primary rounded-pill px-4"><i class="bi bi-person-plus me-1"></i> Add Doctor</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Doctor ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Specialization</th>
                                    <th>Fee</th>
                                    <th>License</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $d): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= sanitize($d['doctor_id']) ?></span></td>
                                        <td class="fw-bold">Dr. <?= sanitize($d['name']) ?></td>
                                        <td><?= sanitize($d['dept_name']) ?></td>
                                        <td><?= sanitize($d['specialization']) ?></td>
                                        <td class="text-success fw-bold"><?= formatCurrency($d['consultation_fee']) ?></td>
                                        <td><code><?= sanitize($d['license_number']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>