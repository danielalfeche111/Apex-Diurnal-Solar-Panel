<?php
/**
 * admin/schedule/calendar.php - JSON API Endpoint for FullCalendar
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getConnection();
if (!$db) {
    echo json_encode([]);
    exit;
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t', strtotime('+2 months'));
$service_type = $_GET['service_type'] ?? '';
$status = $_GET['status'] ?? '';

$where = ["preferred_date BETWEEN :start AND :end"];
$params = [
    ':start' => substr($start, 0, 10),
    ':end' => substr($end, 0, 10)
];

if (!empty($service_type) && in_array($service_type, ['consultation', 'installation', 'maintenance'])) {
    $where[] = "service_type = :stype";
    $params[':stype'] = $service_type;
}

if (!empty($status) && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$where_sql = implode(' AND ', $where);

$sql = "SELECT * FROM service_bookings WHERE $where_sql ORDER BY preferred_date ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
foreach ($bookings as $b) {
    $slot = trim($b['preferred_time_slot'] ?? '');
    $slotLower = strtolower($slot);

    if (strpos($slot, '8:00') !== false) {
        $timeStart = '08:00:00';
        $timeEnd   = '10:00:00';
    } elseif (strpos($slot, '10:00') !== false) {
        $timeStart = '10:00:00';
        $timeEnd   = '12:00:00';
    } elseif (strpos($slot, '1:00') !== false) {
        $timeStart = '13:00:00';
        $timeEnd   = '15:00:00';
    } elseif (strpos($slot, '3:00') !== false) {
        $timeStart = '15:00:00';
        $timeEnd   = '17:00:00';
    } elseif ($slotLower === 'afternoon') {
        $timeStart = '13:30:00';
        $timeEnd   = '16:00:00';
    } else {
        $timeStart = '09:00:00';
        $timeEnd   = '11:30:00';
    }

    $startIso = $b['preferred_date'] . 'T' . $timeStart;
    $endIso = $b['preferred_date'] . 'T' . $timeEnd;

    // Color code based on status & service type
    $bgColor = '#1b335f'; // default navy
    $borderColor = '#1b335f';
    $textColor = '#ffffff';

    if ($b['status'] === 'pending') {
        $bgColor = '#f59e0b'; // amber
        $borderColor = '#d97706';
        $textColor = '#ffffff';
    } elseif ($b['status'] === 'confirmed') {
        $bgColor = '#0284c7'; // info blue
        $borderColor = '#0369a1';
    } elseif ($b['status'] === 'completed') {
        $bgColor = '#10b981'; // green
        $borderColor = '#059669';
    } elseif ($b['status'] === 'cancelled') {
        $bgColor = '#ef4444'; // red
        $borderColor = '#dc2626';
    }

    $events[] = [
        'id' => (string)$b['id'],
        'title' => strtoupper($b['service_type']) . ': ' . $b['customer_name'],
        'start' => $startIso,
        'end' => $endIso,
        'backgroundColor' => $bgColor,
        'borderColor' => $borderColor,
        'textColor' => $textColor,
        'extendedProps' => [
            'booking_reference' => $b['booking_reference'],
            'customer_name' => $b['customer_name'],
            'customer_email' => $b['customer_email'],
            'customer_phone' => $b['customer_phone'],
            'service_type' => $b['service_type'],
            'status' => $b['status'],
            'time_slot' => ucfirst($b['preferred_time_slot']),
            'address' => $b['address'],
            'access_notes' => $b['access_notes'] ?? '',
            'assigned_technician' => $b['assigned_technician'] ?? 'Unassigned',
            'order_id' => $b['order_id'] ?? null
        ]
    ];
}

echo json_encode($events);
