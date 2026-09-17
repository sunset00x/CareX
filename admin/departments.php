<?php
ob_start();
/**
 * Admin - Department Management Console & Clinical Unit Tracking
 * CareX Smart Hospital Management System
 */
$pageTitle = "Departments Management";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// ==========================================
// FORM ACTION HANDLERS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } else {

        // 1. ADD NEW DEPARTMENT
        if ($action === 'add_department') {
            $name        = sanitize($_POST['name'] ?? '');
            $code        = strtoupper(sanitize($_POST['code'] ?? ''));
            $description = sanitize($_POST['description'] ?? '');
            $status      = sanitize($_POST['status'] ?? 'active');

            if (!empty($name) && !empty($code)) {
                try {
                    $stmt = $db->prepare("INSERT INTO departments (name, code, description, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $code, $description, $status]);

                    logAudit($_SESSION['user_id'], "Created Department {$name} ({$code})", 'Departments');
                    setFlashMessage('success', "Department '{$name}' created successfully.");
                    header('Location: departments.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Department creation error: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all mandatory department fields.";
            }
        }

        // 2. EDIT DEPARTMENT
        elseif ($action === 'edit_department') {
            $deptId      = (int)($_POST['department_id'] ?? 0);
            $name        = sanitize($_POST['name'] ?? '');
            $code        = strtoupper(sanitize($_POST['code'] ?? ''));
            $description = sanitize($_POST['description'] ?? '');
            $status      = sanitize($_POST['status'] ?? 'active');

            if ($deptId > 0 && !empty($name) && !empty($code)) {
                try {
                    $stmt = $db->prepare("UPDATE departments SET name = ?, code = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $description, $status, $deptId]);

                    logAudit($_SESSION['user_id'], "Updated Department #{$deptId} ({$name})", 'Departments');
                    setFlashMessage('success', "Department '{$name}' updated successfully.");
                    header('Location: departments.php');
                    exit();
                } catch (\Exception $e) {
                    $error = "Department update error: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all mandatory fields for editing.";
            }
        }

        // 3. DELETE DEPARTMENT
        elseif ($action === 'delete_department') {
            $deptId = (int)($_POST['department_id'] ?? 0);

            if ($deptId > 0) {
                try {
                    // Check if doctors are assigned
                    $stmtChk = $db->prepare("SELECT COUNT(*) FROM doctors WHERE department_id = ?");
                    $stmtChk->execute([$deptId]);
                    $docCount = $stmtChk->fetchColumn();

                    if ($docCount > 0) {
                        $error = "Cannot delete department! There are {$docCount} doctor(s) assigned to this department.";
                    } else {
                        $stmtDel = $db->prepare("DELETE FROM departments WHERE id = ?");
                        $stmtDel->execute([$deptId]);

                        logAudit($_SESSION['user_id'], "Deleted Department #{$deptId}", 'Departments');
                        setFlashMessage('success', "Department deleted successfully.");
                        header('Location: departments.php');
                        exit();
                    }
                } catch (\Exception $e) {
                    $error = "Department deletion error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all departments with doctor counts
$departments = $db->query("
    SELECT dept.*, COUNT(d.id) as total_doctors 
    FROM departments dept 
    LEFT JOIN doctors d ON dept.id = d.department_id 
    GROUP BY dept.id 
    ORDER BY dept.name ASC
")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Clinical Departments & Units</h2>
                <p class="text-muted mb-0">Configure hospital specialties, clinical codes, and staff assignments.</p>
            </div>
            <button class="btn btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="bi bi-building-add me-1"></i> Add Department
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Assigned Doctors</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($departments)): foreach ($departments as $dept): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= sanitize($dept['code'] ?? 'N/A') ?></span></td><td class="fw-bold text-dark"><?= sanitize($dept['name']) ?></td>
                                    <td><small class="text-muted"><?= sanitize($dept['description'] ?: 'N/A') ?></small></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-1">
                                            <?= $dept['total_doctors'] ?> Doctor(s)
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $dept['status'] === 'active' ? 'success' : 'danger' ?> text-capitalize">
                                            <?= sanitize($dept['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary fw-bold rounded-pill me-1"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editDeptModal"
                                                data-dept='<?= json_encode($dept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </button>
                                        <form action="departments.php" method="POST" class="d-inline" onsubmit="return confirm('Delete department <?= sanitize($dept['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="delete_department">
                                            <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger fw-bold rounded-pill">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No department records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-building-add text-primary me-2"></i>New Clinical Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="departments.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_department">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Cardiology, Neurology" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Code *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. CARD, NEUR" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Clinical functions or ward notes..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-building-gear text-primary me-2"></i>Edit Department Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="departments.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="edit_department">
                    <input type="hidden" name="department_id" id="edit_dept_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Code *</label>
                        <input type="text" name="code" id="edit_code" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status *</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
    const editModal = document.getElementById('editDeptModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            const data = JSON.parse(btn.getAttribute('data-dept'));

            document.getElementById('edit_dept_id').value    = data.id;
            document.getElementById('edit_name').value       = data.name;
            document.getElementById('edit_code').value       = data.code;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_status').value     = data.status;
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>