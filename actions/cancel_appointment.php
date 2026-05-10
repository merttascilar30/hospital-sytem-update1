<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$patientId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;

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

header('Location: ../index.php');
exit;

