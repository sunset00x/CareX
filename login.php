<?php
/**
 * CarePlus Hospital - Universal Multi-Role Login Portal
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$selectedRole = sanitize($_GET['role'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "Invalid CSRF Request Token.";
    } elseif (empty($email) || empty($password)) {
        $error = "Please fill in all mandatory fields.";
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account is currently " . $user['status'] . ". Contact support.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['user_name']    = $user['name'];
                $_SESSION['user_email']   = $user['email'];
                $_SESSION['user_role']    = $user['role'];
                $_SESSION['user_profile'] = $user['profile_image'];

                logAudit($user['id'], 'User Login', 'Authentication', $user['id']);
                redirectBasedOnRole();
            }
        } else {
            $error = "Invalid email credentials or incorrect password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Department Login - CarePlus HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .role-card {
            cursor: pointer;
            transition: all 0.25s ease-in-out;
            border: 2px solid transparent;
        }
        .role-card:hover, .role-card.active {
            border-color: #0284c7;
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        }
    </style>
</head>
<body class="bg-light py-5">

<div class="container">
    <div class="text-center mb-5">
        <a href="index.php" class="text-decoration-none">
            <h2 class="fw-bold text-primary"><i class="bi bi-heart-pulse-fill me-2"></i>CarePlus HMS Gateway</h2>
        </a>
        <p class="text-muted">Select your hospital department portal to sign in</p>
    </div>

    <!-- Quick Department Switcher Tabs -->
    <div class="row g-3 mb-5 justify-content-center">
        <div class="col-6 col-md-2">
            <div class="card role-card shadow-sm text-center p-3 rounded-4 bg-white" onclick="selectPortal('admin@careplus.test', 'admin')">
                <i class="bi bi-shield-lock-fill text-danger fs-1"></i>
                <h6 class="fw-bold mt-2 mb-0">Admin</h6>
                <small class="text-muted">Management</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card role-card shadow-sm text-center p-3 rounded-4 bg-white" onclick="selectPortal('doctor@careplus.test', 'doctor')">
                <i class="bi bi-person-md text-primary fs-1"></i>
                <h6 class="fw-bold mt-2 mb-0">Doctor</h6>
                <small class="text-muted">Clinical EMR</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card role-card shadow-sm text-center p-3 rounded-4 bg-white" onclick="selectPortal('patient@careplus.test', 'patient')">
                <i class="bi bi-person-heart text-success fs-1"></i>
                <h6 class="fw-bold mt-2 mb-0">Patient</h6>
                <small class="text-muted">Self-Service</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card role-card shadow-sm text-center p-3 rounded-4 bg-white" onclick="selectPortal('lab@careplus.test', 'laboratory')">
                <i class="bi bi-virus text-warning fs-1"></i>
                <h6 class="fw-bold mt-2 mb-0">Laboratory</h6>
                <small class="text-muted">Diagnostics</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card role-card shadow-sm text-center p-3 rounded-4 bg-white" onclick="selectPortal('billing@careplus.test', 'billing')">
                <i class="bi bi-receipt-cutoff text-info fs-1"></i>
                <h6 class="fw-bold mt-2 mb-0">Billing</h6>
                <small class="text-muted">Invoicing</small>
            </div>
        </div>
    </div>

    <!-- Login Form Card -->
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <?php displayFlashMessage(); ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= sanitize($error) ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" id="emailInput" class="form-control form-control-lg" placeholder="name@careplus.test" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" id="passInput" class="form-control form-control-lg" value="Password123!" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Sign In To Portal</button>
                    </form>

                    <div class="mt-4 border-top pt-3 text-center">
                        <p class="mb-1 text-muted">Patient registration?</p>
                        <a href="register.php" class="fw-bold text-decoration-none">Create Patient Account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPortal(email, role) {
    document.getElementById('emailInput').value = email;
    document.getElementById('passInput').value = 'Password123!';
}
</script>

</body>
</html>