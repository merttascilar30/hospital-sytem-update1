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

    if ($department_id === '') {
        $errors['department_id'] = 'Please select a department.';
    }

    if ($doctor_id === '') {
        $errors['doctor_id'] = 'Please select a doctor.';
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
<script>
    // Client-side filtering of doctors by department
    (function () {
        const departmentSelect = document.getElementById('department_id');
        const doctorSelect = document.getElementById('doctor_id');
        const dateInput = document.getElementById('appointment_date');
        const timeSlotsContainer = document.getElementById('time-slots');
        const hiddenTimeInput = document.getElementById('appointment_time');

        function filterDoctors() {
            const selectedDept = departmentSelect.value;
            const options = doctorSelect.querySelectorAll('option[data-department]');
            options.forEach(option => {
                if (!selectedDept || option.getAttribute('data-department') === selectedDept) {
                    option.hidden = false;
                } else {
                    option.hidden = true;
                }
            });
        }

        function clearTimeSelection() {
            hiddenTimeInput.value = '';
            if (timeSlotsContainer) {
                const buttons = timeSlotsContainer.querySelectorAll('button');
                buttons.forEach(btn => btn.classList.remove('active'));
            }
        }

        function generateTimeSlots(bookedTimes, selectedDate) {
            if (!timeSlotsContainer) {
                return;
            }
            timeSlotsContainer.innerHTML = '';

            const startMinutes = 8 * 60; // 08:00
            const endMinutes = 17 * 60;  // 17:00 (exclusive)

            const now = new Date();
            const todayStr = now.toISOString().slice(0, 10);

            for (let m = startMinutes; m < endMinutes; m += 15) {
                const hour = String(Math.floor(m / 60)).padStart(2, '0');
                const minute = String(m % 60).padStart(2, '0');
                const timeStr = `${hour}:${minute}`;

                const col = document.createElement('div');
                col.className = 'col-4 col-sm-3 col-md-3';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm w-100';
                btn.textContent = timeStr;

                const isBooked = bookedTimes.includes(timeStr);

                let isInPast = false;
                if (selectedDate === todayStr) {
                    const slotDateTime = new Date(selectedDate + 'T' + timeStr + ':00');
                    if (slotDateTime <= now) {
                        isInPast = true;
                    }
                }

                if (isBooked) {
                    btn.classList.add('btn-danger');
                    btn.disabled = true;
                } else if (isInPast) {
                    btn.classList.add('btn-outline-secondary');
                    btn.disabled = true;
                } else {
                    btn.classList.add('btn-success');
                    btn.addEventListener('click', function () {
                        // Clear previous selection
                        const buttons = timeSlotsContainer.querySelectorAll('button');
                        buttons.forEach(b => b.classList.remove('active'));

                        btn.classList.add('active');
                        hiddenTimeInput.value = timeStr;
                    });
                }

                col.appendChild(btn);
                timeSlotsContainer.appendChild(col);
            }
        }

        async function loadAvailableSlots() {
            clearTimeSelection();

            const doctorId = doctorSelect ? doctorSelect.value : '';
            const dateVal = dateInput ? dateInput.value : '';

            if (!doctorId || !dateVal) {
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '';
                }
                return;
            }

            try {
                const response = await fetch(`get_available_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(dateVal)}`);
                if (!response.ok) {
                    throw new Error('Failed to load available slots.');
                }
                const bookedTimes = await response.json();
                generateTimeSlots(Array.isArray(bookedTimes) ? bookedTimes : [], dateVal);
            } catch (e) {
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '<div class="col-12 text-danger small">Unable to load time slots. Please try again later.</div>';
                }
            }
        }

        if (departmentSelect && doctorSelect) {
            departmentSelect.addEventListener('change', () => {
                filterDoctors();
                loadAvailableSlots();
            });
            doctorSelect.addEventListener('change', loadAvailableSlots);
            filterDoctors();
        }

        if (dateInput) {
            dateInput.addEventListener('change', loadAvailableSlots);
        }
    })();
</script>
</body>
</html>

