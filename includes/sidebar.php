<?php
if (count(get_included_files()) == 1) exit("Direct access not permitted.");
$user = currentUser();
$role = $user['role'] ?? '';
$currentScript = basename($_SERVER['PHP_SELF']);
?>

<!-- Scoped CSS: Fixed Scrollable Sidebar with No Layout Gaps -->
<style>
#sidebar-wrapper {
    width: 250px !important;
    min-width: 250px !important;
    max-width: 250px !important;
    height: 100vh !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 1000 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

/* Chrome, Edge, Safari Custom Slim Scrollbar */
#sidebar-wrapper::-webkit-scrollbar {
    width: 5px;
}
#sidebar-wrapper::-webkit-scrollbar-track {
    background: transparent;
}
#sidebar-wrapper::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}
#sidebar-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

/* Push Main Page Content Right so it doesn't overlap the Fixed Sidebar */
#page-content-wrapper {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    min-height: 100vh !important;
}

#sidebar-wrapper .list-group-item {
    border: none !important;
    border-radius: 0 !important;
    padding: 0.75rem 1.25rem !important;
}

#sidebar-wrapper .list-group-item.active {
    background-color: #0d6efd !important;
    color: #ffffff !important;
}
</style>

<div id="sidebar-wrapper" class="bg-dark text-white border-end shadow-sm">
    <div class="sidebar-heading d-flex align-items-center p-3 border-bottom border-secondary">
        <i class="bi bi-heart-pulse-fill text-primary me-2 fs-4"></i>
        <span class="fw-bold text-white fs-5">CarePlus HMS</span>
    </div>
    
    <div class="list-group list-group-flush py-0">
        <?php if ($role === 'admin'): ?>
            <a href="<?= BASE_URL ?>admin/index.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'departments.php' ? 'active' : '' ?>"><i class="bi bi-building me-2"></i>Departments</a>
            <a href="<?= BASE_URL ?>admin/doctors.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'doctors.php' ? 'active' : '' ?>"><i class="bi bi-person-badge me-2"></i>Doctors</a>
            <a href="<?= BASE_URL ?>admin/patients.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'patients.php' ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Patients</a>
            <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'users.php' ? 'active' : '' ?>"><i class="bi bi-shield-lock me-2"></i>Users & Roles</a>
            <a href="<?= BASE_URL ?>admin/appointments.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
            <a href="<?= BASE_URL ?>admin/beds.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'beds.php' ? 'active' : '' ?>"><i class="bi bi-hospital me-2"></i>IPD Bed Matrix</a>
            <a href="<?= BASE_URL ?>admin/discharge.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'discharge.php' ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Discharge Engine</a>
            <a href="<?= BASE_URL ?>admin/pharmacy.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'pharmacy.php' ? 'active' : '' ?>"><i class="bi bi-capsule me-2"></i>Pharmacy Stock</a>
            <a href="<?= BASE_URL ?>admin/pharmacy-pos.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'pharmacy-pos.php' ? 'active' : '' ?>"><i class="bi bi-cart-check me-2"></i>Pharmacy POS</a>
            <a href="<?= BASE_URL ?>admin/lab-lis.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'lab-lis.php' ? 'active' : '' ?>"><i class="bi bi-activity me-2"></i>Diagnostic LIS</a>
            <a href="<?= BASE_URL ?>admin/blood-bank.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'blood-bank.php' ? 'active' : '' ?>"><i class="bi bi-droplet-fill me-2"></i>Blood Bank</a>
            <a href="<?= BASE_URL ?>admin/billing.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'billing.php' ? 'active' : '' ?>"><i class="bi bi-receipt me-2"></i>Financial Billing</a>
            <a href="<?= BASE_URL ?>admin/audit-logs.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'audit-logs.php' ? 'active' : '' ?>"><i class="bi bi-journal-text me-2"></i>Audit Logs</a>
            <a href="<?= BASE_URL ?>admin/settings.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'settings.php' ? 'active' : '' ?>"><i class="bi bi-gear me-2"></i>Settings</a>

        <?php elseif ($role === 'doctor'): ?>
            <a href="<?= BASE_URL ?>doctor/index.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>doctor/availability.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'availability.php' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Availability</a>
            <a href="<?= BASE_URL ?>doctor/appointments.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-event me-2"></i>Appointments</a>
            <a href="<?= BASE_URL ?>doctor/patients.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'patients.php' ? 'active' : '' ?>"><i class="bi bi-person-wheelchair me-2"></i>My Patients</a>
            <a href="<?= BASE_URL ?>doctor/medical-records.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'medical-records.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-medical me-2"></i>EMR Records</a>
            <a href="<?= BASE_URL ?>doctor/prescriptions.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'prescriptions.php' ? 'active' : '' ?>"><i class="bi bi-capsule me-2"></i>Prescriptions</a>
            <a href="<?= BASE_URL ?>doctor/lab-requests.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'lab-requests.php' ? 'active' : '' ?>"><i class="bi bi-virus me-2"></i>Lab Orders</a>

        <?php elseif ($role === 'patient'): ?>
            <a href="<?= BASE_URL ?>patient/index.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>patient/symptom-checker.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'symptom-checker.php' ? 'active' : '' ?>"><i class="bi bi-cpu me-2"></i>AI Symptom Triage</a>
            <a href="<?= BASE_URL ?>patient/find-doctor.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'find-doctor.php' ? 'active' : '' ?>"><i class="bi bi-search me-2"></i>Find a Doctor</a>
            <a href="<?= BASE_URL ?>patient/book-appointment.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'book-appointment.php' ? 'active' : '' ?>"><i class="bi bi-calendar-plus me-2"></i>Book Appointment</a>
            <a href="<?= BASE_URL ?>patient/appointments.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>My Appointments</a>
            <a href="<?= BASE_URL ?>patient/medical-records.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'medical-records.php' ? 'active' : '' ?>"><i class="bi bi-journal-medical me-2"></i>Medical Records</a>
            <a href="<?= BASE_URL ?>patient/prescriptions.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'prescriptions.php' ? 'active' : '' ?>"><i class="bi bi-capsule me-2"></i>Prescriptions</a>
            <a href="<?= BASE_URL ?>patient/lab-reports.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'lab-reports.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Lab Reports</a>
            <a href="<?= BASE_URL ?>patient/bills.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'bills.php' ? 'active' : '' ?>"><i class="bi bi-credit-card me-2"></i>Invoices & Bills</a>
            <a href="<?= BASE_URL ?>patient/profile.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person-gear me-2"></i>My Profile</a>

        <?php elseif ($role === 'laboratory'): ?>
            <a href="<?= BASE_URL ?>laboratory/index.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>laboratory/test-requests.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'test-requests.php' ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Lab Test Worklist</a>

        <?php elseif ($role === 'billing'): ?>
            <a href="<?= BASE_URL ?>billing/index.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>billing/invoices.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'invoices.php' ? 'active' : '' ?>"><i class="bi bi-receipt-cutoff me-2"></i>Manage Invoices</a>
            <a href="<?= BASE_URL ?>billing/payments.php" class="list-group-item bg-transparent text-white <?= $currentScript == 'payments.php' ? 'active' : '' ?>"><i class="bi bi-cash-stack me-2"></i>Payment Ledger</a>
        <?php endif; ?>
    </div>
</div>