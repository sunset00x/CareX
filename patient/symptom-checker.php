<?php
/**
 * Patient - Automated Symptom Triage & Specialty Matcher
 * CarePlus Smart Hospital Management System
 */
$pageTitle = "AI Symptom Triage & Matcher";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');

$db = Database::getConnection();
$matchedDepartment = null;
$recommendedDoctors = [];
$severity = '';
$analysisDone = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token     = $_POST['csrf_token'] ?? '';
    $symptoms  = strtolower(sanitize($_POST['symptoms'] ?? ''));
    $severity  = sanitize($_POST['severity'] ?? 'Mild');
    $duration  = sanitize($_POST['duration'] ?? '');

    if (verifyCSRFToken($token) && !empty($symptoms)) {
        // Clinical Keyword Matching Engine
        $keywordMap = [
            'Cardiology' => ['chest pain', 'heart', 'palpitations', 'shortness of breath', 'bp', 'blood pressure', 'cardiac', 'angina'],
            'Neurology' => ['headache', 'dizziness', 'seizure', 'numbness', 'migraine', 'paralysis', 'brain', 'fainting'],
            'Orthopedics' => ['bone', 'joint pain', 'fracture', 'back pain', 'knee', 'ligament', 'spine', 'muscle strain'],
            'Gastroenterology' => ['stomach pain', 'acid reflux', 'vomiting', 'diarrhea', 'nausea', 'digestion', 'liver', 'abdomen'],
            'Dermatology' => ['rash', 'skin', 'itching', 'allergy', 'eczema', 'acne', 'dermatitis'],
            'Pediatrics' => ['child', 'infant', 'fever in kid', 'pediatric', 'vaccination'],
            'General Medicine' => ['fever', 'flu', 'cold', 'cough', 'fatigue', 'weakness', 'body ache']
        ];

        $matchedDeptName = 'General Medicine'; // Default fallback department

        foreach ($keywordMap as $deptName => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($symptoms, $kw)) {
                    $matchedDeptName = $deptName;
                    break 2;
                }
            }
        }

        // Retrieve matched department from DB
        $stmtDept = $db->prepare("SELECT * FROM departments WHERE name LIKE ? AND status = 'active' LIMIT 1");
        $stmtDept->execute(["%{$matchedDeptName}%"]);
        $matchedDepartment = $stmtDept->fetch();

        if ($matchedDepartment) {
            // Find active specialists in this department
            $stmtDocs = $db->prepare("
                SELECT d.id as doctor_table_id, d.specialization, d.qualification, d.consultation_fee, u.name, u.profile_image 
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                WHERE d.department_id = ? AND u.status = 'active'
            ");
            $stmtDocs->execute([$matchedDepartment['id']]);
            $recommendedDoctors = $stmtDocs->fetchAll();

            // Log Triage Query for Medical History
            $patientStmt = $db->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
            $patientStmt->execute([$_SESSION['user_id']]);
            $patientObj = $patientStmt->fetch();

            if ($patientObj) {
                $stmtLog = $db->prepare("INSERT INTO symptom_triage_logs (patient_id, reported_symptoms, severity_level, suggested_department_id) VALUES (?, ?, ?, ?)");
                $stmtLog->execute([$patientObj['id'], $symptoms, $severity, $matchedDepartment['id']]);
            }
        }

        $analysisDone = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - CarePlus HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-heart-pulse-fill me-2"></i>Patient Dashboard</a>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to Portal</a>
    </div>
</nav>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Triage Assessment Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h4 class="fw-bold mb-1 text-primary"><i class="bi bi-cpu me-2"></i>Automated Symptom Triage Engine</h4>
                    <p class="text-muted small mb-0">Describe your symptoms to automatically identify the most suitable medical department and specialist.</p>
                </div>
                <div class="card-body p-4">
                    <form action="symptom-checker.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Describe Your Symptoms *</label>
                            <textarea name="symptoms" class="form-control" rows="3" placeholder="e.g., Severe chest pain, shortness of breath, dizziness since morning..." required><?= sanitize($_POST['symptoms'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Severity Assessment *</label>
                                <select name="severity" class="form-select" required>
                                    <option value="Mild" <?= ($severity === 'Mild') ? 'selected' : '' ?>>Mild (Manageable discomfort)</option>
                                    <option value="Moderate" <?= ($severity === 'Moderate') ? 'selected' : '' ?>>Moderate (Interferes with daily tasks)</option>
                                    <option value="Severe" <?= ($severity === 'Severe') ? 'selected' : '' ?>>Severe (High pain / distressing)</option>
                                    <option value="Emergency" <?= ($severity === 'Emergency') ? 'selected' : '' ?>>Emergency (Requires immediate attention)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Duration</label>
                                <input type="text" name="duration" class="form-control" placeholder="e.g. 2 days, 5 hours" value="<?= sanitize($_POST['duration'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow-sm">
                            Analyze Symptoms & Match Doctor <i class="bi bi-magic ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Analysis Results View -->
            <?php if ($analysisDone): ?>
                <?php if ($severity === 'Emergency'): ?>
                    <div class="alert alert-danger border-0 shadow-sm p-4 rounded-4 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-octagon-fill fs-1 me-3"></i>
                            <div>
                                <h5 class="fw-bold mb-1">Emergency Level Warning</h5>
                                <p class="mb-0">If you are experiencing severe, life-threatening symptoms, please call our 24/7 hotline immediately or proceed to the Emergency Room.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-4 border-success">
                    <div class="card-body p-4">
                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold mb-2"><i class="bi bi-check-circle-fill me-1"></i> Match Found</span>
                        <h3 class="fw-bold text-dark mb-2">Recommended Department: <?= sanitize($matchedDepartment['name'] ?? 'General Medicine') ?></h3>
                        <p class="text-muted mb-4"><?= sanitize($matchedDepartment['description'] ?? 'Specialized diagnosis and clinical management.') ?></p>

                        <h5 class="fw-bold mb-3">Available Department Specialists</h5>

                        <div class="row g-3">
                            <?php if (!empty($recommendedDoctors)): foreach ($recommendedDoctors as $doc): ?>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center mb-2">
                                                <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($doc['profile_image']) ?>" class="rounded-circle me-2 object-fit-cover" width="50" height="50">
                                                <div>
                                                    <h6 class="fw-bold mb-0">Dr. <?= sanitize($doc['name']) ?></h6>
                                                    <small class="text-primary fw-semibold"><?= sanitize($doc['specialization']) ?></small>
                                                </div>
                                            </div>
                                            <p class="small text-muted mb-2"><?= sanitize($doc['qualification']) ?></p>
                                        </div>
                                        <div class="pt-2 border-top d-flex align-items-center justify-content-between">
                                            <span class="fw-bold text-success"><?= formatCurrency($doc['consultation_fee']) ?></span>
                                            <a href="appointments.php?doctor_id=<?= $doc['doctor_table_id'] ?>" class="btn btn-primary btn-sm rounded-pill fw-bold">Book Slot</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; else: ?>
                                <div class="col-12 text-muted">No specialists are directly assigned to this department right now. You may book with our General Practitioners.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>