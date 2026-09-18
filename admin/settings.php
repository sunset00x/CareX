<?php
ob_start();

$pageTitle = "System Settings & Branding";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verifyCSRFToken($token)) {
        $name     = sanitize($_POST['hospital_name'] ?? '');
        $tagline  = sanitize($_POST['tagline'] ?? '');
        $phone    = sanitize($_POST['emergency_phone'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $address  = sanitize($_POST['address'] ?? '');
        $currency = sanitize($_POST['currency_symbol'] ?? 'NPR');

        try {
            $stmtUpd = $db->prepare("
                INSERT INTO system_settings (id, hospital_name, tagline, emergency_phone, email, address, currency_symbol)
                VALUES (1, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    hospital_name = VALUES(hospital_name),
                    tagline = VALUES(tagline),
                    emergency_phone = VALUES(emergency_phone),
                    email = VALUES(email),
                    address = VALUES(address),
                    currency_symbol = VALUES(currency_symbol)
            ");
            $stmtUpd->execute([$name, $tagline, $phone, $email, $address, $currency]);

            logAudit($_SESSION['user_id'], "Updated System Hospital Branding Settings", 'Settings');
            setFlashMessage('success', 'System settings updated successfully.');
            header('Location: settings.php');
            exit();
        } catch (\PDOException $e) {
            $error = "Failed to update settings: " . $e->getMessage();
        }
    } else {
        $error = "CSRF Token Validation Failed.";
    }
}

try {
    $settings = $db->query("SELECT * FROM system_settings WHERE id = 1")->fetch();
} catch (\PDOException $e) {
    $settings = [];
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="fw-bold mb-0"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Hospital System & Branding Settings</h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="settings.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hospital Name *</label>
                                <input type="text" name="hospital_name" class="form-control" value="<?= sanitize($settings['hospital_name'] ?? 'CareX Hospital') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tagline</label>
                                <input type="text" name="tagline" class="form-control" value="<?= sanitize($settings['tagline'] ?? '') ?>">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">24/7 Emergency Line *</label>
                                    <input type="text" name="emergency_phone" class="form-control" value="<?= sanitize($settings['emergency_phone'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">System Email *</label>
                                    <input type="email" name="email" class="form-control" value="<?= sanitize($settings['email'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Physical Address</label>
                                    <input type="text" name="address" class="form-control" value="<?= sanitize($settings['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Currency Code *</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?= sanitize($settings['currency_symbol'] ?? 'NPR') ?>" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">Save Hospital Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>