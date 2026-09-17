<?php
/**
 * Admin - Blood Bank Inventory Control
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "Blood Bank Registry";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $group = sanitize($_POST['blood_group'] ?? '');
    $units = (int)($_POST['units'] ?? 0);

    $stmtUpd = $db->prepare("UPDATE blood_bank SET units_available = units_available + ? WHERE blood_group = ?");
    $stmtUpd->execute([$units, $group]);
    setFlashMessage('success', "Updated blood units for {$group}.");
    header('Location: blood-bank.php');
    exit();
}

$bloodData = $db->query("SELECT * FROM blood_bank ORDER BY blood_group ASC")->fetchAll();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <?php displayFlashMessage(); ?>
        <h2 class="fw-bold mb-1">Blood Bank Inventory</h2>
        <p class="text-muted mb-4">Real-time reserve levels for emergency blood transfusions.</p>

        <div class="row g-4">
            <?php foreach ($bloodData as $b): 
                $isCritical = $b['units_available'] < 5;
            ?>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3 <?= $isCritical ? 'bg-danger-subtle' : '' ?>">
                        <div class="card-body">
                            <h1 class="fw-bold text-danger mb-0"><?= $b['blood_group'] ?></h1>
                            <h3 class="fw-bold text-dark my-2"><?= $b['units_available'] ?> Units</h3>
                            <span class="badge bg-<?= $isCritical ? 'danger' : 'success' ?> mb-3">
                                <?= $isCritical ? 'Critical Shortage' : 'Stocked' ?>
                            </span>

                            <form action="blood-bank.php" method="POST" class="input-group input-group-sm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="blood_group" value="<?= $b['blood_group'] ?>">
                                <input type="number" name="units" class="form-control" placeholder="+/- Units" required>
                                <button type="submit" class="btn btn-danger fw-bold">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>