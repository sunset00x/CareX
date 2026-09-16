<?php
/**
 * Admin - Hospital Departments Management & Editing Engine
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Departments Management";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS (CREATE & EDIT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Error. Action cancelled.";
    } else {
        // 1. CREATE NEW DEPARTMENT
        if ($action === 'create') {
            $name = sanitize($_POST['name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');

            if (empty($name)) {
                $error = "Department name is required.";
            } else {
                try {
                    $stmtIns = $db->prepare("INSERT INTO departments (name, description, status) VALUES (?, ?, 'active')");
                    $stmtIns->execute([$name, $desc]);

                    logAudit($_SESSION['user_id'], "Added New Department '{$name}'", 'Departments');
                    setFlashMessage('success', "Department '{$name}' created successfully.");
                    header('Location: departments.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Department creation error: " . $e->getMessage();
                }
            }
        }
        // 2. EDIT / UPDATE EXISTING DEPARTMENT
        elseif ($action === 'edit') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $name   = sanitize($_POST['name'] ?? '');
            $desc   = sanitize($_POST['description'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');

            if ($deptId > 0 && !empty($name)) {
                try {
                    $stmtUpd = $db->prepare("UPDATE departments SET name = ?, description = ?, status = ? WHERE id = ?");
                    $stmtUpd->execute([$name, $desc, $status, $deptId]);

                    logAudit($_SESSION['user_id'], "Updated Department #{$deptId} ('{$name}')", 'Departments', $deptId);
                    setFlashMessage('success', "Department '{$name}' details updated successfully.");
                    header('Location: departments.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Update error: " . $e->getMessage();
                }
            } else {
                $error = "Please provide a valid department name.";
            }
        }
    }
}

// Fetch all departments with active doctor counts
$departments = $db->query("
    SELECT d.*, COUNT(doc.id) as doctor_count 
    FROM departments d 
    LEFT JOIN doctors doc ON doc.department_id = d.id 
    GROUP BY d.id 
    ORDER BY d.name ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger shadow-sm"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Hospital Departments Directory</h2>
                <p class="text-muted mb-0">Add, edit, or toggle clinical department information shown across the hospital system.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: Add Department Form -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Department</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="departments.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Department Name *</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Cardiology" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Overview of department services..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">Save Department</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Side: Departments Directory & Edit Actions -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-building me-2"></i>Active & Inactive Departments</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Info</th>
                                        <th>Assigned Doctors</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($departments)): foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><code>#<?= $dept['id'] ?></code></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= sanitize($dept['name']) ?></div>
                                                <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                                    <?= sanitize($dept['description'] ?: 'No description provided.') ?>
                                                </small>
                                            </td>
                                            <td><span class="badge bg-primary-subtle text-primary fw-bold"><?= $dept['doctor_count'] ?> Doctors</span></td>
                                            <td>
                                                <span class="badge bg-<?= $dept['status'] === 'active' ? 'success' : 'danger' ?> px-3 py-1">
                                                    <?= ucfirst(sanitize($dept['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editDeptModal"
                                                        data-id="<?= $dept['id'] ?>"
                                                        data-name="<?= sanitize($dept['name']) ?>"
                                                        data-desc="<?= sanitize($dept['description']) ?>"
                                                        data-status="<?= sanitize($dept['status']) ?>">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No departments created yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT DEPARTMENT DETAILS              -->
<!-- ========================================== -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Department Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="departments.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="department_id" id="edit_dept_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Name *</label>
                        <input type="text" name="name" id="edit_dept_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="edit_dept_desc" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status *</label>
                        <select name="status" id="edit_dept_status" class="form-select" required>
                            <option value="active">Active (Visible Publicly)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editDeptModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            document.getElementById('edit_dept_id').value     = btn.getAttribute('data-id');
            document.getElementById('edit_dept_name').value   = btn.getAttribute('data-name');
            document.getElementById('edit_dept_desc').value   = btn.getAttribute('data-desc');
            document.getElementById('edit_dept_status').value = btn.getAttribute('data-status');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>