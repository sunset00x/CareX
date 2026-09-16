<?php
/**
 * User Authentication Interface (Login)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';

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
                // Secure Session Initialization
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
    <title>Login - CarePlus HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-heart-pulse-fill text-primary display-4"></i>
                        <h3 class="fw-bold mt-2">Welcome Back</h3>
                        <p class="text-muted">Sign in to access your portal</p>
                    </div>

                    <?php displayFlashMessage(); ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= sanitize($error) ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Sign In</button>
                    </form>

                    <div class="mt-4 border-top pt-3 text-center">
                        <p class="mb-1 text-muted">Don't have a patient account?</p>
                        <a href="register.php" class="fw-bold text-decoration-none">Register Patient Account</a>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded border text-muted small">
                        <strong>Demo Logins (Password: <code>Password123!</code>):</strong><br>
                        Admin: <code>admin@careplus.test</code><br>
                        Doctor: <code>doctor@careplus.test</code><br>
                        Patient: <code>patient@careplus.test</code><br>
                        Lab: <code>lab@careplus.test</code> | Billing: <code>billing@careplus.test</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>