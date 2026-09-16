<?php
/**
 * Patient Appointment Booking Interface
 */
$pageTitle = "Book Appointment";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole('patient');

$user = currentUser();
$db = Database::getConnection();

// Fetch Patient Database Record
$stmtP = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmtP->execute([$user['id']]);
$patient = $stmtP->fetch();
$patientId = $patient['id'];

// Fetch Active Departments for Selection Dropdown
$departments = $db->query("SELECT * FROM departments WHERE status = 'active'")->fetchAll();

// Fetch Active Doctors
$doctors = $db->query("
    SELECT d.id, u.name, d.department_id, d.specialization, d.consultation_fee 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.status = 'active'
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $date     = sanitize($_POST['appointment_date'] ?? '');
    $time     = sanitize($_POST['appointment_time'] ?? '');
    $reason   = sanitize($_POST['reason'] ?? '');
    $token    = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = "CSRF Verification Token Failed.";
    } elseif (!$doctorId || empty($date) || empty($time)) {
        $error = "Please fill in all mandatory booking fields.";
    } elseif ($date < date('Y-m-d')) {
        $error = "Cannot book appointments in the past.";
    } else {
        // Prevent Double Booking Validation
        $stmtDouble = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled')");
        $stmtDouble->execute([$doctorId, $date, $time]);
        
        if ($stmtDouble->fetch()) {
            $error = "This time slot is no longer available. Please select another slot.";
        } else {
            try {
                $apptNum = generateAppointmentNumber();
                $stmtInst = $db->prepare("INSERT INTO appointments (appointment_number, patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
                $stmtInst->execute([$apptNum, $patientId, $doctorId, $date, $time, $reason]);
                $apptId = $db->lastInsertId();

                // Notify Doctor
                $stmtDocUser = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
                $stmtDocUser->execute([$doctorId]);
                $docUserId = $stmtDocUser->fetchColumn();

                createNotification($docUserId, 'New Booking Request', "New appointment {$apptNum} scheduled for {$date}.", 'info', $apptId);
                logAudit($user['id'], 'Booked Appointment', 'Appointments', $apptId);

                setFlashMessage('success', "Appointment requested successfully! Number: {$apptNum}");
                header('Location: appointments.php');
                exit();
            } catch (\Exception $e) {
                $error = "Booking creation error: " . $e->getMessage();
            }
        }
    }
}
?>

<div id="page-content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="fw-bold mb-0">Book a Medical Appointment</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= sanitize($error) ?></div>
                        <?php endif; ?>

                        <form action="book-appointment.php" method="POST" id="bookingForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">1. Select Department</label>
                                <select id="deptSelect" class="form-select form-select-lg">
                                    <option value="">-- Filter by Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>"><?= sanitize($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">2. Select Doctor *</label>
                                <select name="doctor_id" id="doctorSelect" class="form-select form-select-lg" required>
                                    <option value="">-- Select Specialist --</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?= $doc['id'] ?>" data-dept="<?= $doc['department_id'] ?>">
                                            <?= sanitize($doc['name']) ?> (<?= sanitize($doc['specialization']) ?>) - <?= formatCurrency($doc['consultation_fee']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">3. Appointment Date *</label>
                                <input type="date" name="appointment_date" id="dateInput" class="form-control form-control-lg" min="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">4. Available Dynamic Slot *</label>
                                <div id="slotsContainer" class="d-flex flex-wrap gap-2 p-3 bg-light rounded border min-h-50">
                                    <small class="text-muted">Select doctor and date to load open schedule slots.</small>
                                </div>
                                <input type="hidden" name="appointment_time" id="timeInput" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Reason for Visit / Symptoms</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Describe your primary reason for consultation..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Confirm & Request Appointment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.getElementById('deptSelect');
    const doctorSelect = document.getElementById('doctorSelect');
    const dateInput = document.getElementById('dateInput');
    const slotsContainer = document.getElementById('slotsContainer');
    const timeInput = document.getElementById('timeInput');

    // Filter Doctors by Department
    deptSelect.addEventListener('change', function() {
        const selectedDept = this.value;
        Array.from(doctorSelect.options).forEach(option => {
            if (!option.value) return;
            if (!selectedDept || option.dataset.dept === selectedDept) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
        doctorSelect.value = '';
    });

    // Fetch Dynamic Availability Slots via AJAX
    function loadSlots() {
        const docId = doctorSelect.value;
        const dateVal = dateInput.value;

        if (!docId || !dateVal) return;

        slotsContainer.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat spin"></i> Loading open schedule slots...</span>';

        fetch(`${BASE_URL}ajax/get-time-slots.php?doctor_id=${docId}&date=${dateVal}`)
            .then(res => res.json())
            .then(data => {
                slotsContainer.innerHTML = '';
                if (data.success && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = slot.available ? 'btn btn-outline-primary me-2 mb-2' : 'btn btn-outline-secondary me-2 mb-2 disabled';
                        btn.textContent = slot.label;
                        if (slot.available) {
                            btn.addEventListener('click', function() {
                                document.querySelectorAll('#slotsContainer .btn').forEach(b => b.classList.replace('btn-primary', 'btn-outline-primary'));
                                this.classList.replace('btn-outline-primary', 'btn-primary');
                                timeInput.value = slot.time;
                            });
                        }
                        slotsContainer.appendChild(btn);
                    });
                } else {
                    slotsContainer.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> ${data.message || 'No slots available on this date.'}</span>`;
                }
            });
    }

    doctorSelect.addEventListener('change', loadSlots);
    dateInput.addEventListener('change', loadSlots);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>