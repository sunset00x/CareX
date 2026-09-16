<?php
/**
 * Authentication and Access Control Middleware
 * CarePlus Smart Hospital Management System
 */

require_once __DIR__ . '/config/config.php';
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
 * Mandatory Login Middleware Guard
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('warning', 'Please login to access this section.');
        header('Location: ' . BASE_URL . 'login.php');
        exit();
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