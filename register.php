<?php
/**
 * Patient Self-Registration Control
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) redirectBasedOnRole();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = sanitize($_POST['name'] ?? '');
    $email            = sanitize($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $phone            = sanitize($_POST['phone'] ?? '');
    $dob              = sanitize($_POST['date_of_birth'] ?? '');
    $gender           = sanitize($_POST['gender'] ?? '');
    $bloodGroup       = sanitize($_POST['blood_group'] ?? '');
    $address          = sanitize($_POST['address'] ?? '');
    $emergencyContact = sanitize($_POST['emergency_contact'] ?? '');
    $emergencyPhone   = sanitize($_POST['emergency_phone'] ?? '');
    $token            = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification failed.";
    } elseif (empty($name) || empty($email) || empty($password) || empty($dob) || empty($gender)) {
        $error = "Please complete all mandatory required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $db = Database::getConnection();

        // Check Unique Email constraint
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $error = "This email address is already registered.";
        } else {
            try {
                $db->beginTransaction();

                // 1. Create Base Security User Account
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtUser = $db->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, 'patient', ?, 'active')");
                $stmtUser->execute([$name, $email, $hashedPassword, $phone]);
                $userId = $db->lastInsertId();

                // 2. Create Patient Profile
                $patientCode = generatePatientID();
                $stmtPatient = $db->prepare("INSERT INTO patients (user_id, patient_id, date_of_birth, gender, blood_group, address, emergency_contact, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtPatient->execute([$userId, $patientCode, $dob, $gender, $bloodGroup, $address, $emergencyContact, $emergencyPhone]);

                $db->commit();

                logAudit($userId, 'Self-Registration', 'Authentication', $userId);
                setFlashMessage('success', 'Registration successful! You can now log in.');
                header('Location: login.php');
                exit();

            } catch (\Exception $e) {
                $db->rollBack();
                $error = "Account registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Registration - CarePlus HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-1">Create Patient Account</h3>
                    <p class="text-muted mb-4">Register your information for instant online appointment booking</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= sanitize($error) ?></div>
                    <?php endif; ?>

                    <form action="register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <h5 class="fw-bold text-primary mb-3">Account Details</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>

                        <h5 class="fw-bold text-primary mb-3 mt-4">Medical Demographics</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" name="date_of_birth" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Unknown</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Full Residential Address</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <h5 class="fw-bold text-primary mb-3 mt-4">Emergency Contact</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" name="emergency_phone" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Complete Registration</button>
                    </form>

                    <div class="mt-4 text-center">
                        <p class="mb-0 text-muted">Already registered? <a href="login.php" class="fw-bold text-decoration-none">Sign In</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>