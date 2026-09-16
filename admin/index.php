<?php
/**
 * Master Admin Analytics Dashboard
 */
$pageTitle = "Admin System Analytics";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('admin');

$db = Database::getConnection();

// Core Analytics KPI Aggregation Queries
$countPatients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$countDoctors  = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$countAppts    = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalRev      = $db->query("SELECT SUM(paid_amount) FROM bills")->fetchColumn() ?: 0.00;

// Department Patient Distribution for Chart.js
$stmtDeptDist = $db->query("
    SELECT d.name, COUNT(a.id) as appt_count 
    FROM departments d 
    LEFT JOIN doctors doc ON doc.department_id = d.id 
    LEFT JOIN appointments a ON a.doctor_id = doc.id 
    GROUP BY d.id
");
$deptDistData = $stmtDeptDist->fetchAll();

$deptLabels = json_encode(array_column($deptDistData, 'name'));
$deptCounts = json_encode(array_column($deptDistData, 'appt_count'));
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <h2 class="fw-bold mb-1">Administrative Overview</h2>
        <p class="text-muted mb-4">Comprehensive analytics and operational controls.</p>

        <!-- KPI Metrics Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-primary-subtle text-primary me-3"><i class="bi bi-people"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countPatients ?></h3>
                            <small class="text-muted fw-semibold">Total Patients</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-info-subtle text-info me-3"><i class="bi bi-person-md"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countDoctors ?></h3>
                            <small class="text-muted fw-semibold">Active Doctors</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-warning-subtle text-warning me-3"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $countAppts ?></h3>
                            <small class="text-muted fw-semibold">Total Bookings</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon bg-success-subtle text-success me-3"><i class="bi bi-bank"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= formatCurrency($totalRev) ?></h3>
                            <small class="text-muted fw-semibold">Revenue</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js Visualization Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Appointments by Department</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="deptChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">System Security Logs</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $logs = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();
                                foreach ($logs as $log):
                                ?>
                                    <tr>
                                        <td><small><?= formatDate($log['created_at']) ?></small></td>
                                        <td class="fw-semibold"><?= sanitize($log['action']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= sanitize($log['module']) ?></span></td>
                                        <td><code><?= sanitize($log['ip_address']) ?></code></td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('deptChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= $deptLabels ?>,
            datasets: [{
                data: <?= $deptCounts ?>,
                backgroundColor: ['#0284c7', '#0f766e', '#eab308', '#ef4444', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>