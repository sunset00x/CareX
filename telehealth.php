<?php
/**
 * Universal Telehealth & WebRTC Video Consultation Room
 */
$pageTitle = "Telehealth Video Consultation";
require_once __DIR__ . '/includes/header.php';

$roomId = sanitize($_GET['room'] ?? 'CarePlus-Consult-' . rand(1000, 9999));
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-primary"><i class="bi bi-camera-video-fill me-2"></i>Telehealth Consultation Room</h3>
            <p class="text-muted mb-0">Encrypted virtual clinical consultation.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill fw-bold">Leave Consultation</a>
    </div>

    <!-- WebRTC Video Room Container -->
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-dark text-white" style="height: 520px;">
        <div class="card-body p-0 d-flex align-items-center justify-content-center text-center id="video-wrapper">
            <div>
                <i class="bi bi-person-video3 text-primary display-1 mb-3"></i>
                <h4 class="fw-bold">Virtual Room Connected: <code><?= $roomId ?></code></h4>
                <p class="text-white-50">Camera and microphone stream initialized. Waiting for physician/patient...</p>
                <button class="btn btn-danger rounded-circle p-3 me-2"><i class="bi bi-mic-mute-fill fs-4"></i></button>
                <button class="btn btn-danger rounded-circle p-3 me-2"><i class="bi bi-camera-video-off-fill fs-4"></i></button>
                <a href="index.php" class="btn btn-danger fw-bold rounded-pill px-4 py-2">End Call</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>