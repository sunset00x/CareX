<?php
/**
 * Advanced Admin - User Management Console & Profile Inspector
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Users & Access Control";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error. Operation cancelled.";
    } else {
        // 1. EDIT USER DETAILS
        if ($action === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name   = sanitize($_POST['name'] ?? '');
            $email  = sanitize($_POST['email'] ?? '');
            $phone  = sanitize($_POST['phone'] ?? '');
            $role   = sanitize($_POST['role'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');

            if ($userId > 0 && !empty($name) && !empty($email)) {
                try {
                    $stmtUpd = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $stmtUpd->execute([$name, $email, $phone, $role, $status, $userId]);

                    logAudit($_SESSION['user_id'], "Updated User #{$userId} Profile ({$name})", 'Users', $userId);
                    setFlashMessage('success', "User '{$name}' updated successfully.");
                    header('Location: users.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Error updating user: " . $e->getMessage();
                }
            }
        }

        // 2. TOGGLE ACCOUNT STATUS (Activate / Suspend)
        elseif ($action === 'toggle_status') {
            $userId    = (int)($_POST['user_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? 'active');

            if ($userId > 0 && $userId !== $_SESSION['user_id']) {
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $userId]);

                logAudit($_SESSION['user_id'], "Set User #{$userId} Status to {$newStatus}", 'Users', $userId);
                setFlashMessage('success', "User account status changed to '{$newStatus}'.");
                header('Location: users.php');
                exit();
            }
        }

        // 3. ADMIN PASSWORD OVERRIDE
        elseif ($action === 'reset_password') {
            $userId   = (int)($_POST['user_id'] ?? 0);
            $newPass  = $_POST['new_password'] ?? '';

            if ($userId > 0 && !empty($newPass)) {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                $stmtPass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtPass->execute([$hashed, $userId]);

                logAudit($_SESSION['user_id'], "Reset Password for User #{$userId}", 'Users', $userId);
                setFlashMessage('success', "Password updated successfully for User #{$userId}.");
                header('Location: users.php');
                exit();
            }
        }
    }
}

// ==========================================
// DATA FETCHING & FILTERING
// ==========================================
$searchRole = sanitize($_GET['role'] ?? '');
$searchQuery = sanitize($_GET['q'] ?? '');

$sql = "SELECT u.*, 
          p.patient_id as patient_code, p.gender, p.blood_group,
          d.doctor_id as doctor_code, d.specialization, dept.name as dept_name
        FROM users u
        LEFT JOIN patients p ON p.user_id = u.id
        LEFT JOIN doctors d ON d.user_id = u.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE 1=1";

$params = [];

if (!empty($searchRole)) {
    $sql .= " AND u.role = ?";
    $params[] = $searchRole;
}

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY u.created_at DESC";

$stmtUsers = $db->prepare($sql);
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll();

// Count KPIs by Role
$kpiCounts = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <!-- Title Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">System User Directory & Access Control</h2>
                <p class="text-muted">Manage system identity, credentials, roles, and review individual user appointments.</p>
            </div>
        </div>

        <!-- Metric Quick Filter Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <a href="users.php" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= empty($searchRole) ? 'border-primary border-2' : '' ?>">
                        <small class="text-muted fw-bold">ALL USERS</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= array_sum($kpiCounts) ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=admin" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'admin' ? 'border-danger border-2' : '' ?>">
                        <small class="text-danger fw-bold">ADMINS</small>
                        <h4 class="fw-bold mb-0 text-danger"><?= $kpiCounts['admin'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=doctor" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'doctor' ? 'border-primary border-2' : '' ?>">
                        <small class="text-primary fw-bold">DOCTORS</small>
                        <h4 class="fw-bold mb-0 text-primary"><?= $kpiCounts['doctor'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=patient" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'patient' ? 'border-success border-2' : '' ?>">
                        <small class="text-success fw-bold">PATIENTS</small>
                        <h4 class="fw-bold mb-0 text-success"><?= $kpiCounts['patient'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=laboratory" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'laboratory' ? 'border-warning border-2' : '' ?>">
                        <small class="text-warning fw-bold">LAB TECHS</small>
                        <h4 class="fw-bold mb-0 text-warning"><?= $kpiCounts['laboratory'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="users.php?role=billing" class="text-decoration-none">
                    <div class="card card-kpi p-3 bg-white border <?= $searchRole == 'billing' ? 'border-info border-2' : '' ?>">
                        <small class="text-info fw-bold">BILLING</small>
                        <h4 class="fw-bold mb-0 text-info"><?= $kpiCounts['billing'] ?? 0 ?></h4>
                    </div>
                </a>
            </div>
        </div>

        <!-- Directory Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="users.php" class="row g-2">
                    <?php if (!empty($searchRole)): ?>
                        <input type="hidden" name="role" value="<?= sanitize($searchRole) ?>">
                    <?php endif; ?>
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0" placeholder="Search user by name, email, or phone number..." value="<?= sanitize($searchQuery) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main User Accounts Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User Profile</th>
                                <th>Email</th>
                                <th>System Role</th>
                                <th>Phone</th>
                                <th>Account Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($u['profile_image']) ?>" class="rounded-circle border me-3 object-fit-cover" width="42" height="42">
                                            <div>
                                                <div class="fw-bold text-dark mb-0"><?= sanitize($u['name']) ?></div>
                                                <small class="text-muted">
                                                    <?php if ($u['role'] === 'patient' && $u['patient_code']): ?>
                                                        ID: <code><?= sanitize($u['patient_code']) ?></code>
                                                    <?php elseif ($u['role'] === 'doctor' && $u['doctor_code']): ?>
                                                        Specialist: <?= sanitize($u['specialization']) ?>
                                                    <?php else: ?>
                                                        Created: <?= formatDate($u['created_at']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-semibold text-secondary"><?= sanitize($u['email']) ?></td>
                                    <td>
                                        <?php
                                        $roleBadge = match($u['role']) {
                                            'admin' => 'bg-danger',
                                            'doctor' => 'bg-primary',
                                            'patient' => 'bg-success',
                                            'laboratory' => 'bg-warning text-dark',
                                            'billing' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $roleBadge ?> text-capitalize px-3 py-2 rounded-pill"><?= sanitize($u['role']) ?></span>
                                    </td>
                                    <td><?= sanitize($u['phone'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>-subtle text-<?= $u['status'] === 'active' ? 'success' : 'danger' ?> fw-bold border border-<?= $u['status'] === 'active' ? 'success' : 'danger' ?> px-3 py-1">
                                            <?= ucfirst(sanitize($u['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <!-- Edit Modal Trigger -->
                                                <li>
                                                    <button type="button" class="dropdown-item" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editModal"
                                                            data-id="<?= $u['id'] ?>"
                                                            data-name="<?= sanitize($u['name']) ?>"
                                                            data-email="<?= sanitize($u['email']) ?>"
                                                            data-phone="<?= sanitize($u['phone']) ?>"
                                                            data-role="<?= sanitize($u['role']) ?>"
                                                            data-status="<?= sanitize($u['status']) ?>">
                                                        <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Profile Details
                                                    </button>
                                                </li>

                                                <!-- Deep Profile & Appointments Viewer -->
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewModal<?= $u['id'] ?>">
                                                        <i class="bi bi-calendar-range me-2 text-info"></i>View Profile & Appointments
                                                    </button>
                                                </li>

                                                <!-- Reset Password Trigger -->
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#passModal"
                                                            data-id="<?= $u['id'] ?>"
                                                            data-name="<?= sanitize($u['name']) ?>">
                                                        <i class="bi bi-key me-2 text-warning"></i>Override Password
                                                    </button>
                                                </li>

                                                <li><hr class="dropdown-divider"></li>

                                                <!-- Suspend / Activate Account -->
                                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                    <li>
                                                        <form action="users.php" method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <?php if ($u['status'] === 'active'): ?>
                                                                <button type="submit" name="status" value="suspended" class="dropdown-item text-danger"><i class="bi bi-slash-circle me-2"></i>Suspend Account</button>
                                                            <?php else: ?>
                                                                <button type="submit" name="status" value="active" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>Reactivate Account</button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item text-muted disabled">Current Logged Admin</span></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>

                                        <!-- ========================================== -->
                                        <!-- MODAL: VIEW PROFILE & USER APPOINTMENTS     -->
                                        <!-- ========================================== -->
                                        <div class="modal fade text-start" id="viewModal<?= $u['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-light border-0">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-person-bounding-box text-primary me-2"></i>Profile & Appointments: <?= sanitize($u['name']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <!-- Profile Summary Bar -->
                                                        <div class="p-3 bg-light rounded-4 mb-4 border d-flex align-items-center">
                                                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($u['profile_image']) ?>" class="rounded-circle me-3 object-fit-cover border" width="60" height="60">
                                                            <div>
                                                                <h5 class="fw-bold mb-0"><?= sanitize($u['name']) ?></h5>
                                                                <small class="text-muted"><?= sanitize($u['email']) ?> | Phone: <?= sanitize($u['phone'] ?: 'N/A') ?></small>
                                                                <div>
                                                                    <span class="badge <?= $roleBadge ?> text-capitalize mt-1"><?= sanitize($u['role']) ?></span>
                                                                    <?php if ($u['role'] === 'patient'): ?>
                                                                        <span class="badge bg-secondary ms-1">DOB: <?= formatDate($u['gender']) ?> | Blood: <?= sanitize($u['blood_group'] ?: 'N/A') ?></span>
                                                                    <?php elseif ($u['role'] === 'doctor'): ?>
                                                                        <span class="badge bg-info text-dark ms-1"><?= sanitize($u['specialization']) ?> (<?= sanitize($u['dept_name']) ?>)</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- User-Specific Appointments List -->
                                                        <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-calendar-check me-2"></i>Associated Appointment History</h6>
                                                        <?php
                                                        if ($u['role'] === 'patient') {
                                                            $stmtAppt = $db->prepare("
                                                                SELECT a.*, u_doc.name as doctor_name, dept.name as dept_name 
                                                                FROM appointments a
                                                                JOIN patients p ON a.patient_id = p.id
                                                                JOIN doctors d ON a.doctor_id = d.id
                                                                JOIN users u_doc ON d.user_id = u_doc.id
                                                                JOIN departments dept ON d.department_id = dept.id
                                                                WHERE p.user_id = ?
                                                                ORDER BY a.appointment_date DESC
                                                            ");
                                                            $stmtAppt->execute([$u['id']]);
                                                        } elseif ($u['role'] === 'doctor') {
                                                            $stmtAppt = $db->prepare("
                                                                SELECT a.*, u_pat.name as patient_name, dept.name as dept_name 
                                                                FROM appointments a
                                                                JOIN doctors d ON a.doctor_id = d.id
                                                                JOIN patients p ON a.patient_id = p.id
                                                                JOIN users u_pat ON p.user_id = u_pat.id
                                                                JOIN departments dept ON d.department_id = dept.id
                                                                WHERE d.user_id = ?
                                                                ORDER BY a.appointment_date DESC
                                                            ");
                                                            $stmtAppt->execute([$u['id']]);
                                                        } else {
                                                            $stmtAppt = null;
                                                        }

                                                        $userAppts = $stmtAppt ? $stmtAppt->fetchAll() : [];
                                                        ?>

                                                        <?php if (!empty($userAppts)): ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-sm align-middle">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th>Appt #</th>
                                                                            <th><?= $u['role'] === 'patient' ? 'Doctor' : 'Patient' ?></th>
                                                                            <th>Date & Time</th>
                                                                            <th>Reason</th>
                                                                            <th>Status</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($userAppts as $ap): ?>
                                                                            <tr>
                                                                                <td class="fw-bold text-primary"><?= sanitize($ap['appointment_number']) ?></td>
                                                                                <td><?= sanitize($u['role'] === 'patient' ? $ap['doctor_name'] : $ap['patient_name']) ?></td>
                                                                                <td><?= formatDate($ap['appointment_date']) ?> <small><?= formatTime($ap['appointment_time']) ?></small></td>
                                                                                <td><?= sanitize($ap['reason'] ?: 'N/A') ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?= $ap['status'] === 'Confirmed' ? 'success' : ($ap['status'] === 'Completed' ? 'info' : 'warning') ?>">
                                                                                        <?= sanitize($ap['status']) ?>
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="alert alert-light text-center border py-3 text-muted">
                                                                No appointments recorded for this user identity.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No user accounts found matching query parameters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT USER DETAILS                   -->
<!-- ========================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Edit System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="billing">Billing</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                            </select>
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

<!-- ========================================== -->
<!-- MODAL: OVERRIDE USER PASSWORD             -->
<!-- ========================================== -->
<div class="modal fade" id="passModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Override User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="pass_user_id">

                    <p class="text-muted mb-3">Target Account: <strong id="pass_target_name" class="text-dark"></strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password *</label>
                        <input type="password" name="new_password" class="form-control form-control-lg" placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit User Modal
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_name').value    = btn.getAttribute('data-name');
            document.getElementById('edit_email').value   = btn.getAttribute('data-email');
            document.getElementById('edit_phone').value   = btn.getAttribute('data-phone');
            document.getElementById('edit_role').value    = btn.getAttribute('data-role');
            document.getElementById('edit_status').value  = btn.getAttribute('data-status');
        });
    }

    // Populate Password Override Modal
    const passModal = document.getElementById('passModal');
    if (passModal) {
        passModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('pass_user_id').value     = btn.getAttribute('data-id');
            document.getElementById('pass_target_name').textContent = btn.getAttribute('data-name');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>