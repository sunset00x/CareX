USE `smart_hospital`;

SET FOREIGN_KEY_CHECKS = 0;

-- Clean standard records
TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `bill_items`;
TRUNCATE TABLE `bills`;
TRUNCATE TABLE `lab_reports`;
TRUNCATE TABLE `lab_tests`;
TRUNCATE TABLE `prescription_items`;
TRUNCATE TABLE `prescriptions`;
TRUNCATE TABLE `medical_records`;
TRUNCATE TABLE `appointments`;
TRUNCATE TABLE `doctor_availability`;
TRUNCATE TABLE `doctors`;
TRUNCATE TABLE `patients`;
TRUNCATE TABLE `departments`;
TRUNCATE TABLE `users`;

-- Password string: Password123!
-- Hash: $2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G

-- 1. Insert Departments
INSERT INTO `departments` (`id`, `name`, `description`, `status`) VALUES
(1, 'Cardiology', 'Comprehensive care for cardiac conditions and hypertension.', 'active'),
(2, 'Neurology', 'Diagnosis and treatment of nervous system and brain disorders.', 'active'),
(3, 'Orthopedics', 'Musculoskeletal system specialists including joint surgeries.', 'active'),
(4, 'Pediatrics', 'Comprehensive healthcare services dedicated to children and adolescents.', 'active'),
(5, 'General Medicine', 'Primary healthcare, internal medicine, and preventative care.', 'active');

