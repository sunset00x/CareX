<?php
/**
 * Patient - Manage Personal Profile Settings
 */
$pageTitle = "My Profile";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$user = currentUser();
$db = Database::getConnection();

// Fetch Profile Data
$stmtProfile = $db->prepare("
    SELECT u.*, p.patient_id as patient_code, p.date_of_birth, p.gender, p.blood_group, p.address, p.emergency_contact, p.emergency_phone
    FROM users u
    JOIN patients p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmtProfile->execute([$user['id']]);
$profile = $stmtProfile->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = sanitize($_POST['name'] ?? '');
    $phone            = sanitize($_POST['phone'] ?? '');
    $address          = sanitize($_POST['address'] ?? '');
    $emergencyContact = sanitize($_POST['emergency_contact'] ?? '');
    $emergencyPhone   = sanitize($_POST['emergency_phone'] ?? '');
    $token            = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Token Validation Failed.";
    } elseif (empty($name)) {
        $error = "Name cannot be empty.";
    } else {
        try {
            $db->beginTransaction();

            // Update user table
            $stmtU = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmtU->execute([$name, $phone, $user['id']]);

            // Update patient table
            $stmtP = $db->prepare("UPDATE patients SET address = ?, emergency_contact = ?, emergency_phone = ? WHERE user_id = ?");
            $stmtP->execute([$address, $emergencyContact, $emergencyPhone, $user['id']]);

            $db->commit();
            $_SESSION['user_name'] = $name;

            logAudit($user['id'], 'Updated Profile', 'User Profile', $user['id']);
            setFlashMessage('success', 'Profile information updated successfully.');
            header('Location: profile.php');
            exit();
        } catch (\Exception $e) {
            $db->rollBack();
            $error = "Update error: " . $e->getMessage();
        }
    }
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="fw-bold mb-0">My Personal Profile</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= sanitize($error) ?></div>
                        <?php endif; ?>

                        <form action="profile.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Patient Identification ID</label>
                                    <input type="text" class="form-control bg-light" value="<?= sanitize($profile['patient_code']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email Address</label>
                                    <input type="text" class="form-control bg-light" value="<?= sanitize($profile['email']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Full Name *</label>
                                    <input type="text" name="name" class="form-control" value="<?= sanitize($profile['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?= sanitize($profile['phone']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Date of Birth</label>
                                    <input type="text" class="form-control bg-light" value="<?= formatDate($profile['date_of_birth']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Blood Group</label>
                                    <input type="text" class="form-control bg-light" value="<?= sanitize($profile['blood_group'] ?: 'N/A') ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Residential Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?= sanitize($profile['address']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Emergency Contact Person</label>
                                    <input type="text" name="emergency_contact" class="form-control" value="<?= sanitize($profile['emergency_contact']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Emergency Phone</label>
                                    <input type="text" name="emergency_phone" class="form-control" value="<?= sanitize($profile['emergency_phone']) ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Update Profile Details</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>