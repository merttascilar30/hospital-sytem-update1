<?php
require_once __DIR__ . '/includes/db_connection.php';

$errors = [];
$success = false;

// Initialize form values
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$birth_date = '';
$gender = '';
$notes = '';
$preferred_department = '';
$terms_accepted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim all incoming values
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $preferred_department = $_POST['preferred_department'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted = isset($_POST['terms']);

    // Regular expressions
    $namePattern = "/^[A-Za-zÇçĞğİıÖöŞşÜü\s'-]{2,}$/u";
    $emailPattern = "/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/";
    $phonePattern = "/^[0-9+\-\s]{7,20}$/";
    // At least 8 chars, one letter, one digit
    $passwordPattern = "/^(?=.*[A-Za-z])(?=.*\d).{8,}$/";

    // Validation
    if ($first_name === '' || !preg_match($namePattern, $first_name)) {
        $errors['first_name'] = 'Please enter a valid first name.';
    }

    if ($last_name === '' || !preg_match($namePattern, $last_name)) {
        $errors['last_name'] = 'Please enter a valid last name.';
    }

    if ($email === '' || !preg_match($emailPattern, $email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match($phonePattern, $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if ($birth_date !== '' && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $birth_date)) {
        $errors['birth_date'] = 'Please enter a valid birth date (YYYY-MM-DD).';
    }

    if (!in_array($gender, ['M', 'F', 'Other'], true)) {
        $errors['gender'] = 'Please select a gender.';
    }

    if (!in_array($preferred_department, ['cardiology', 'neurology', 'orthopedics', 'pediatrics'], true)) {
        $errors['preferred_department'] = 'Please select a preferred department.';
    }

    if ($password === '' || !preg_match($passwordPattern, $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include at least one letter and one number.';
    }

    if ($confirm_password === '' || $confirm_password !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($notes !== '' && !preg_match('/\A.{0,2000}\z/us', $notes)) {
        $errors['notes'] = 'Notes must be at most 2000 characters.';
    }

    if (!$terms_accepted) {
        $errors['terms'] = 'You must accept the terms and conditions.';
    }

    // If no validation errors, insert into database
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Normalize nullable date for bind_param (must be a variable, not an expression)
        $birth_date_param = $birth_date !== '' ? $birth_date : null;

        $stmt = $mysqli->prepare(
            "INSERT INTO patients (first_name, last_name, email, phone, birth_date, gender, password_hash, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

            if ($stmt) {
                $first_name_param = $first_name;
                $last_name_param = $last_name;
                $email_param = $email;
                $phone_param = $phone;
                $gender_param = $gender;
                $password_hash_param = $password_hash;
                $notes_param = $notes;

                $stmt->bind_param(
                    'ssssssss',
                    $first_name_param,
                    $last_name_param,
                    $email_param,
                    $phone_param,
                    $birth_date_param,
                    $gender_param,
                    $password_hash_param,
                    $notes_param
                );

                $mysqli->begin_transaction();
                $insertOk = $stmt->execute();

                if (!$insertOk) {
                    $mysqli->rollback();
                    if ($mysqli->errno === 1062) {
                        $errors['email'] = 'This email is already registered.';
                    } else {
                        $errors['general'] = 'An unexpected error occurred. Please try again later.';
                    }
                    $stmt->close();
                } else {
                    $newPatientId = (int)$mysqli->insert_id;

                    $slugToName = [
                        'cardiology' => 'Cardiology',
                        'neurology' => 'Neurology',
                        'orthopedics' => 'Orthopedics',
                        'pediatrics' => 'Pediatrics',
                    ];
                    $deptName = $slugToName[$preferred_department] ?? '';
                    $departmentIdForPref = null;

                    if ($deptName !== '') {
                        $dStmt = $mysqli->prepare('SELECT department_id FROM departments WHERE name = ? LIMIT 1');
                        if ($dStmt) {
                            $dStmt->bind_param('s', $deptName);
                            $dStmt->execute();
                            $dRes = $dStmt->get_result();
                            $dRow = $dRes ? $dRes->fetch_assoc() : null;
                            $dStmt->close();
                            if ($dRow) {
                                $departmentIdForPref = (int)$dRow['department_id'];
                            }
                        }
                    }

                    $prefOk = true;
                    if ($departmentIdForPref !== null) {
                        $pStmt = $mysqli->prepare(
                            'INSERT INTO patient_preferred_departments (patient_id, department_id) VALUES (?, ?)'
                        );
                        if ($pStmt) {
                            $pStmt->bind_param('ii', $newPatientId, $departmentIdForPref);
                            $prefOk = $pStmt->execute();
                            $pStmt->close();
                        } else {
                            $prefOk = false;
                        }
                    }

                    if (!$prefOk) {
                        $mysqli->rollback();
                        $errors['general'] = 'An unexpected error occurred. Please try again later.';
                        $stmt->close();
                    } else {
                        $mysqli->commit();
                        $stmt->close();
                        $success = true;
                        $first_name = $last_name = $email = $phone = $birth_date = $gender = $notes = $preferred_department = '';
                        $terms_accepted = false;
                    }
                }
            } else {
                $errors['general'] = 'Could not prepare registration statement.';
            }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Registration - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Patient Registration</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Registration successful. You can now log in.
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text"
                                       class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                       id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>"
                                       required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text"
                                       class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                       id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>"
                                       required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email"
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       id="email" name="email"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text"
                                       class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                       id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="birth_date" class="form-label">Birth Date</label>
                                <input type="date"
                                       class="form-control <?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>"
                                       id="birth_date" name="birth_date"
                                       value="<?php echo htmlspecialchars($birth_date, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (isset($errors['birth_date'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['birth_date'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label d-block">Gender</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_m"
                                           value="M" <?php echo $gender === 'M' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="gender_m">Male</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_f"
                                           value="F" <?php echo $gender === 'F' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="gender_f">Female</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_o"
                                           value="Other" <?php echo $gender === 'Other' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="gender_o">Other</label>
                                </div>
                                <?php if (isset($errors['gender'])): ?>
                                    <div class="text-danger small mt-1">
                                        <?php echo htmlspecialchars($errors['gender'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="preferred_department" class="form-label">Preferred Department</label>
                                <select
                                    class="form-select <?php echo isset($errors['preferred_department']) ? 'is-invalid' : ''; ?>"
                                    id="preferred_department" name="preferred_department" required>
                                    <option value="">Choose...</option>
                                    <option value="cardiology" <?php echo $preferred_department === 'cardiology' ? 'selected' : ''; ?>>
                                        Cardiology
                                    </option>
                                    <option value="neurology" <?php echo $preferred_department === 'neurology' ? 'selected' : ''; ?>>
                                        Neurology
                                    </option>
                                    <option value="orthopedics" <?php echo $preferred_department === 'orthopedics' ? 'selected' : ''; ?>>
                                        Orthopedics
                                    </option>
                                    <option value="pediatrics" <?php echo $preferred_department === 'pediatrics' ? 'selected' : ''; ?>>
                                        Pediatrics
                                    </option>
                                </select>
                                <?php if (isset($errors['preferred_department'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['preferred_department'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password"
                                       class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                       id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password"
                                       class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Medical Notes / Additional Information</label>
                                <textarea
                                    class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>"
                                    id="notes" name="notes" rows="3"
                                    placeholder="Describe any important medical history, allergies, or other notes."><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php if (isset($errors['notes'])): ?>
                                    <div class="invalid-feedback d-block">
                                        <?php echo htmlspecialchars($errors['notes'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input <?php echo isset($errors['terms']) ? 'is-invalid' : ''; ?>"
                                           type="checkbox" value="1" id="terms" name="terms"
                                        <?php echo $terms_accepted ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="terms">
                                        I agree to the processing of my personal data according to the hospital's privacy policy.
                                    </label>
                                    <?php if (isset($errors['terms'])): ?>
                                        <div class="invalid-feedback d-block">
                                            <?php echo htmlspecialchars($errors['terms'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Register
                                </button>
                                <a href="login.php" class="btn btn-link">Already have an account? Login</a>
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

