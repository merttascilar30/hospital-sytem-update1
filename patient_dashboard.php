<?php
require_once __DIR__ . '/includes/auth.php';

hs_require_patient();

$firstName = $_SESSION['user_first_name'] ?? '';
$lastName = $_SESSION['user_last_name'] ?? '';
$patientId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/includes/db_connection.php';

// Optional status message from redirects
$statusMessage = null;
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $statusMessage = 'Appointment processed successfully!';
}

// Total appointments for this patient via stored procedure
$totalAppointments = 0;
$patientIdForCall = (int)$patientId;
$spStmt = $mysqli->prepare('CALL sp_get_patient_appointment_count(?)');
if ($spStmt) {
    $spStmt->bind_param('i', $patientIdForCall);
    if ($spStmt->execute()) {
        $result = $spStmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && isset($row['total_appointments'])) {
                $totalAppointments = (int)$row['total_appointments'];
            }
            $result->free();
        }
    }
    $spStmt->close();
    // Advance to next result set if any, to avoid "commands out of sync"
    while ($mysqli->more_results() && $mysqli->next_result()) {
        $extraResult = $mysqli->use_result();
        if ($extraResult instanceof mysqli_result) {
            $extraResult->free();
        }
    }
}

// Upcoming appointments for this patient
$appointments = [];
$upcomingStmt = $mysqli->prepare(
    "SELECT
        a.appointment_id,
        a.appointment_datetime,
        a.status,
        a.notes,
        d.first_name AS doctor_first_name,
        d.last_name  AS doctor_last_name,
        dep.name     AS department_name
     FROM appointments a
     INNER JOIN doctors d ON d.doctor_id = a.doctor_id
     INNER JOIN departments dep ON dep.department_id = d.department_id
     WHERE a.patient_id = ?
       AND a.appointment_datetime >= NOW()
     ORDER BY a.appointment_datetime ASC"
);

if ($upcomingStmt) {
    $upcomingStmt->bind_param('i', $patientId);
    $upcomingStmt->execute();
    $result = $upcomingStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $result->free();
    $upcomingStmt->close();
}

// Past appointment history
$pastAppointments = [];
$pastStmt = $mysqli->prepare(
    "SELECT
        a.appointment_id,
        a.appointment_datetime,
        a.status,
        d.first_name AS doctor_first_name,
        d.last_name  AS doctor_last_name,
        dep.name     AS department_name
     FROM appointments a
     INNER JOIN doctors d ON d.doctor_id = a.doctor_id
     INNER JOIN departments dep ON dep.department_id = d.department_id
     WHERE a.patient_id = ?
       AND a.appointment_datetime < NOW()
     ORDER BY a.appointment_datetime DESC"
);

if ($pastStmt) {
    $pastStmt->bind_param('i', $patientId);
    $pastStmt->execute();
    $result = $pastStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pastAppointments[] = $row;
    }
    $result->free();
    $pastStmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="patient_dashboard.php">Hospital Appointment System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <?php if ($statusMessage): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h1 class="h4 mb-3">
                        Welcome,
                        <?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?>!
                    </h1>
                    <p class="text-muted flex-grow-1">
                        From this dashboard you can view, book, edit, and cancel your hospital appointments.
                    </p>
                    <a href="book_appointment.php" class="btn btn-primary mt-2">
                        Book New Appointment
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center mb-3 g-3">
                        <div class="col-12 col-md-6">
                            <h2 class="h5 mb-0">Upcoming Appointments</h2>
                        </div>
                        <div class="col-12 col-md-6 text-md-end">
                            <div class="card border-0 bg-light d-inline-block">
                                <div class="card-body py-2 px-3">
                                    <div class="text-muted small">Total Appointments</div>
                                    <div class="h4 mb-0">
                                        <?php echo htmlspecialchars((string)$totalAppointments, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($appointments)): ?>
                        <p class="text-muted mb-0">
                            You do not have any upcoming appointments.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Date &amp; Time</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Doctor</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Notes</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['appointment_datetime']);
                                            echo $dt ? htmlspecialchars($dt->format('d.m.Y H:i'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($appointment['appointment_datetime'], ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appointment['department_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary text-capitalize">
                                                <?php echo htmlspecialchars($appointment['status'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="table-notes-cell">
                                            <small class="text-muted d-block text-truncate" title="<?php echo htmlspecialchars($appointment['notes'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($appointment['notes'], ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="edit_appointment.php?id=<?php echo (int)$appointment['appointment_id']; ?>"
                                                   class="btn btn-outline-primary">
                                                    Edit
                                                </a>
                                                <form action="actions/cancel_appointment.php" method="post" class="d-inline"
                                                      data-confirm="Are you sure you want to cancel this appointment?">
                                                    <input type="hidden" name="appointment_id"
                                                           value="<?php echo (int)$appointment['appointment_id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Appointment History</h2>
                    <?php if (empty($pastAppointments)): ?>
                        <p class="text-muted mb-0">No past appointments.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Date &amp; Time</th>
                                    <th scope="col">Doctor</th>
                                    <th scope="col">Polyclinic</th>
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
                                        <td><?php echo htmlspecialchars(
                                            $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['department_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary text-capitalize">
                                                <?php echo htmlspecialchars($appointment['status'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="js/script.js"></script>
</body>
</html>

