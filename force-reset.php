<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection();

    // 1. Ensure password column is wide enough for BCRYPT hashes (VARCHAR 255)
    $db->exec("ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;");

    // 2. Generate a fresh hash directly on your current PHP runtime
    $rawPassword = 'Password123!';
    $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

    // 3. Clear existing user accounts to avoid duplicate email constraints
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE `users`;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 4. Insert guaranteed fresh demo accounts
    $stmt = $db->prepare("INSERT INTO users (id, name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    
    $users = [
        [1, 'System Administrator', 'admin@careplus.test', $hashedPassword, 'admin', '+977-9800000001'],
        [2, 'Dr. Anish Sharma', 'doctor@careplus.test', $hashedPassword, 'doctor', '+977-9800000002'],
        [3, 'Dr. Sunita Rai', 'sunita.rai@careplus.test', $hashedPassword, 'doctor', '+977-9800000003'],
        [4, 'Aarav Patel', 'patient@careplus.test', $hashedPassword, 'patient', '+977-9800000004'],
        [5, 'Bina Thapa', 'bina.patient@careplus.test', $hashedPassword, 'patient', '+977-9800000005'],
        [6, 'Ramesh Technician', 'lab@careplus.test', $hashedPassword, 'laboratory', '+977-9800000006'],
        [7, 'Sita Billing Manager', 'billing@careplus.test', $hashedPassword, 'billing', '+977-9800000007']
    ];

    foreach ($users as $u) {
        $stmt->execute($u);
    }

    echo "<div style='font-family:sans-serif; padding:20px; background:#dcfce7; color:#166534; border-radius:8px;'>";
    echo "<h2>✅ Success! Accounts Re-created</h2>";
    echo "<p>All 5 user role accounts have been freshly generated and hashed on your local PHP setup.</p>";
    echo "<p><strong>Password for all accounts:</strong> <code>Password123!</code></p>";
    echo "<p><a href='login.php' style='display:inline-block; padding:10px 20px; background:#166534; color:white; text-decoration:none; border-radius:4px;'>Go to Login Portal Now</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family:sans-serif; padding:20px; background:#fee2e2; color:#991b1b; border-radius:8px;'>";
    echo "<h2>❌ Reset Failed</h2>";
    echo "<p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}