<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

hs_require_patient();

$patientId = (int)$_SESSION['user_id'];
$errors = [];
$success = false;
$submitted = false;

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doctor_id = 0;
$appointment_date = '';
$appointment_time = '';
$notes = '';

// Load appointment for this patient
if ($appointment_id > 0) {
    $stmt = $mysqli->prepare(
        "SELECT appointment_id, doctor_id, appointment_datetime, notes
         FROM appointments
         WHERE appointment_id = ? AND patient_id = ?
         LIMIT 1"
    );

    if ($stmt) {
        $appointment_id_param = $appointment_id;
        $patient_id_param = $patientId;
        $stmt->bind_param('ii', $appointment_id_param, $patient_id_param);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();

        if ($appointment) {
            $doctor_id = (int)$appointment['doctor_id'];
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['appointment_datetime']);
            if ($dt) {
                $appointment_date = $dt->format('Y-m-d');
                $appointment_time = $dt->format('H:i');
            }
            $notes = $appointment['notes'] ?? '';
        } else {
            $errors['general'] = 'Appointment not found.';
        }
    } else {
        $errors['general'] = 'Could not load appointment.';
    }
} else {
    $errors['general'] = 'Invalid appointment ID.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $submitted = true;
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($notes !== '' && !preg_match('/\A.{0,2000}\z/us', $notes)) {
        $errors['notes'] = 'Notes must be at most 2000 characters.';
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

    $dt = null;
    if ($appointment_date !== '' && $appointment_time !== '' && empty($errors['appointment_date']) && empty($errors['appointment_time'])) {
        $combined = $appointment_date . ' ' . $appointment_time . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $combined);
        if (!$dt) {
            $errors['appointment_date'] = 'Please enter a valid date.';
        } else {
            $now = new DateTime('now');
            if ($dt <= $now) {
                $errors['appointment_date'] = 'You cannot book an appointment for a past date.';
            }
        }
    }

    if (empty($errors)) {
        $appointmentSql = $dt ? $dt->format('Y-m-d H:i:s') : null;

        $stmt = $mysqli->prepare(
            "UPDATE appointments
             SET appointment_datetime = ?, notes = ?
             WHERE appointment_id = ? AND patient_id = ?"
        );

        if ($stmt) {
            $appointment_datetime_param = $appointmentSql;
            $notes_param = $notes;
            $appointment_id_param = $appointment_id;
            $patient_id_param = $patientId;

            $stmt->bind_param(
                'ssii',
                $appointment_datetime_param,
                $notes_param,
                $appointment_id_param,
                $patient_id_param
            );

            if ($stmt->execute()) {
                // Successful update: redirect to dashboard with success status
                header('Location: index.php?status=success');
                exit;
            } else {
                if ($mysqli->errno === 1062) {
                    $errors['appointment_time'] = 'This doctor already has an appointment at the selected time.';
                } else {
                    $errors['general'] = 'An unexpected error occurred while updating the appointment.';
                }
            }

            $stmt->close();
        } else {
            $errors['general'] = 'Could not prepare update statement.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Appointment - Hospital Appointment System</title>
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
                    <h4 class="mb-0">Edit Appointment</h4>
                </div>
                <div class="card-body">
                    <?php if ($submitted && !$success): ?>
                        <div class="alert alert-danger">
                            <?php
                            if (!empty($errors['general'])) {
                                echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8');
                            } else {
                                echo 'The appointment could not be updated. Please check the form for errors.';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($errors['general'])): ?>
                        <form method="post" novalidate>
                            <div id="edit-appointment-meta" class="d-none"
                                 data-doctor-id="<?php echo (int)$doctor_id; ?>"
                                 data-appointment-id="<?php echo (int)$appointment_id; ?>"
                                 aria-hidden="true"></div>
                            <div class="row g-3">
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
                            </div>

                            <div class="mb-3 mt-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea
                                    class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>"
                                    id="notes" name="notes" rows="3"
                                    placeholder="Update any details about your visit."><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php if (isset($errors['notes'])): ?>
                                    <div class="invalid-feedback d-block">
                                        <?php echo htmlspecialchars($errors['notes'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" class="btn btn-primary">
                                    Save Changes
                                </button>
                                <a href="index.php" class="btn btn-link">Back to Dashboard</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
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

