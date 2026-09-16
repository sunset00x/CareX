<?php
/**
 * CarePlus Hospital Public Landing Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getConnection();

// Fetch Active Departments for Display
$stmtDept = $db->query("SELECT * FROM departments WHERE status = 'active' LIMIT 6");
$departments = $stmtDept->fetchAll();

// Fetch Active Doctors for Public Showcase
$stmtDocs = $db->query("
    SELECT d.*, u.name, u.profile_image, dept.name as department_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN departments dept ON d.department_id = dept.id 
    WHERE u.status = 'active' LIMIT 4
");
$doctors = $stmtDocs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarePlus Hospital - Smart Healthcare, Better Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #0284c7 0%, #0f766e 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(2, 132, 199, 0.1);
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary fs-4" href="index.php">
            <i class="bi bi-heart-pulse-fill me-2"></i>CarePlus Hospital
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto me-4 fw-medium">
                <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link" href="#doctors">Doctors</a></li>
                <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
            </ul>
            <div class="d-flex gap-2">
                <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill">Login</a>
                <a href="register.php" class="btn btn-primary px-4 rounded-pill">Register</a>
            </div>
        </div>
    </div>
</nav>

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

<!-- Stats Bar -->
<section class="bg-white py-4 shadow-sm">
    <div class="container">
        <div class="row text-center">
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0">10,000+</h3>
                <small class="text-muted text-uppercase fw-semibold">Satisfied Patients</small>
            </div>
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0">100+</h3>
                <small class="text-muted text-uppercase fw-semibold">Specialist Doctors</small>
            </div>
            <div class="col-6 col-md-3 border-end">
                <h3 class="fw-bold text-primary mb-0">20+</h3>
                <small class="text-muted text-uppercase fw-semibold">Medical Departments</small>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="fw-bold text-primary mb-0">50,000+</h3>
                <small class="text-muted text-uppercase fw-semibold">Successful Consultations</small>
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
            <?php foreach ($departments as $dept): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-3">
                        <div class="card-body">
                            <div class="feature-icon"><i class="bi bi-building"></i></div>
                            <h5 class="card-title fw-bold"><?= sanitize($dept['name']) ?></h5>
                            <p class="card-text text-muted"><?= sanitize($dept['description']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white py-4 border-top border-secondary">
    <div class="container text-center">
        <p class="mb-0 text-muted">&copy; <?= date('Y') ?> CarePlus Hospital Management System. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>