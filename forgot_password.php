<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

hs_redirect_if_logged_in();

$errors = [];
$success = false;
$email = '';

$emailPattern = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($email === '' || !preg_match($emailPattern, $email)) {
        $errors['email'] = 'Please enter a valid email address (validated with server-side regex).';
    }

    if ($new_password === '' || !preg_match($passwordPattern, $new_password)) {
        $errors['new_password'] = 'New password must be at least 8 characters with at least one letter and one number.';
    }

    if ($confirm_password === '' || $confirm_password !== $new_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare(
            'SELECT patient_id FROM patients WHERE email = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $errors['email'] = 'No patient account was found with this email address.';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $patient_id = (int)$row['patient_id'];

                $update = $mysqli->prepare(
                    'UPDATE patients SET password_hash = ? WHERE patient_id = ? AND email = ?'
                );
                if ($update) {
                    $update->bind_param('sis', $password_hash, $patient_id, $email);
                    if ($update->execute() && $update->affected_rows >= 0) {
                        $success = true;
                        $email = '';
                    } else {
                        $errors['general'] = 'Could not update password. Please try again.';
                    }
                    $update->close();
                } else {
                    $errors['general'] = 'Could not prepare password update.';
                }
            }
        } else {
            $errors['general'] = 'Could not verify email address.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light auth-page">
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="card shadow-sm auth-card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Reset Patient Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Your password has been updated. You can now <a href="login.php">log in</a>.
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted small">
                            Enter your registered email. The address is validated on the server using a regular expression before any database lookup.
                        </p>

                        <form method="post" novalidate>
                            <div class="mb-3">
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

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password"
                                       class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                       id="new_password" name="new_password" required>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['new_password'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password"
                                       class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                                <a href="login.php" class="btn btn-link">Back to Login</a>
                            </div>
                        </form>
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
