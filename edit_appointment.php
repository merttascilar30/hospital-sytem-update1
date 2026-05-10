<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$patientId = (int)$_SESSION['user_id'];
$errors = [];
$success = false;
$submitted = false;

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$appointment_datetime = '';
$notes = '';

// Load appointment for this patient
if ($appointment_id > 0) {
    $stmt = $mysqli->prepare(
        "SELECT appointment_id, appointment_datetime, notes
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
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['appointment_datetime']);
            if ($dt) {
                $appointment_datetime = $dt->format('Y-m-d\TH:i');
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
    $appointment_datetime = trim($_POST['appointment_datetime'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($appointment_datetime === '') {
        $errors['appointment_datetime'] = 'Please select a date and time.';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $appointment_datetime);
        if (!$dt) {
            $errors['appointment_datetime'] = 'Please enter a valid date and time.';
        } else {
            // Prevent updating an appointment to a past date, including earlier hours today
            $now = new DateTime('now');
            $selectedDate = $dt->format('Y-m-d');
            $todayDate = $now->format('Y-m-d');

            if ($selectedDate < $todayDate || ($selectedDate === $todayDate && $dt <= $now)) {
                $errors['appointment_datetime'] = 'You cannot book an appointment for a past date.';
            }
        }
    }

    if (empty($errors)) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $appointment_datetime);
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
                    $errors['appointment_datetime'] = 'This doctor already has an appointment at the selected time.';
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
        <div class="col-lg-6">
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
                            <div class="mb-3">
                                <label for="appointment_datetime" class="form-label">Date &amp; Time</label>
                                <input type="datetime-local"
                                       class="form-control <?php echo isset($errors['appointment_datetime']) ? 'is-invalid' : ''; ?>"
                                       id="appointment_datetime" name="appointment_datetime"
                                       value="<?php echo htmlspecialchars($appointment_datetime, ENT_QUOTES, 'UTF-8'); ?>"
                                       required>
                                <?php if (isset($errors['appointment_datetime'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['appointment_datetime'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea
                                    class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>"
                                    id="notes" name="notes" rows="3"
                                    placeholder="Update any details about your visit."><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
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
</body>
</html>