-- 2. Insert Base Users
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `status`) VALUES
(1, 'System Administrator', 'admin@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'admin', '+977-9800000001', 'active'),
(2, 'Dr. Anish Sharma', 'doctor@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'doctor', '+977-9800000002', 'active'),
(3, 'Dr. Sunita Rai', 'sunita.rai@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'doctor', '+977-9800000003', 'active'),
(4, 'Aarav Patel', 'patient@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'patient', '+977-9800000004', 'active'),
(5, 'Bina Thapa', 'bina.patient@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'patient', '+977-9800000005', 'active'),
(6, 'Ramesh Technician', 'lab@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'laboratory', '+977-9800000006', 'active'),
(7, 'Sita Billing Manager', 'billing@careplus.test', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1n4V8kH/Yc/.HhA00.jG.yG/Lg21a0G', 'billing', '+977-9800000007', 'active');

-- 3. Insert Patients
INSERT INTO `patients` (`id`, `user_id`, `patient_id`, `date_of_birth`, `gender`, `blood_group`, `address`, `emergency_contact`, `emergency_phone`) VALUES
(1, 4, 'PAT-2026-0001', '1992-05-14', 'male', 'O+', 'Kathmandu, Nepal', 'Pooja Patel (Spouse)', '+977-9841111111'),
(2, 5, 'PAT-2026-0002', '1988-11-23', 'female', 'A+', 'Lalitpur, Nepal', 'Ram Thapa (Brother)', '+977-9842222222');

-- 4. Insert Doctors
INSERT INTO `doctors` (`id`, `user_id`, `doctor_id`, `department_id`, `specialization`, `qualification`, `experience`, `consultation_fee`, `license_number`, `bio`) VALUES
(1, 2, 'DOC-2026-0001', 1, 'Cardiology', 'MD, DM Cardiology (AIIMS)', 12, 1000.00, 'NMC-12894', 'Senior Cardiologist specializing in preventative heart health and hypertension management.'),
(2, 3, 'DOC-2026-0002', 5, 'Internal Medicine', 'MBBS, MD General Medicine', 8, 800.00, 'NMC-18452', 'General Practitioner dedicated to internal medicine and infectious disease management.');

-- 5. Insert Doctor Availability
INSERT INTO `doctor_availability` (`id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration`, `status`) VALUES
(1, 1, 'Monday', '09:00:00', '13:00:00', 30, 'active'),
(2, 1, 'Wednesday', '09:00:00', '13:00:00', 30, 'active'),
(3, 1, 'Friday', '14:00:00', '17:00:00', 30, 'active'),
(4, 2, 'Tuesday', '10:00:00', '16:00:00', 20, 'active'),
(5, 2, 'Thursday', '10:00:00', '16:00:00', 20, 'active');

-- 6. Insert Appointments
INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `reason`, `notes`, `status`, `created_at`) VALUES
(1, 'APT-2026-000001', 1, 1, '2026-09-10', '09:30:00', 'Chest discomfort and shortness of breath upon exertion.', 'Patient reports symptoms for 3 days.', 'Completed', '2026-09-08 10:00:00'),
(2, 'APT-2026-000002', 1, 2, '2026-09-15', '10:20:00', 'Routine follow up for high fever and malaise.', 'Scheduled.', 'Confirmed', '2026-09-12 11:30:00'),
(3, 'APT-2026-000003', 2, 1, '2026-09-21', '10:00:00', 'Palpitations and dizziness.', 'Initial consultation.', 'Pending', '2026-09-13 08:15:00');

-- 7. Insert Medical Records
INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `appointment_id`, `symptoms`, `diagnosis`, `treatment`, `notes`, `blood_pressure`, `temperature`, `weight`, `created_at`) VALUES
(1, 1, 1, 1, 'Mild chest tightness, fatigue, slight elevated BP.', 'Stage 1 Essential Hypertension', 'Lifestyle modifications, low sodium diet, daily blood pressure tracking.', 'Schedule Lipid Profile and follow up in 2 weeks.', '138/88 mmHg', '98.6 F', '74 kg', '2026-09-10 10:15:00');

-- 8. Insert Prescriptions & Items
INSERT INTO `prescriptions` (`id`, `prescription_number`, `patient_id`, `doctor_id`, `appointment_id`, `instructions`, `created_at`) VALUES
(1, 'PRX-2026-000001', 1, 1, 1, 'Take medications strictly after food. Drink plenty of water.', '2026-09-10 10:20:00');

INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_name`, `dosage`, `frequency`, `duration`, `instructions`) VALUES
(1, 1, 'Amlodipine', '5mg', 'Once daily (Morning)', '30 days', 'Take after breakfast'),
(2, 1, 'Aspirin', '75mg', 'Once daily (Night)', '30 days', 'Take after dinner');

-- 9. Insert Lab Tests & Reports
INSERT INTO `lab_tests` (`id`, `test_number`, `patient_id`, `doctor_id`, `appointment_id`, `test_name`, `description`, `priority`, `status`, `requested_at`, `completed_at`) VALUES
(1, 'LAB-2026-000001', 1, 1, 1, 'Lipid Profile & Fasting Blood Sugar', 'Evaluate cholesterol levels and fasting glucose.', 'Normal', 'Completed', '2026-09-10 10:25:00', '2026-09-11 14:00:00');

INSERT INTO `lab_reports` (`id`, `lab_test_id`, `technician_id`, `result`, `reference_range`, `notes`, `report_file`, `created_at`) VALUES
(1, 1, 6, 'Total Cholesterol: 215 mg/dL (Elevated)\nTriglycerides: 160 mg/dL\nHDL: 42 mg/dL\nLDL: 141 mg/dL\nFasting Glucose: 95 mg/dL', 'Total Chol: < 200 mg/dL\nLDL: < 100 mg/dL\nFasting Glucose: 70-99 mg/dL', 'Mild hypercholesterolemia indicated. Fasting glucose normal.', NULL, '2026-09-11 14:00:00');

-- 10. Insert Bills, Bill Items & Payments
INSERT INTO `bills` (`id`, `invoice_number`, `patient_id`, `appointment_id`, `subtotal`, `discount`, `tax`, `total`, `paid_amount`, `due_amount`, `status`, `created_at`) VALUES
(1, 'INV-2026-000001', 1, 1, 2300.00, 200.00, 273.00, 2373.00, 2373.00, 0.00, 'Paid', '2026-09-10 10:30:00');

INSERT INTO `bill_items` (`id`, `bill_id`, `service_name`, `description`, `quantity`, `unit_price`, `total`) VALUES
(1, 1, 'Cardiology Consultation', 'Consultation fee with Dr. Anish Sharma', 1, 1000.00, 1000.00),
(2, 1, 'Lipid Profile & Blood Test', 'Laboratory diagnostic fee', 1, 1300.00, 1300.00);

INSERT INTO `payments` (`id`, `bill_id`, `patient_id`, `amount`, `payment_method`, `transaction_reference`, `payment_date`, `status`) VALUES
(1, 1, 1, 2373.00, 'Card', 'TXN-CARD-9988221', '2026-09-10 10:35:00', 'Success');

-- 11. Notifications
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `is_read`, `created_at`) VALUES
(1, 4, 'Appointment Confirmed', 'Your appointment APT-2026-000002 with Dr. Sunita Rai is confirmed.', 'success', 2, 0, '2026-09-12 11:30:00'),
(2, 4, 'Lab Report Available', 'Your lab test report for LAB-2026-000001 has been uploaded.', 'info', 1, 1, '2026-09-11 14:00:00');

SET FOREIGN_KEY_CHECKS = 1;