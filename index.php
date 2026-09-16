<?php
/**
 * CarePlus Hospital Public Landing Page (With Global Header & Footer Includes)
 */
require_once __DIR__ . '/includes/public_header.php';

// Fetch Active Departments
$stmtDept = $db->query("SELECT * FROM departments WHERE status = 'active' LIMIT 6");
$departments = $stmtDept->fetchAll();

// Fetch Active Doctors Showcase
$stmtDocs = $db->query("
    SELECT d.*, u.name, u.profile_image, dept.name as department_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN departments dept ON d.department_id = dept.id 
    WHERE u.status = 'active' LIMIT 6
");
$doctors = $stmtDocs->fetchAll();

// KPI Metrics
$totalPatients = (int)($db->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?: 0);
$totalDoctors  = (int)($db->query("SELECT COUNT(*) FROM doctors")->fetchColumn() ?: 0);
$totalDepts    = (int)($db->query("SELECT COUNT(*) FROM departments WHERE status='active'")->fetchColumn() ?: 0);
?>

<!-- Hero Banner -->
<section id="home" class="hero-section">
    <div class="container text-center text-lg-start">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Smart Healthcare, Better Care</h1>
                <p class="lead mb-4 opacity-90">Experience seamless healthcare management. Connect directly with top specialists, manage your health records, view lab reports, and schedule appointments online.</p>
                <div class="d-flex gap-3 justify-content-center justify-content-lg-start">
                    <a href="register.php" class="btn btn-light text-primary btn-lg px-4 fw-bold rounded-pill">Book Appointment</a>
                    <a href="#doctors" class="btn btn-outline-light btn-lg px-4 rounded-pill">Find a Doctor</a>
                </div>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0 text-center">
                <i class="bi bi-hospital text-white opacity-25" style="font-size: 15rem;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Live Stats Bar -->
<section class="bg-white py-4 shadow-sm">
    <div class="container">
        <div class="row text-center">
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0"><?= number_format($totalPatients + 1000) ?>+</h3>
                <small class="text-muted text-uppercase fw-semibold">Satisfied Patients</small>
            </div>
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0"><?= $totalDoctors ?></h3>
                <small class="text-muted text-uppercase fw-semibold">Specialist Doctors</small>
            </div>
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0"><?= $totalDepts ?></h3>
                <small class="text-muted text-uppercase fw-semibold">Medical Departments</small>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="fw-bold text-primary mb-0">24/7</h3>
                <small class="text-muted text-uppercase fw-semibold">Emergency Support</small>
            </div>
        </div>
    </div>
</section>

<!-- Departments Section -->
<section id="departments" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Our Medical Departments</h2>
            <p class="text-muted">Providing world-class specialized care across diverse medical disciplines.</p>
        </div>
        <div class="row g-4">
            <?php if (!empty($departments)): foreach ($departments as $dept): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-3">
                        <div class="card-body">
                            <div class="feature-icon"><i class="bi bi-building"></i></div>
                            <h5 class="card-title fw-bold"><?= sanitize($dept['name']) ?></h5>
                            <p class="card-text text-muted"><?= sanitize($dept['description'] ?: 'Specialized clinical care.') ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center text-muted">No active departments.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Doctors Showcase Section -->
<section id="doctors" class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Meet Our Specialist Doctors</h2>
            <p class="text-muted">Consult with experienced medical professionals.</p>
        </div>
        <div class="row g-4">
            <?php if (!empty($doctors)): foreach ($doctors as $doc): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-3 text-center">
                        <div class="card-body">
                            <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($doc['profile_image']) ?>" class="rounded-circle border mb-3 object-fit-cover" width="80" height="80">
                            <h5 class="fw-bold mb-1">Dr. <?= sanitize($doc['name']) ?></h5>
                            <span class="badge bg-primary-subtle text-primary mb-2"><?= sanitize($doc['department_name']) ?></span>
                            <p class="text-muted small mb-3"><?= sanitize($doc['specialization']) ?></p>
                            <a href="login.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">Book Consultation</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center text-muted">No doctors currently showcaseable.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Services Overview Section -->
<section id="services" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Healthcare Services</h2>
            <p class="text-muted">Integrated medical platform capabilities for doctors and patients.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body">
                        <div class="feature-icon"><i class="bi bi-calendar-check"></i></div>
                        <h5 class="fw-bold">Online Scheduling</h5>
                        <p class="text-muted">Book real-time appointment slots with conflict checks.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body">
                        <div class="feature-icon"><i class="bi bi-file-earmark-medical"></i></div>
                        <h5 class="fw-bold">Digital EMR Records</h5>
                        <p class="text-muted">Centralized patient medical histories and diagnostic reports.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <div class="card-body">
                        <div class="feature-icon"><i class="bi bi-receipt"></i></div>
                        <h5 class="fw-bold">Transparent Billing</h5>
                        <p class="text-muted">Instant invoice receipts and payment tracking.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>