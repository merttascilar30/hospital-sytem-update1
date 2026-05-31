<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

hs_redirect_if_logged_in();

$errors = [];
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$gender = '';
$birthDate = '';
$password = '';
$confirmPassword = '';
$securityQuestion = '';
$securityAnswer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birthDate = $_POST['birth_date'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $securityQuestion = $_POST['security_question'] ?? '';
    $securityAnswer = trim($_POST['security_answer'] ?? '');

    // Form Doğrulamaları
    if ($firstName === '') { $errors['first_name'] = 'First name is required.'; }
    if ($lastName === '') { $errors['last_name'] = 'Last name is required.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Please enter a valid email address.'; }
    if ($phone === '') { $errors['phone'] = 'Phone number is required.'; }
    if ($gender === '') { $errors['gender'] = 'Please select your gender.'; }
    if ($birthDate === '') { $errors['birth_date'] = 'Birth date is required.'; }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters long and contain letters and numbers.';
    }
    if ($password !== $confirmPassword) { $errors['confirm_password'] = 'Passwords do not match.'; }
    if ($securityQuestion === '') { $errors['security_question'] = 'Please select a security question.'; }
    if ($securityAnswer === '') { $errors['security_answer'] = 'Security answer is required.'; }

    // Çift E-posta Kayıt Engelleyicisi
    if (empty($errors)) {
        $checkEmailStmt = $mysqli->prepare("SELECT patient_id FROM patients WHERE email = ? LIMIT 1");
        if ($checkEmailStmt) {
            $checkEmailStmt->bind_param("s", $email);
            $checkEmailStmt->execute();
            $checkEmailStmt->store_result();
            if ($checkEmailStmt->num_rows > 0) {
                $errors['email'] = "This email address is already registered.";
            }
            $checkEmailStmt->close();
        }
    }

    // Veritabanına Kayıt
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $normalizedAnswer = strtolower(trim($securityAnswer));
        $securityAnswerHash = password_hash($normalizedAnswer, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare(
            'INSERT INTO patients (first_name, last_name, email, phone, gender, birth_date, password_hash, security_question, security_answer) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $stmt->bind_param('sssssssss', $firstName, $lastName, $email, $phone, $gender, $birthDate, $passwordHash, $securityQuestion, $securityAnswerHash);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: login.php?registered=success');
                exit;
            } else {
                $errors['general'] = 'An unexpected database error occurred. Please try again.';
                $stmt->close();
            }
        } else {
            $errors['general'] = 'Could not prepare database statement.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Patient - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light auth-page">

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="fa-solid fa-hospital-user me-2"></i>Çukurova Hospital
        </a>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm auth-card">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-1">Create Patient Account</h4>
                    <p class="mb-0 small auth-card-subtitle">Please fill out the form below to register</p>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" name="first_name" value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?php echo $errors['first_name']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?php echo $errors['last_name']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" name="phone" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 05551234567" required>
                                <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?php echo $errors['phone']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" name="gender" required>
                                    <option value="">Select...</option>
                                    <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['gender'])): ?><div class="invalid-feedback"><?php echo $errors['gender']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control <?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>" name="birth_date" value="<?php echo htmlspecialchars($birthDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['birth_date'])): ?><div class="invalid-feedback"><?php echo $errors['birth_date']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" name="password" required>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-12">
                                <hr class="my-3">
                                <h5 class="text-secondary h6 mb-3"><i class="fa-solid fa-shield-halved me-1"></i> Security Verification</h5>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Security Question</label>
                                <select class="form-select <?php echo isset($errors['security_question']) ? 'is-invalid' : ''; ?>" name="security_question" required>
                                    <option value="">Select...</option>
                                    <option value="What is your mother's maiden name?" <?php echo $securityQuestion === "What is your mother's maiden name?" ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                                    <option value="What was the name of your first pet?" <?php echo $securityQuestion === "What was the name of your first pet?" ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                                    <option value="What is your favorite book?" <?php echo $securityQuestion === "What is your favorite book?" ? 'selected' : ''; ?>>What is your favorite book?</option>
                                </select>
                                <?php if (isset($errors['security_question'])): ?><div class="invalid-feedback"><?php echo $errors['security_question']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Your Answer</label>
                                <input type="text" class="form-control <?php echo isset($errors['security_answer']) ? 'is-invalid' : ''; ?>" name="security_answer" value="<?php echo htmlspecialchars($securityAnswer, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['security_answer'])): ?><div class="invalid-feedback"><?php echo $errors['security_answer']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-12 pt-4">
                                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">Register</button>
                                <div class="text-center text-muted small">
                                    Already have a patient account? <a href="login.php" class="fw-semibold text-primary text-decoration-none">Sign In Here</a>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>