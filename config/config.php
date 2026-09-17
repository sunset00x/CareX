<?php
/**
 * Global Configuration Settings
 * CareX Smart Hospital Management System
 */

// Prevent direct execution
if (count(get_included_files()) == 1) exit("Direct access not permitted.");

// Application Metadata
define('APP_NAME', 'CareX Hospital Management System');
define('APP_SHORT_NAME', 'CareX HMS');
define('APP_VERSION', '1.0.0');
define('CURRENCY_SYMBOL', 'NPR ');

// Base URL configuration (Adjust according to local folder setup)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $host . '/hospital-system/');

// File Path Rules
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');

// Upload Categories Directory Maps
define('PATH_PRESCRIPTIONS', UPLOAD_PATH . 'prescriptions/');
define('PATH_LAB_REPORTS', UPLOAD_PATH . 'lab-reports/');
define('PATH_INVOICES', UPLOAD_PATH . 'invoices/');
define('PATH_PROFILES', UPLOAD_PATH . 'profile-images/');

// File Upload Constraints
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB limit
define('ALLOWED_FILE_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);
define('ALLOWED_MIME_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

// Timezone Setup
date_default_timezone_set('Asia/Kathmandu');

// Error Reporting Config (Development vs Production)
define('ENVIRONMENT', 'development'); // Options: 'development', 'production'

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . 'logs/error.log');
}

// Session Security Parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}