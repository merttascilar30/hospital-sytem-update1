<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';

hs_redirect_if_logged_in();

$errors = [];
$role = 'patient';
$email = '';
$username = '';

$emailPattern = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
$usernamePattern = '/^[A-Za-z0-9._-]{3,50}$/';
$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'patient';
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!in_array($role, ['patient', 'doctor', 'admin'], true)) {
        $errors['general'] = 'Invalid role selected.';
    }

    if ($role === 'patient') {
        if ($email === '' || !preg_match($emailPattern, $email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
    } else {
        if ($username === '' || !preg_match($usernamePattern, $username)) {
            $errors['username'] = 'Please enter a valid username (3–50 characters, letters, numbers, . _ -).';
        }
    }

    if ($password === '' || !preg_match($passwordPattern, $password)) {
        $errors['password'] = 'Password must be at least 8 characters with at least one letter and one number.';
    }

    if (empty($errors)) {
        if ($role === 'patient') {
            $stmt = $mysqli->prepare(
                'SELECT patient_id, first_name, last_name, email, password_hash
                 FROM patients WHERE email = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password_hash'])) {
                    hs_login_user((int)$user['patient_id'], 'patient', [
                        'user_first_name' => $user['first_name'],
                        'user_last_name' => $user['last_name'],
                        'user_email' => $user['email'],
                    ]);
                    header('Location: index.php');
                    exit;
                }
                $errors['general'] = 'Invalid email or password.';
            } else {
                $errors['general'] = 'Could not prepare login statement.';
            }
        } elseif ($role === 'doctor') {
            $stmt = $mysqli->prepare(
                'SELECT doctor_id, first_name, last_name, username, password_hash, is_active
                 FROM doctors WHERE username = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && (int)$user['is_active'] === 1 && !empty($user['password_hash'])
                    && password_verify($password, $user['password_hash'])) {
                    hs_login_user((int)$user['doctor_id'], 'doctor', [
                        'user_first_name' => $user['first_name'],
                        'user_last_name' => $user['last_name'],
                        'user_username' => $user['username'],
                    ]);
                    header('Location: doctor/index.php');
                    exit;
                }
                $errors['general'] = 'Invalid username or password.';
            } else {
                $errors['general'] = 'Could not prepare login statement.';
            }
        } else {
            $stmt = $mysqli->prepare(
                'SELECT admin_id, username, full_name, password_hash
                 FROM admins WHERE username = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password_hash'])) {
                    hs_login_user((int)$user['admin_id'], 'admin', [
                        'user_full_name' => $user['full_name'],
                        'user_username' => $user['username'],
                    ]);
                    header('Location: admin/index.php');
                    exit;
                }
                $errors['general'] = 'Invalid username or password.';
            } else {
                $errors['general'] = 'Could not prepare login statement.';
            }
        }
    }
} elseif (isset($_GET['role']) && in_array($_GET['role'], ['patient', 'doctor', 'admin'], true)) {
    $role = $_GET['role'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light auth-page">
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8 col-xl-6">
            <div class="card shadow-sm auth-card">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-1">Hospital Appointment System</h4>
                    <p class="mb-0 small auth-card-subtitle">Sign in to your account</p>
                </div>
                <div class="card-body p-3 p-md-4">
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-pills nav-fill mb-4 auth-role-tabs" id="loginRoleTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link <?php echo $role === 'patient' ? 'active' : ''; ?>"
                                    data-login-role="patient" role="tab"
                                    aria-selected="<?php echo $role === 'patient' ? 'true' : 'false'; ?>">
                                Patient
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link <?php echo $role === 'doctor' ? 'active' : ''; ?>"
                                    data-login-role="doctor" role="tab"
                                    aria-selected="<?php echo $role === 'doctor' ? 'true' : 'false'; ?>">
                                Doctor
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link <?php echo $role === 'admin' ? 'active' : ''; ?>"
                                    data-login-role="admin" role="tab"
                                    aria-selected="<?php echo $role === 'admin' ? 'true' : 'false'; ?>">
                                Admin
                            </button>
                        </li>
                    </ul>

                    <form method="post" novalidate id="loginForm">
                        <input type="hidden" name="role" id="login_role" value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">

                        <div id="patient-login-fields" class="<?php echo $role !== 'patient' ? 'd-none' : ''; ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email"
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       id="email" name="email"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="email">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="staff-login-fields" class="<?php echo $role === 'patient' ? 'd-none' : ''; ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text"
                                       class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                       id="username" name="username"
                                       value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="username">
                                <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                       id="password" name="password" required
                                       autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button"
                                        data-password-toggle="password"
                                        data-label-show="Show" data-label-hide="Hide"
                                        aria-pressed="false" aria-controls="password">
                                    Show
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback d-block">
                                    <?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="patient-extra-links" class="mb-3 <?php echo $role !== 'patient' ? 'd-none' : ''; ?>">
                            <a href="forgot_password.php" class="auth-forgot-link">Forgot Password?</a>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div id="patient-register-prompt" class="text-center <?php echo $role !== 'patient' ? 'd-none' : ''; ?>">
                        <p class="text-muted mb-2">New patient?</p>
                        <a href="register.php" class="btn btn-outline-primary">Create Patient Account</a>
                    </div>
                    <div id="staff-register-note" class="text-center small text-muted <?php echo $role === 'patient' ? 'd-none' : ''; ?>">
                        Doctor and admin accounts are created by the hospital administrator.
                    </div>
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
