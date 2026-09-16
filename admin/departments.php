<?php
/**
 * Admin - Hospital Departments CRUD
 */
$pageTitle = "Departments";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

// Add Department Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = sanitize($_POST['name'] ?? '');
    $desc  = sanitize($_POST['description'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } elseif (empty($name)) {
        $error = "Department name is required.";
    } else {
        try {
            $stmtIns = $db->prepare("INSERT INTO departments (name, description, status) VALUES (?, ?, 'active')");
            $stmtIns->execute([$name, $desc]);
            logAudit($_SESSION['user_id'], "Added Department {$name}", 'Departments');
            setFlashMessage('success', "Department {$name} added successfully.");
            header('Location: departments.php');
            exit();
        } catch (\Exception $e) {
            $error = "Department creation error: " . $e->getMessage();
        }
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Add New Department</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

                        <form action="departments.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Department Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">Save Department</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Active Hospital Departments</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Department Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?= $dept['id'] ?></td>
                                        <td class="fw-bold"><?= sanitize($dept['name']) ?></td>
                                        <td><?= sanitize($dept['description'] ?: 'N/A') ?></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>