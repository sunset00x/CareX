<?php
/**
 * User Logout Guard & Session Destruction Script
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], 'User Logout', 'Authentication', $_SESSION['user_id']);
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

header('Location: ' . BASE_URL . 'login.php');
exit();