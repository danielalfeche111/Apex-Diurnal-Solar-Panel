<?php
/**
 * services/check_availability.php - Real-Time Inspection & Audit Slot Availability Checker
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../config.php';

$date = trim($_GET['date'] ?? $_POST['date'] ?? '');

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid date parameter (YYYY-MM-DD) is required.'
    ]);
    exit;
}

// Check if date is in the past
$today = date('Y-m-d');
if ($date < $today) {
    echo json_encode([
        'success' => true,
        'date' => $date,
        'is_past' => true,
        'booked_slots' => [
            '8:00 AM - 10:00 AM',
            '10:00 AM - 12:00 PM',
            '1:00 PM - 3:00 PM',
            '3:00 PM - 5:00 PM'
        ],
        'available_slots' => []
    ]);
    exit;
}

try {
    $db = getConnection();

    $standardSlots = [
        '8:00 AM - 10:00 AM',
        '10:00 AM - 12:00 PM',
        '1:00 PM - 3:00 PM',
        '3:00 PM - 5:00 PM'
    ];

    // Query active service bookings for the given date
    $stmt = $db->prepare("
        SELECT preferred_time_slot 
        FROM service_bookings 
        WHERE preferred_date = :pdate 
          AND status NOT IN ('cancelled')
    ");
    $stmt->execute([':pdate' => $date]);
    $rawSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $bookedSlots = [];
    foreach ($rawSlots as $slot) {
        $trimmed = trim($slot);
        if (empty($trimmed)) continue;

        $lower = strtolower($trimmed);
        if ($lower === 'morning') {
            $bookedSlots[] = '8:00 AM - 10:00 AM';
            $bookedSlots[] = '10:00 AM - 12:00 PM';
        } elseif ($lower === 'afternoon') {
            $bookedSlots[] = '1:00 PM - 3:00 PM';
            $bookedSlots[] = '3:00 PM - 5:00 PM';
        } else {
            foreach ($standardSlots as $std) {
                if (strcasecmp($std, $trimmed) === 0) {
                    $bookedSlots[] = $std;
                    break;
                }
            }
        }
    }

    $bookedSlots = array_values(array_unique($bookedSlots));
    $availableSlots = array_values(array_diff($standardSlots, $bookedSlots));

    echo json_encode([
        'success' => true,
        'date' => $date,
        'is_past' => false,
        'booked_slots' => $bookedSlots,
        'available_slots' => $availableSlots,
        'all_slots' => $standardSlots
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error checking slot availability.'
    ]);
}
