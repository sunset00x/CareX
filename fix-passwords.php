<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection();
    $newHash = password_hash('Password123!', PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE users SET password = ?");
    $stmt->execute([$newHash]);
    
    echo "<h2 style='color:green;'>Success!</h2>";
    echo "<p>All user passwords have been reset to: <strong>Password123!</strong></p>";
    echo "<a href='login.php'>Go to Login Page</a>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error:</h2> " . $e->getMessage();
}