<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_patient();

$patientId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawId = isset($_POST['appointment_id']) ? trim((string)$_POST['appointment_id']) : '';
    $appointment_id = ($rawId !== '' && preg_match('/^\d{1,10}$/', $rawId)) ? (int)$rawId : 0;

    if ($appointment_id > 0) {
        $stmt = $mysqli->prepare(
            "DELETE FROM appointments
             WHERE appointment_id = ? AND patient_id = ?"
        );

        if ($stmt) {
            $appointment_id_param = $appointment_id;
            $patient_id_param = $patientId;

            $stmt->bind_param(
                'ii',
                $appointment_id_param,
                $patient_id_param
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}

header('Location: ../patient_dashboard.php');
exit;

