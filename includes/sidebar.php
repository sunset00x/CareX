<?php
if (count(get_included_files()) == 1) exit("Direct access not permitted.");
$user = currentUser();
$role = $user['role'] ?? '';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar-wrapper">
    <div class="sidebar-heading d-flex align-items-center">
        <i class="bi bi-heart-pulse-fill text-primary me-2 fs-4"></i>
        <span>CarePlus HMS</span>
    </div>
    <div class="list-group list-group-flush py-3">
        <?php if ($role === 'admin'): ?>
            <a href="<?= BASE_URL ?>admin/index.php" class="list-group-item <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item <?= $currentScript == 'departments.php' ? 'active' : '' ?>"><i class="bi bi-building me-2"></i>Departments</a>
            <a href="<?= BASE_URL ?>admin/doctors.php" class="list-group-item <?= $currentScript == 'doctors.php' ? 'active' : '' ?>"><i class="bi bi-person-md me-2"></i>Doctors</a>
            <a href="<?= BASE_URL ?>admin/patients.php" class="list-group-item <?= $currentScript == 'patients.php' ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Patients</a>
            <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item <?= $currentScript == 'users.php' ? 'active' : '' ?>"><i class="bi bi-shield-lock me-2"></i>Users & Roles</a>
            <a href="<?= BASE_URL ?>admin/appointments.php" class="list-group-item <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
            <a href="<?= BASE_URL ?>admin/billing.php" class="list-group-item <?= $currentScript == 'billing.php' ? 'active' : '' ?>"><i class="bi bi-receipt me-2"></i>Financial Billing</a>
            <a href="<?= BASE_URL ?>admin/audit-logs.php" class="list-group-item <?= $currentScript == 'audit-logs.php' ? 'active' : '' ?>"><i class="bi bi-journal-text me-2"></i>Audit Logs</a>
        
<a href="<?= BASE_URL ?>admin/beds.php" class="list-group-item list-group-item-action bg-transparent border-0 <?= $currentPage === 'beds.php' ? 'active' : '' ?>">
    <i class="bi bi-hospital me-2"></i>IPD Bed Matrix
</a>

<a href="<?= BASE_URL ?>admin/pharmacy.php" class="list-group-item list-group-item-action bg-transparent border-0 <?= $currentPage === 'pharmacy.php' ? 'active' : '' ?>">
    <i class="bi bi-capsule me-2"></i>Pharmacy Stock
</a>

<a href="<?= BASE_URL ?>admin/blood-bank.php" class="list-group-item list-group-item-action bg-transparent border-0 <?= $currentPage === 'blood-bank.php' ? 'active' : '' ?>">
    <i class="bi bi-droplet-fill me-2"></i>Blood Bank
</a>
 <a href="<?= BASE_URL ?>admin/settings.php" class="list-group-item <?= $currentScript == 'settings.php' ? 'active' : '' ?>"><i class="bi bi-journal-text me-2"></i>Settings</a>

        <?php elseif ($role === 'doctor'): ?>
            <a href="<?= BASE_URL ?>doctor/index.php" class="list-group-item <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>doctor/availability.php" class="list-group-item <?= $currentScript == 'availability.php' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Availability</a>
            <a href="<?= BASE_URL ?>doctor/appointments.php" class="list-group-item <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-event me-2"></i>Appointments</a>
            <a href="<?= BASE_URL ?>doctor/patients.php" class="list-group-item <?= $currentScript == 'patients.php' ? 'active' : '' ?>"><i class="bi bi-person-wheelchair me-2"></i>My Patients</a>
            <a href="<?= BASE_URL ?>doctor/medical-records.php" class="list-group-item <?= $currentScript == 'medical-records.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-medical me-2"></i>EMR Records</a>
            <a href="<?= BASE_URL ?>doctor/prescriptions.php" class="list-group-item <?= $currentScript == 'prescriptions.php' ? 'active' : '' ?>"><i class="bi bi-capsule me-2"></i>Prescriptions</a>
            <a href="<?= BASE_URL ?>doctor/lab-requests.php" class="list-group-item <?= $currentScript == 'lab-requests.php' ? 'active' : '' ?>"><i class="bi bi-virus me-2"></i>Lab Orders</a>

        <?php elseif ($role === 'patient'): ?>
            <a href="<?= BASE_URL ?>patient/index.php" class="list-group-item <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>patient/find-doctor.php" class="list-group-item <?= $currentScript == 'find-doctor.php' ? 'active' : '' ?>"><i class="bi bi-search me-2"></i>Find a Doctor</a>
            <a href="<?= BASE_URL ?>patient/book-appointment.php" class="list-group-item <?= $currentScript == 'book-appointment.php' ? 'active' : '' ?>"><i class="bi bi-calendar-plus me-2"></i>Book Appointment</a>
            <a href="<?= BASE_URL ?>patient/appointments.php" class="list-group-item <?= $currentScript == 'appointments.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>My Appointments</a>
            <a href="<?= BASE_URL ?>patient/medical-records.php" class="list-group-item <?= $currentScript == 'medical-records.php' ? 'active' : '' ?>"><i class="bi bi-journal-medical me-2"></i>Medical Records</a>
            <a href="<?= BASE_URL ?>patient/prescriptions.php" class="list-group-item <?= $currentScript == 'prescriptions.php' ? 'active' : '' ?>"><i class="bi bi-capsule me-2"></i>Prescriptions</a>
            <a href="<?= BASE_URL ?>patient/lab-reports.php" class="list-group-item <?= $currentScript == 'lab-reports.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Lab Reports</a>
            <a href="<?= BASE_URL ?>patient/bills.php" class="list-group-item <?= $currentScript == 'bills.php' ? 'active' : '' ?>"><i class="bi bi-credit-card me-2"></i>Invoices & Bills</a>
            <a href="<?= BASE_URL ?>patient/profile.php" class="list-group-item <?= $currentScript == 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person-gear me-2"></i>My Profile</a>

        <?php elseif ($role === 'laboratory'): ?>
            <a href="<?= BASE_URL ?>laboratory/index.php" class="list-group-item <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>laboratory/test-requests.php" class="list-group-item <?= $currentScript == 'test-requests.php' ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Lab Test Worklist</a>

        <?php elseif ($role === 'billing'): ?>
            <a href="<?= BASE_URL ?>billing/index.php" class="list-group-item <?= $currentScript == 'index.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>billing/invoices.php" class="list-group-item <?= $currentScript == 'invoices.php' ? 'active' : '' ?>"><i class="bi bi-receipt-cutoff me-2"></i>Manage Invoices</a>
            <a href="<?= BASE_URL ?>billing/payments.php" class="list-group-item <?= $currentScript == 'payments.php' ? 'active' : '' ?>"><i class="bi bi-cash-stack me-2"></i>Payment Ledger</a>
        <?php endif; ?>
    </div>
</div>