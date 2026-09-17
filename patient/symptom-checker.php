<?php
/**
 * Advanced AI Clinical Triage & Specialty Matcher
 * CareX Smart Hospital Management System
 */
$pageTitle = "Advanced Clinical AI Triage";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');

$db = Database::getConnection();
$matchedDepartment = null;
$recommendedDoctors = [];
$analysisResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token          = $_POST['csrf_token'] ?? '';
    $symptomsInput  = strtolower(sanitize($_POST['symptoms'] ?? ''));
    $severityInput  = sanitize($_POST['severity'] ?? 'Mild');
    $durationInput  = sanitize($_POST['duration'] ?? '');
    $patientAge     = (int)($_POST['age'] ?? 30);
    $patientSex     = sanitize($_POST['sex'] ?? 'Unspecified');
    $preConditions  = $_POST['pre_conditions'] ?? [];

    if (verifyCSRFToken($token) && !empty($symptomsInput)) {
        
        // ===================================================
        // 1. RED-FLAG EMERGENCY DETECTOR (Critical Safety Net)
        // ===================================================
        $redFlags = [
            'stroke' => ['slurred speech', 'face drooping', 'arm weakness', 'sudden numbness'],
            'cardiac_arrest' => ['crushing chest pain', 'pain radiating to arm', 'pain radiating to jaw', 'fainting with chest pain'],
            'anaphylaxis' => ['swollen tongue', 'throat tightening', 'severe allergic reaction'],
            'respiratory_distress' => ['unable to breathe', 'blue lips', 'severe gasping']
        ];

        $isEmergencyRedFlag = false;
        $redFlagTriggered = '';

        foreach ($redFlags as $condition => $triggers) {
            foreach ($triggers as $trigger) {
                if (str_contains($symptomsInput, $trigger)) {
                    $isEmergencyRedFlag = true;
                    $redFlagTriggered = ucwords(str_replace('_', ' ', $condition));
                    break 2;
                }
            }
        }

        // ===================================================
        // 2. CLINICAL DEPARTMENT KNOWLEDGE BASE & WEIGHTING
        // ===================================================
        $clinicalMatrix = [
            'Cardiology' => [
                'primary' => ['chest pain', 'palpitations', 'angina', 'shortness of breath'],
                'secondary' => ['dizziness', 'leg swelling', 'high bp', 'hypertension', 'fatigue'],
                'risk_boost' => in_array('hypertension', $preConditions) || in_array('diabetes', $preConditions) ? 15 : 0
            ],
            'Neurology' => [
                'primary' => ['severe headache', 'seizure', 'migraine', 'paralysis', 'memory loss'],
                'secondary' => ['dizziness', 'tremor', 'numbness', 'blurred vision', 'confusion'],
                'risk_boost' => $patientAge > 60 ? 10 : 0
            ],
            'Orthopedics' => [
                'primary' => ['fracture', 'bone pain', 'joint pain', 'knee swelling', 'dislocation'],
                'secondary' => ['back pain', 'stiffness', 'muscle strain', 'ligament pain'],
                'risk_boost' => 0
            ],
            'Gastroenterology' => [
                'primary' => ['abdominal pain', 'stomach pain', 'vomiting blood', 'acid reflux', 'jaundice'],
                'secondary' => ['nausea', 'diarrhea', 'bloating', 'indigestion', 'constipation'],
                'risk_boost' => 0
            ],
            'Pulmonology' => [
                'primary' => ['chronic cough', 'wheezing', 'asthma', 'coughing blood', 'bronchitis'],
                'secondary' => ['fever', 'chest tightness', 'shallow breathing'],
                'risk_boost' => in_array('asthma', $preConditions) ? 20 : 0
            ],
            'Dermatology' => [
                'primary' => ['skin rash', 'eczema', 'psoriasis', 'hives', 'skin lesion'],
                'secondary' => ['itching', 'skin redness', 'acne', 'dry skin'],
                'risk_boost' => 0
            ],
            'General Medicine' => [
                'primary' => ['fever', 'chills', 'flu', 'body ache', 'viral fever'],
                'secondary' => ['weakness', 'headache', 'fatigue', 'sore throat'],
                'risk_boost' => 0
            ]
        ];

        // Calculate Scores for Each Department
        $scores = [];
        foreach ($clinicalMatrix as $dept => $data) {
            $score = 0;
            // Primary keywords (15 points each)
            foreach ($data['primary'] as $pKey) {
                if (str_contains($symptomsInput, $pKey)) $score += 15;
            }
            // Secondary keywords (5 points each)
            foreach ($data['secondary'] as $sKey) {
                if (str_contains($symptomsInput, $sKey)) $score += 5;
            }
            if ($score > 0) {
                $score += $data['risk_boost'];
            }
            $scores[$dept] = $score;
        }

        // Sort Highest Score First
        arsort($scores);
        $bestMatchDept = array_key_first($scores);
        $maxScore = reset($scores);

        if ($maxScore == 0) {
            $bestMatchDept = 'General Medicine';
            $confidenceScore = 60.00;
        } else {
            $confidenceScore = min(98.00, round(($maxScore / 40) * 100, 2));
        }

        // Calculate Risk Score (0 - 100)
        $riskScore = 20; // base
        if ($severityInput === 'Moderate') $riskScore += 20;
        if ($severityInput === 'Severe') $riskScore += 45;
        if ($severityInput === 'Emergency' || $isEmergencyRedFlag) $riskScore = 95;
        if (!empty($preConditions)) $riskScore += (count($preConditions) * 5);
        $riskScore = min(100, $riskScore);

        // Fetch Matched Department from DB
        $stmtDept = $db->prepare("SELECT * FROM departments WHERE name LIKE ? AND status = 'active' LIMIT 1");
        $stmtDept->execute(["%{$bestMatchDept}%"]);
        $matchedDepartment = $stmtDept->fetch();

        if ($matchedDepartment) {
            $stmtDocs = $db->prepare("
                SELECT d.id as doctor_table_id, d.specialization, d.qualification, d.experience, d.consultation_fee, u.name, u.profile_image 
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                WHERE d.department_id = ? AND u.status = 'active'
            ");
            $stmtDocs->execute([$matchedDepartment['id']]);
            $recommendedDoctors = $stmtDocs->fetchAll();

            // Store Logged Summary for Doctors
            $clinicalSummary = "Patient ({$patientAge}y/o {$patientSex}) reported: '{$symptomsInput}'. Severity: {$severityInput}. Duration: {$durationInput}. Calculated Risk Score: {$riskScore}/100. Key Risk Factors: " . (empty($preConditions) ? 'None' : implode(', ', $preConditions));

            $patientStmt = $db->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
            $patientStmt->execute([$_SESSION['user_id']]);
            $patientObj = $patientStmt->fetch();

            if ($patientObj) {
                $stmtLog = $db->prepare("
                    INSERT INTO symptom_triage_logs 
                    (patient_id, patient_age, patient_sex, reported_symptoms, severity_level, risk_score, confidence_score, suggested_department_id, clinical_summary) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtLog->execute([
                    $patientObj['id'], $patientAge, $patientSex, $symptomsInput, 
                    $severityInput, $riskScore, $confidenceScore, $matchedDepartment['id'], $clinicalSummary
                ]);
            }
        }

        $analysisResult = [
            'is_red_flag' => $isEmergencyRedFlag,
            'red_flag_name' => $redFlagTriggered,
            'confidence' => $confidenceScore,
            'risk_score' => $riskScore,
            'summary' => $clinicalSummary ?? ''
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - CareX HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-cpu-fill me-2"></i>Clinical Intelligence Triage</a>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to Patient Portal</a>
    </div>
</nav>

<div class="container py-2 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <!-- Form Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h4 class="fw-bold mb-1 text-primary"><i class="bi bi-activity me-2"></i>AI Clinical Assessment Engine</h4>
                    <p class="text-muted small mb-0">Multi-factor clinical evaluation matching symptoms, patient demographics, and medical risk factors to the correct medical specialty.</p>
                </div>
                <div class="card-body p-4">
                    <form action="symptom-checker.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Patient Age *</label>
                                <input type="number" name="age" class="form-control" value="<?= (int)($_POST['age'] ?? 30) ?>" min="1" max="120" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Biological Sex *</label>
                                <select name="sex" class="form-select" required>
                                    <option value="Male" <?= (($_POST['sex'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= (($_POST['sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= (($_POST['sex'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Primary Symptoms & Clinical Complaint *</label>
                            <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe symptoms in detail (e.g. sharp chest pain radiating to left shoulder, shortness of breath, dizziness since 2 hours)..." required><?= sanitize($_POST['symptoms'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Perceived Severity *</label>
                                <select name="severity" class="form-select" required>
                                    <option value="Mild" <?= (($_POST['severity'] ?? '') === 'Mild') ? 'selected' : '' ?>>Mild (Noticeable but non-disruptive)</option>
                                    <option value="Moderate" <?= (($_POST['severity'] ?? '') === 'Moderate') ? 'selected' : '' ?>>Moderate (Limits normal activities)</option>
                                    <option value="Severe" <?= (($_POST['severity'] ?? '') === 'Severe') ? 'selected' : '' ?>>Severe (High pain / debilitating)</option>
                                    <option value="Emergency" <?= (($_POST['severity'] ?? '') === 'Emergency') ? 'selected' : '' ?>>Emergency (Acute distress)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Symptom Duration</label>
                                <input type="text" name="duration" class="form-control" placeholder="e.g. 3 hours, 2 days" value="<?= sanitize($_POST['duration'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Pre-existing Conditions / Medical History</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pre_conditions[]" value="hypertension" id="chk_hyp">
                                    <label class="form-check-label" for="chk_hyp">Hypertension (High BP)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pre_conditions[]" value="diabetes" id="chk_diab">
                                    <label class="form-check-label" for="chk_diab">Diabetes Mellitus</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pre_conditions[]" value="asthma" id="chk_asthma">
                                    <label class="form-check-label" for="chk_asthma">Asthma / Respiratory Illness</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow-sm">
                            Run Clinical Evaluation & Match Specialist <i class="bi bi-cpu ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Analysis Output -->
            <?php if ($analysisResult): ?>
                <!-- Red-Flag Alert Banner -->
                <?php if ($analysisResult['is_red_flag']): ?>
                    <div class="alert alert-danger border-0 shadow-lg p-4 rounded-4 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-1 me-3 text-danger"></i>
                            <div>
                                <h4 class="fw-bold mb-1">CRITICAL RED-FLAG ALERT: <?= $analysisResult['red_flag_name'] ?></h4>
                                <p class="mb-0">Your symptoms match critical emergency criteria. Please proceed directly to the nearest hospital Emergency Room or call 24/7 hotline <strong>+977-9800000000</strong> immediately.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Clinical Recommendation Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-4 border-primary">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold"><i class="bi bi-shield-check me-1"></i> Triage Analysis Complete</span>
                            <small class="text-muted fw-bold">AI Match Confidence: <span class="text-primary"><?= $analysisResult['confidence'] ?>%</span></small>
                        </div>

                        <h3 class="fw-bold text-dark mb-1">Matched Specialty: <?= sanitize($matchedDepartment['name'] ?? 'General Medicine') ?></h3>
                        <p class="text-muted mb-3"><?= sanitize($matchedDepartment['description'] ?? 'Comprehensive care.') ?></p>

                        <!-- Risk Score Bar -->
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="fw-bold">Calculated Clinical Risk Score:</small>
                                <small class="fw-bold text-<?= $analysisResult['risk_score'] > 60 ? 'danger' : 'success' ?>"><?= $analysisResult['risk_score'] ?> / 100</small>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?= $analysisResult['risk_score'] > 60 ? 'danger' : ($analysisResult['risk_score'] > 35 ? 'warning' : 'success') ?>" style="width: <?= $analysisResult['risk_score'] ?>%"></div>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3">Recommended Department Physicians</h5>
                        <div class="row g-3">
                            <?php if (!empty($recommendedDoctors)): foreach ($recommendedDoctors as $doc): ?>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center mb-2">
                                                <img src="<?= BASE_URL ?>uploads/profile-images/<?= sanitize($doc['profile_image']) ?>" class="rounded-circle me-2 object-fit-cover border" width="50" height="50">
                                                <div>
                                                    <h6 class="fw-bold mb-0">Dr. <?= sanitize($doc['name']) ?></h6>
                                                    <small class="text-primary fw-semibold"><?= sanitize($doc['specialization']) ?></small>
                                                </div>
                                            </div>
                                            <p class="small text-muted mb-2"><?= sanitize($doc['qualification']) ?> | <?= $doc['experience'] ?> Yrs Exp</p>
                                        </div>
                                        <div class="pt-2 border-top d-flex align-items-center justify-content-between">
                                            <span class="fw-bold text-success"><?= formatCurrency($doc['consultation_fee']) ?></span>
                                            <a href="appointments.php?doctor_id=<?= $doc['doctor_table_id'] ?>" class="btn btn-primary btn-sm rounded-pill fw-bold">Book Consultation</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; else: ?>
                                <div class="col-12 text-muted">No specialists currently listed for this department.</div>
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