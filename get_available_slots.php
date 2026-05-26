<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

hs_start_session();
if (!hs_is_logged_in() || hs_current_role() !== 'patient') {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$excludeAppointmentId = isset($_GET['exclude_appointment_id']) ? (int)$_GET['exclude_appointment_id'] : 0;

// Basic validation: require doctor and valid date (YYYY-MM-DD)
if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$patientId = (int)$_SESSION['user_id'];
$excludeValid = false;
if ($excludeAppointmentId > 0) {
    $verifyStmt = $mysqli->prepare(
        'SELECT 1 FROM appointments
         WHERE appointment_id = ? AND patient_id = ? AND doctor_id = ?
         LIMIT 1'
    );
    if ($verifyStmt) {
        $verifyStmt->bind_param('iii', $excludeAppointmentId, $patientId, $doctorId);
        $verifyStmt->execute();
        $verifyRes = $verifyStmt->get_result();
        $excludeValid = $verifyRes && $verifyRes->fetch_row();
        $verifyStmt->close();
    }
}

$bookedTimes = [];

$sql = "SELECT appointment_datetime
        FROM appointments
        WHERE doctor_id = ?
          AND DATE(appointment_datetime) = ?";
if ($excludeValid) {
    $sql .= ' AND appointment_id <> ?';
}
$sql .= ' ORDER BY appointment_datetime';

$stmt = $mysqli->prepare($sql);

if ($stmt) {
    $doctor_id_param = $doctorId;
    $date_param = $date;
    if ($excludeValid) {
        $exclude_param = $excludeAppointmentId;
        $stmt->bind_param('isi', $doctor_id_param, $date_param, $exclude_param);
    } else {
        $stmt->bind_param('is', $doctor_id_param, $date_param);
    }
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

