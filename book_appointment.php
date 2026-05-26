<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

hs_require_patient();

$patientId = (int)$_SESSION['user_id'];

$errors = [];
$success = false;
$submitted = false;

$department_id = '';
$doctor_id = '';
$appointment_date = '';
$appointment_time = '';
$notes = '';

// Fetch departments
$departments = [];
$deptResult = $mysqli->query("SELECT department_id, name FROM departments ORDER BY name");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
    $deptResult->free();
}

// Fetch doctors (for client-side filtering)
$doctors = [];
$docResult = $mysqli->query(
    "SELECT doctor_id, first_name, last_name, department_id
     FROM doctors
     ORDER BY first_name, last_name"
);
if ($docResult) {
    while ($row = $docResult->fetch_assoc()) {
        $doctors[] = $row;
    }
    $docResult->free();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $department_id = $_POST['department_id'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($notes !== '' && !preg_match('/\A.{0,2000}\z/us', $notes)) {
        $errors['notes'] = 'Notes must be at most 2000 characters.';
    }

    if ($department_id === '') {
        $errors['department_id'] = 'Please select a department.';
    } elseif (!preg_match('/^\d{1,10}$/', (string)$department_id)) {
        $errors['department_id'] = 'Please select a valid department.';
    }

    if ($doctor_id === '') {
        $errors['doctor_id'] = 'Please select a doctor.';
    } elseif (!preg_match('/^\d{1,10}$/', (string)$doctor_id)) {
        $errors['doctor_id'] = 'Please select a valid doctor.';
    }

    if (empty($errors['department_id']) && empty($errors['doctor_id'])) {
        $deptIdInt = (int)$department_id;
        $docIdInt = (int)$doctor_id;
        if ($deptIdInt > 0 && $docIdInt > 0) {
            $matchStmt = $mysqli->prepare(
                'SELECT 1 FROM doctors WHERE doctor_id = ? AND department_id = ? LIMIT 1'
            );
            if ($matchStmt) {
                $matchStmt->bind_param('ii', $docIdInt, $deptIdInt);
                $matchStmt->execute();
                $matchRes = $matchStmt->get_result();
                $exists = $matchRes ? $matchRes->fetch_row() : null;
                $matchStmt->close();
                if (!$exists) {
                    $errors['doctor_id'] = 'The selected doctor does not belong to the chosen department.';
                }
            }
        }
    }

    if ($appointment_date === '') {
        $errors['appointment_date'] = 'Please select a date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        $errors['appointment_date'] = 'Please enter a valid date (YYYY-MM-DD).';
    }

    if ($appointment_time === '') {
        $errors['appointment_time'] = 'Please select a time slot.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $appointment_time)) {
        $errors['appointment_time'] = 'Please select a valid time slot.';
    }

    if ($appointment_date !== '' && $appointment_time !== '' && empty($errors['appointment_date']) && empty($errors['appointment_time'])) {
        $combined = $appointment_date . ' ' . $appointment_time . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $combined);
        if (!$dt) {
            $errors['appointment_date'] = 'Please enter a valid date.';
        } else {
            // Prevent booking appointments in the past, including earlier hours today
            $now = new DateTime('now');
            if ($dt <= $now) {
                $errors['appointment_date'] = 'You cannot book an appointment for a past date.';
            }
        }
    }

    if (empty($errors)) {
        $combined = $appointment_date . ' ' . $appointment_time . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $combined);
        $appointmentSql = $dt ? $dt->format('Y-m-d H:i:s') : null;

        // Final concurrency check: ensure no existing appointment at the same time for this doctor
        $checkStmt = $mysqli->prepare(
            "SELECT appointment_id
             FROM appointments
             WHERE doctor_id = ? AND appointment_datetime = ?
             LIMIT 1"
        );

        if ($checkStmt) {
            $doctor_id_check = (int)$doctor_id;
            $appointment_datetime_check = $appointmentSql;
            $checkStmt->bind_param('is', $doctor_id_check, $appointment_datetime_check);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $existing = $checkResult ? $checkResult->fetch_assoc() : null;
            if ($checkResult) {
                $checkResult->free();
            }
            $checkStmt->close();

            if ($existing) {
                $errors['appointment_time'] = 'This doctor already has an appointment at the selected time.';
            }
        }

        if (empty($errors)) {
            $stmt = $mysqli->prepare(
                "INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, notes)
                 VALUES (?, ?, ?, ?)"
            );

            if ($stmt) {
                $patient_id_param = $patientId;
                $doctor_id_param = (int)$doctor_id;
                $appointment_datetime_param = $appointmentSql;
                $notes_param = $notes;

                $stmt->bind_param(
                    'iiss',
                    $patient_id_param,
                    $doctor_id_param,
                    $appointment_datetime_param,
                    $notes_param
                );

                if ($stmt->execute()) {
                    // Successful booking: redirect to dashboard with success status
                    header('Location: index.php?status=success');
                    exit;
                } else {
                    if ($mysqli->errno === 1062) {
                        $errors['appointment_time'] = 'This doctor already has an appointment at the selected time.';
                    } else {
                        $errors['general'] = 'An unexpected error occurred while booking the appointment.';
                    }
                }

                $stmt->close();
            } else {
                $errors['general'] = 'Could not prepare appointment statement.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Appointment - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">Hospital Appointment System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Book a New Appointment</h4>
                </div>
                <div class="card-body">
                    <?php if ($submitted && !$success): ?>
                        <div class="alert alert-danger">
                            <?php
                            if (!empty($errors['general'])) {
                                echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8');
                            } else {
                                echo 'The appointment could not be booked. Please check the form for errors.';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="department_id" class="form-label">Department</label>
                                <select
                                    class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>"
                                    id="department_id" name="department_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo (int)$dept['department_id']; ?>"
                                            <?php echo (string)$department_id === (string)$dept['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['department_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['department_id'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="doctor_id" class="form-label">Doctor</label>
                                <select
                                    class="form-select <?php echo isset($errors['doctor_id']) ? 'is-invalid' : ''; ?>"
                                    id="doctor_id" name="doctor_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo (int)$doc['doctor_id']; ?>"
                                                data-department="<?php echo (int)$doc['department_id']; ?>"
                                            <?php echo (string)$doctor_id === (string)$doc['doctor_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['doctor_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['doctor_id'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Date</label>
                                <input type="date"
                                       class="form-control <?php echo isset($errors['appointment_date']) ? 'is-invalid' : ''; ?>"
                                       id="appointment_date" name="appointment_date"
                                       value="<?php echo htmlspecialchars($appointment_date, ENT_QUOTES, 'UTF-8'); ?>"
                                       required>
                                <?php if (isset($errors['appointment_date'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['appointment_date'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label d-block">Time Slots</label>
                                <input type="hidden" id="appointment_time" name="appointment_time"
                                       value="<?php echo htmlspecialchars($appointment_time, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="time-slots" class="row g-2">
                                    <!-- Time slot buttons will be injected here via JavaScript -->
                                </div>
                                <?php if (isset($errors['appointment_time'])): ?>
                                    <div class="text-danger small mt-1">
                                        <?php echo htmlspecialchars($errors['appointment_time'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea
                                    class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>"
                                    id="notes" name="notes" rows="3"
                                    placeholder="Add any details about your visit."><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php if (isset($errors['notes'])): ?>
                                    <div class="invalid-feedback d-block">
                                        <?php echo htmlspecialchars($errors['notes'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Book Appointment
                                </button>
                                <a href="index.php" class="btn btn-link">Back to Dashboard</a>
                            </div>
                        </div>
                    </form>
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

