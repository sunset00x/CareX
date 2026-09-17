<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
$db = Database::getConnection();
$sysSettings = $db->query("SELECT * FROM system_settings WHERE id = 1")->fetch() ?: [];
$hospName = $sysSettings['hospital_name'] ?? 'CareX Hospital';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($hospName) ?> - Smart Healthcare, Better Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
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
        .hover-white:hover {
            color: #ffffff !important;
        }
    </style>
</head>
<body>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary fs-4" href="<?= BASE_URL ?>index.php">
            <i class="bi bi-heart-pulse-fill me-2"></i><?= sanitize($hospName) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto me-4 fw-medium">
                <li class="nav-item"><a class="nav-link active" href="<?= BASE_URL ?>index.php#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php#doctors">Doctors</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php#services">Services</a></li>
            </ul>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>patient_login.php" class="btn btn-outline-primary px-4 rounded-pill">Login</a>
                <a href="<?= BASE_URL ?>register.php" class="btn btn-primary px-4 rounded-pill">Register</a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content flex-grow-1">