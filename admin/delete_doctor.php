<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$adminId = (int)$_SESSION['user_id'];

if ($doctorId <= 0) {
    header('Location: index.php');
    exit;
}

hs_set_admin_context($mysqli, $adminId);

$stmt = $mysqli->prepare('DELETE FROM doctors WHERE doctor_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $doctorId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        hs_clear_admin_context($mysqli);
        header('Location: index.php?status=doctor_deleted');
        exit;
    }
    $stmt->close();
}

hs_clear_admin_context($mysqli);
header('Location: index.php?status=delete_failed');
exit;
