<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

// Basic validation: require doctor and valid date (YYYY-MM-DD)
if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$bookedTimes = [];

$stmt = $mysqli->prepare(
    "SELECT appointment_datetime
     FROM appointments
     WHERE doctor_id = ?
       AND DATE(appointment_datetime) = ?
     ORDER BY appointment_datetime"
);

if ($stmt) {
    $doctor_id_param = $doctorId;
    $date_param = $date;
    $stmt->bind_param('is', $doctor_id_param, $date_param);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['appointment_datetime']);
        if ($dt) {
            $bookedTimes[] = $dt->format('H:i');
        }
    }

    $result->free();
    $stmt->close();
}

echo json_encode($bookedTimes);
exit;

