<?php
ob_start();
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

// FORM ACTION HANDLERS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = sanitize($_POST['action'] ?? '');
    $token      = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } 
    // 1. UPDATE EXISTING DOCTOR PROFILE
    elseif ($postAction === 'edit_doctor') {
        $doctorId   = (int)($_POST['doctor_id'] ?? 0);
        $userId     = (int)($_POST['user_id'] ?? 0);
        $name       = sanitize($_POST['name'] ?? '');
        $email      = sanitize($_POST['email'] ?? '');
        $phone      = sanitize($_POST['phone'] ?? '');
        $deptId     = (int)($_POST['department_id'] ?? 0);
        $spec       = sanitize($_POST['specialization'] ?? '');
        $qual       = sanitize($_POST['qualification'] ?? '');
        $exp        = (int)($_POST['experience'] ?? 0);
        $fee        = (float)($_POST['consultation_fee'] ?? 0);
        $license    = sanitize($_POST['license_number'] ?? '');

        if ($doctorId > 0 && $userId > 0 && !empty($name) && !empty($email) && $deptId > 0 && !empty($license)) {
            try {
                $db->beginTransaction();

                // Update User details
                $stmtU = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmtU->execute([$name, $email, $phone, $userId]);

                // Update Doctor details
                $stmtD = $db->prepare("
                    UPDATE doctors 
                    SET department_id = ?, specialization = ?, qualification = ?, experience = ?, consultation_fee = ?, license_number = ? 
                    WHERE id = ?
                ");
                $stmtD->execute([$deptId, $spec, $qual, $exp, $fee, $license, $doctorId]);

                $db->commit();
                logAudit($_SESSION['user_id'], "Updated Doctor Profile for Dr. {$name}", 'Doctors', $userId);
                setFlashMessage('success', "Doctor Dr. {$name} profile updated successfully!");
                header('Location: doctors.php');
                exit();
            } catch (\Exception $e) {
                $db->rollBack();
                $error = "Doctor update error: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all mandatory fields for editing.";
        }
    }
    // 2. CREATE NEW DOCTOR ACCOUNT
    else {
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

        if (empty($name) || empty($email) || empty($password) || !$deptId || empty($license)) {
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
}

// Fetch Active Departments for forms
$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name ASC")->fetchAll();

// Fetch Doctors Directory
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
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <?php if ($action === 'create'): ?>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h4 class="fw-bold mb-0">Register New Specialist Doctor</h4>
                            <a href="doctors.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back to Directory</a>
                        </div>
                        <div class="card-body p-4">
                            <form action="doctors.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Full Name *</label>
                                        <input type="text" name="name" class="form-control" placeholder="Dr. John Doe" required>
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
                                            <?php foreach ($departments as $dept): ?>
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
                <div>
                    <h3 class="fw-bold mb-0">Medical Staff & Doctors Directory</h3>
                    <p class="text-muted mb-0">Manage doctor credentials, clinical departments, and consultation fees.</p>
                </div>
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
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($doctors)): foreach ($doctors as $d): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= sanitize($d['doctor_id']) ?></span></td>
                                        <td class="fw-bold text-dark">Dr. <?= sanitize($d['name']) ?></td>
                                        <td><?= sanitize($d['dept_name']) ?></td>
                                        <td><?= sanitize($d['specialization']) ?></td>
                                        <td class="text-success fw-bold"><?= formatCurrency($d['consultation_fee']) ?></td>
                                        <td><code><?= sanitize($d['license_number']) ?></code></td>
                                        <td class="text-end pe-4">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary fw-bold rounded-pill"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editDoctorModal"
                                                    data-doctor='<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                                                <i class="bi bi-pencil-square me-1"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No doctor records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Edit Doctor Profile -->
<div class="modal fade" id="editDoctorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-primary me-2"></i>Edit Doctor Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="doctors.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_doctor">
                    <input type="hidden" name="doctor_id" id="edit_doctor_id">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department *</label>
                            <select name="department_id" id="edit_department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= sanitize($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specialization *</label>
                            <input type="text" name="specialization" id="edit_specialization" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Qualifications</label>
                            <input type="text" name="qualification" id="edit_qualification" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Experience (Yrs)</label>
                            <input type="number" name="experience" id="edit_experience" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Consultation Fee</label>
                            <input type="number" step="0.01" name="consultation_fee" id="edit_consultation_fee" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Medical License # *</label>
                            <input type="text" name="license_number" id="edit_license_number" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editDoctorModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            const data = JSON.parse(btn.getAttribute('data-doctor'));

            document.getElementById('edit_doctor_id').value        = data.id;
            document.getElementById('edit_user_id').value          = data.user_id;
            document.getElementById('edit_name').value             = data.name;
            document.getElementById('edit_email').value            = data.email;
            document.getElementById('edit_phone').value            = data.phone || '';
            document.getElementById('edit_department_id').value   = data.department_id;
            document.getElementById('edit_specialization').value  = data.specialization;
            document.getElementById('edit_qualification').value   = data.qualification || '';
            document.getElementById('edit_experience').value      = data.experience;
            document.getElementById('edit_consultation_fee').value = data.consultation_fee;
            document.getElementById('edit_license_number').value  = data.license_number;
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>