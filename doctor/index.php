<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_doctor();

$doctorId = (int)($_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: ../login.php?role=doctor');
    exit;
}

$firstName = $_SESSION['user_first_name'] ?? '';
$lastName = $_SESSION['user_last_name'] ?? '';

$appointments = [];
$upcomingCount = 0;

// Upcoming and future appointments (no CURDATE-only filter).
$stmt = $mysqli->prepare(
    'SELECT
        a.appointment_id,
        a.appointment_datetime,
        a.status,
        a.notes,
        p.patient_id,
        p.first_name AS patient_first_name,
        p.last_name  AS patient_last_name,
        dep.name     AS department_name
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     INNER JOIN doctors d ON d.doctor_id = a.doctor_id
     INNER JOIN departments dep ON dep.department_id = d.department_id
     WHERE a.doctor_id = ?
       AND a.appointment_datetime >= NOW()
     ORDER BY a.appointment_datetime ASC'
);

if ($stmt) {
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
        $upcomingCount++;
    }
    $result->free();
    $stmt->close();
}

$pastAppointments = [];
$pastStmt = $mysqli->prepare(
    'SELECT
        a.appointment_id,
        a.appointment_datetime,
        a.status,
        a.notes,
        p.patient_id,
        p.first_name AS patient_first_name,
        p.last_name  AS patient_last_name,
        dep.name     AS department_name
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     INNER JOIN doctors d ON d.doctor_id = a.doctor_id
     INNER JOIN departments dep ON dep.department_id = d.department_id
     WHERE a.doctor_id = ?
       AND a.appointment_datetime < NOW()
     ORDER BY a.appointment_datetime DESC
     LIMIT 20'
);

if ($pastStmt) {
    $pastStmt->bind_param('i', $doctorId);
    $pastStmt->execute();
    $pastResult = $pastStmt->get_result();
    while ($row = $pastResult->fetch_assoc()) {
        $pastAppointments[] = $row;
    }
    $pastResult->free();
    $pastStmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doctor Dashboard - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="index.php">Doctor Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#doctorNav"
                aria-controls="doctorNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="doctorNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Appointments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4 py-md-5">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-0">
                        Welcome, Dr. <?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?>
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <h2 class="h5 mb-0">Upcoming Appointments</h2>
            <span class="badge bg-success"><?php echo (int)$upcomingCount; ?> scheduled</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($appointments)): ?>
                <p class="text-muted p-3 mb-0">No upcoming appointments.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Date &amp; Time</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Polyclinic</th>
                            <th scope="col">Status</th>
                            <th scope="col">Notes</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <?php
                                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['appointment_datetime']);
                                    echo $dt
                                        ? htmlspecialchars($dt->format('d.m.Y H:i'), ENT_QUOTES, 'UTF-8')
                                        : htmlspecialchars($appointment['appointment_datetime'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(
                                        $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['department_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge bg-secondary text-capitalize">
                                        <?php echo htmlspecialchars($appointment['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="table-notes-cell">
                                    <small class="text-muted d-block text-truncate">
                                        <?php echo htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($pastAppointments)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0 text-muted">Recent Past Appointments</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Date &amp; Time</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pastAppointments as $appointment): ?>
                            <tr>
                                <td>
                                    <?php
                                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['appointment_datetime']);
                                    echo $dt
                                        ? htmlspecialchars($dt->format('d.m.Y H:i'), ENT_QUOTES, 'UTF-8')
                                        : htmlspecialchars($appointment['appointment_datetime'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(
                                        $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark text-capitalize border">
                                        <?php echo htmlspecialchars($appointment['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
