<?php
/**
 * Laboratory Technician Main Dashboard
 * CareX Smart Hospital Management System
 */
$pageTitle = "Laboratory Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(['admin', 'laboratory']);

$db = Database::getConnection();

// Metrics
$pendingCount = $db->query("SELECT COUNT(*) FROM lab_test_orders WHERE status IN ('Requested', 'Sample Collected', 'In Testing')")->fetchColumn();
$completedToday = $db->query("SELECT COUNT(*) FROM lab_test_orders WHERE status = 'Completed' AND DATE(completed_at) = CURDATE()")->fetchColumn();
$criticalCount = $db->query("SELECT COUNT(*) FROM lab_test_orders WHERE is_critical = 1 AND status = 'Completed'")->fetchColumn();
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Laboratory Control Console</h2>
                <p class="text-muted mb-0">Diagnostic analytics, STAT queue tracking, and specimen logs.</p>
            </div>
            <a href="test-requests.php" class="btn btn-primary fw-bold rounded-pill px-4">
                <i class="bi bi-list-task me-1"></i> Open Lab Worklist
            </a>
        </div>

        <!-- Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Active Test Queue</h6>
                            <h2 class="display-5 fw-bold mb-0"><?= $pendingCount ?></h2>
                        </div>
                        <i class="bi bi-hourglass-split display-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Completed Today</h6>
                            <h2 class="display-5 fw-bold mb-0"><?= $completedToday ?></h2>
                        </div>
                        <i class="bi bi-check-circle-fill display-4 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-danger text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 text-uppercase fw-bold mb-1">Critical Panic Values</h6>
                            <h2 class="display-5 fw-bold mb-0"><?= $criticalCount ?></h2>
                        </div>
                        <i class="bi bi-exclamation-diamond-fill display-4 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>