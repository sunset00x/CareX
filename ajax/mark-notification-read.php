<?php
/**
 * Asynchronous AJAX API Endpoint: Mark Notification as Read
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$user = currentUser();

if ($id > 0) {
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid ID']);
exit();