<?php
/**
 * Asynchronous AJAX API Endpoint: Doctor Dynamic Time Slots
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';



$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date     = isset($_GET['date']) ? sanitize($_GET['date']) : '';

if (!$doctorId || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Missing doctor_id or date parameter.']);
    exit();
}

$db = Database::getConnection();

// 1. Determine Day of Week for requested Date
$dayOfWeek = date('l', strtotime($date));

// 2. Query Doctor Availability for this Day
$stmtAvail = $db->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ? AND day_of_week = ? AND status = 'active'");
$stmtAvail->execute([$doctorId, $dayOfWeek]);
$availability = $stmtAvail->fetch();

if (!$availability) {
    echo json_encode(['success' => true, 'slots' => [], 'message' => "Doctor has no active clinic hours scheduled on {$dayOfWeek}s."]);
    exit();
}

// 3. Generate Interval Time Slots
$startTime    = strtotime($date . ' ' . $availability['start_time']);
$endTime      = strtotime($date . ' ' . $availability['end_time']);
$durationSecs = $availability['slot_duration'] * 60;

// 4. Query Existing Bookings for this Doctor & Date to prevent Double-Booking
$stmtBooked = $db->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('Cancelled')");
$stmtBooked->execute([$doctorId, $date]);
$bookedTimes = $stmtBooked->fetchAll(PDO::FETCH_COLUMN);

// Normalize booked times to HH:MM:SS format
$bookedMap = array_map(function($t) {
    return date('H:i:s', strtotime($t));
}, $bookedTimes);

$slots = [];
$current = $startTime;

while ($current + $durationSecs <= $endTime) {
    $slotTimeStr = date('H:i:s', $current);
    $displayLabel = date('h:i A', $current);

    $isBooked = in_array($slotTimeStr, $bookedMap);
    
    // Check if slot is in the past for today's date
    $isPast = false;
    if ($date === date('Y-m-d') && $current < time()) {
        $isPast = true;
    }

    $slots[] = [
        'time'      => $slotTimeStr,
        'label'     => $displayLabel,
        'available' => !$isBooked && !$isPast,
        'reason'    => $isBooked ? 'Already Booked' : ($isPast ? 'Time Elapsed' : 'Available')
    ];

    $current += $durationSecs;
}

echo json_encode(['success' => true, 'slots' => $slots]);
exit();