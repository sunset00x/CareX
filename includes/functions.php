<?php
/**
 * Utility & Helper Functions
 * CareX Smart Hospital Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escape string output to block XSS vector attacks
 */
function sanitize($string) {
    return htmlspecialchars(trim($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format Currency output
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format Standard Date Display
 */
function formatDate($dateString) {
    if (empty($dateString)) return 'N/A';
    return date('M d, Y', strtotime($dateString));
}

/**
 * Format Standard Time Display
 */
function formatTime($timeString) {
    if (empty($timeString)) return 'N/A';
    return date('h:i A', strtotime($timeString));
}

/**
 * Audit Logging Helper Function
 */
function logAudit($userId, $action, $module, $recordId = null) {
    try {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $module, $recordId, $ip]);
    } catch (\Exception $e) {
        // Fail silently in audit logging to prevent application workflow disruptions
        error_log("Audit log insertion failed: " . $e->getMessage());
    }
}

/**
 * Create Database System Notification
 */
function createNotification($userId, $title, $message, $type = 'info', $relatedId = null) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type, $relatedId]);
    } catch (\Exception $e) {
        error_log("Notification failure: " . $e->getMessage());
    }
}

/**
 * Fetch Unread Notifications Count for Logged User
 */
function getUnreadNotificationCount($userId) {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * System Unique ID Generators
 */
function generatePatientID() {
    return 'PAT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateDoctorID() {
    return 'DOC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateAppointmentNumber() {
    return 'APT-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function generatePrescriptionNumber() {
    return 'PRX-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function generateLabTestNumber() {
    return 'LAB-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function generateInvoiceNumber() {
    return 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * CSRF Guard Generation & Validation
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("CSRF Token Verification Failed. Transaction Terminated.");
    }
    return true;
}

/**
 * Render Flash Alert Messages
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message
    ];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . sanitize($msg['type']) . ' alert-dismissible fade show" role="alert">
                ' . sanitize($msg['text']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash_message']);
    }
}