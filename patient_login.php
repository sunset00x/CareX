<?php
/**
 * Dedicated Patient Login Gateway
 * CarePlus Smart Hospital Management System
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if patient is already logged in
if (isLoggedIn()) {
    if (($_SESSION['user_role'] ?? '') === 'patient') {
        header('Location: ' . BASE_URL . 'patient/index.php');
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Security Token Validation Failed. Please try again.";
    } elseif (empty($email) || empty($password)) {
        $error = "Please enter your registered email address and password.";
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // STAGE 1: Check Account Status
                if ($user['status'] !== 'active') {
                    $error = "Your patient account is suspended or inactive. Please contact support.";
                } 
                // STAGE 2: Patient Role Security Guard
                elseif ($user['role'] !== 'patient') {
                    $error = "Access Denied: This login portal is strictly for Patients. Hospital staff must use the Staff Portal.";
                } 
                // STAGE 3: Successful Patient Login & Session Setup
                else {
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['user_name']    = $user['name'];
                    $_SESSION['user_email']   = $user['email'];
                    $_SESSION['user_role']    = $user['role'];
                    $_SESSION['user_profile'] = $user['profile_image'];

                    // Update session tracking & last seen timestamp
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $stmtBeat = $db->prepare("UPDATE users SET last_seen = NOW(), ip_address = ?, user_agent = ?, force_logout = 0 WHERE id = ?");
                    $stmtBeat->execute([$ip, $agent, $user['id']]);

                    logAudit($user['id'], "Patient Logged In Successfully", 'Auth', $user['id']);

                    // Redirect directly to Patient Dashboard or intended appointment booking
                    $redirectDoc = (int)($_GET['doctor_id'] ?? 0);
                    if ($redirectDoc > 0) {
                        header('Location: ' . BASE_URL . 'patient/appointments.php?doctor_id=' . $redirectDoc);
                    } else {
                        header('Location: ' . BASE_URL . 'patient/index.php');
                    }
                    exit();
                }
            } else {
                $error = "Invalid email address or password combination.";
            }
        } catch (\Exception $e) {
            $error = "Authentication System Error: " . $e->getMessage();
        }
    }
}

// Now include public header layout
require_once __DIR__ . '/includes/public_header.php';
?>

<div class="container py-5 my-4">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white text-center py-4 border-0">
                    <div class="bg-white text-primary rounded-circle d-inline-flex p-3 mb-2 shadow-sm">
                        <i class="bi bi-person-heart fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Patient Portal Login</h3>
                    <p class="text-white-50 small mb-0">Access your health records & book appointments</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php displayFlashMessage(); ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <div><?= sanitize($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="patient_login.php<?= isset($_GET['doctor_id']) ? '?doctor_id=' . (int)$_GET['doctor_id'] : '' ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="patient@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label fw-semibold mb-0">Password</label>
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow-sm mb-3">
                            Login to Patient Dashboard <i class="bi bi-arrow-right ms-1"></i>
                        </button>

                        <div class="text-center">
                            <p class="text-muted small mb-0">Don't have a patient account yet?</p>
                            <a href="<?= BASE_URL ?>register.php" class="fw-bold text-primary text-decoration-none">Register as New Patient</a>
                        </div>
                    </form>
                </div>

                <div class="card-footer bg-light text-center py-3 border-0">
                    <small class="text-muted">Hospital Staff or Doctor? <a href="<?= BASE_URL ?>login.php" class="text-secondary fw-semibold">Staff Login Portal</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>