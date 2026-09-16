<?php
/**
 * Authentication and Access Control Middleware (With Real-time Session Guard & Heartbeat)
 * CarePlus Smart Hospital Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Verify User Authentication Status
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Fetch Current Logged-in User Array Data
 */
function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['user_name'] ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'profile'  => $_SESSION['user_profile'] ?? 'default-avatar.png'
    ];
}

/**
 * Mandatory Login Middleware Guard & Real-Time Session Invalidator
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('warning', 'Please login to access this section.');
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }

    // Live Heartbeat & Revocation Guard
    try {
        $db = Database::getConnection();
        $userId = $_SESSION['user_id'];

        $stmt = $db->prepare("SELECT status, force_logout FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userStatus = $stmt->fetch();

        // Check if Admin force-logged out or suspended the user
        if (!$userStatus || $userStatus['status'] !== 'active' || $userStatus['force_logout'] == 1) {
            // Unset force logout flag if set
            if ($userStatus && $userStatus['force_logout'] == 1) {
                $db->prepare("UPDATE users SET force_logout = 0 WHERE id = ?")->execute([$userId]);
            }

            // Destroy Session
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();

            session_start();
            setFlashMessage('danger', 'Your session has been terminated or revoked by administrator security policy.');
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }

        // Update Online Heartbeat Timestamp and IP/User Agent
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmtBeat = $db->prepare("UPDATE users SET last_seen = NOW(), ip_address = ?, user_agent = ? WHERE id = ?");
        $stmtBeat->execute([$ip, $agent, $userId]);

    } catch (\Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
    }
}

/**
 * Mandatory Authorization / Role Access Guard
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    
    if (!in_array($userRole, $allowedRoles)) {
        header("HTTP/1.1 403 Forbidden");
        echo "<h1>403 Forbidden</h1><p>You lack authorization to access this module.</p><a href='".BASE_URL."'>Return Home</a>";
        exit();
    }
}

/**
 * Redirect Authenticated User To Their Role Dashboard
 */
function redirectBasedOnRole() {
    if (!isLoggedIn()) return;
    
    $role = $_SESSION['user_role'] ?? '';
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . 'admin/index.php');
            break;
        case 'doctor':
            header('Location: ' . BASE_URL . 'doctor/index.php');
            break;
        case 'patient':
            header('Location: ' . BASE_URL . 'patient/index.php');
            break;
        case 'laboratory':
            header('Location: ' . BASE_URL . 'laboratory/index.php');
            break;
        case 'billing':
            header('Location: ' . BASE_URL . 'billing/index.php');
            break;
        default:
            header('Location: ' . BASE_URL . 'login.php');
            break;
    }
    exit();
}