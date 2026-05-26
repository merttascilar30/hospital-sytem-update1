<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/security_questions.php';

hs_redirect_if_logged_in();
hs_start_session();

$errors = [];
$success = false;
$email = '';
$security_answer = '';
$step = (int)($_SESSION['reset_step'] ?? 1);

$emailPattern = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'email';

    if ($action === 'email') {
        $email = trim($_POST['email'] ?? '');
        $step = 1;

        if ($email === '' || !preg_match($emailPattern, $email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $stmt = $mysqli->prepare(
                'SELECT patient_id, security_question, security_answer
                 FROM patients WHERE email = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || empty($row['security_question']) || empty($row['security_answer'])) {
                    $errors['email'] = 'No patient account was found with this email address.';
                } else {
                    $_SESSION['reset_patient_id'] = (int)$row['patient_id'];
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_security_question'] = $row['security_question'];
                    $_SESSION['reset_security_answer_hash'] = $row['security_answer'];
                    $_SESSION['reset_verified'] = false;
                    $_SESSION['reset_step'] = 2;
                    $step = 2;
                }
            } else {
                $errors['general'] = 'Could not verify email address.';
            }
        }
    } elseif ($action === 'answer') {
        $step = 2;
        $email = (string)($_SESSION['reset_email'] ?? '');
        $security_answer = trim($_POST['security_answer'] ?? '');

        if (empty($_SESSION['reset_patient_id']) || $email === '') {
            $_SESSION['reset_step'] = 1;
            header('Location: forgot_password.php');
            exit;
        }

        if ($security_answer === '') {
            $errors['security_answer'] = 'Please enter your security answer.';
        } elseif (!hs_verify_security_answer(
            $security_answer,
            (string)($_SESSION['reset_security_answer_hash'] ?? '')
        )) {
            $errors['security_answer'] = 'Security answer is incorrect.';
        } else {
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_step'] = 3;
            $step = 3;
        }
    } elseif ($action === 'password') {
        $step = 3;
        $email = (string)($_SESSION['reset_email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($_SESSION['reset_patient_id']) || empty($_SESSION['reset_verified'])) {
            $_SESSION['reset_step'] = 1;
            header('Location: forgot_password.php');
            exit;
        }

        if ($new_password === '' || !preg_match($passwordPattern, $new_password)) {
            $errors['new_password'] = 'New password must be at least 8 characters with at least one letter and one number.';
        }
        if ($confirm_password === '' || $confirm_password !== $new_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $patient_id = (int)$_SESSION['reset_patient_id'];

            $update = $mysqli->prepare(
                'UPDATE patients SET password_hash = ? WHERE patient_id = ? AND email = ?'
            );
            if ($update) {
                $update->bind_param('sis', $password_hash, $patient_id, $email);
                if ($update->execute()) {
                    $update->close();
                    unset(
                        $_SESSION['reset_step'],
                        $_SESSION['reset_patient_id'],
                        $_SESSION['reset_email'],
                        $_SESSION['reset_security_question'],
                        $_SESSION['reset_security_answer_hash'],
                        $_SESSION['reset_verified']
                    );
                    $success = true;
                    $step = 1;
                } else {
                    $errors['general'] = 'Could not update password. Please try again.';
                    $update->close();
                }
            } else {
                $errors['general'] = 'Could not prepare password update.';
            }
        }
    }
} else {
    if (isset($_GET['restart'])) {
        unset(
            $_SESSION['reset_step'],
            $_SESSION['reset_patient_id'],
            $_SESSION['reset_email'],
            $_SESSION['reset_security_question'],
            $_SESSION['reset_security_answer_hash'],
            $_SESSION['reset_verified']
        );
        $step = 1;
    } else {
        $step = (int)($_SESSION['reset_step'] ?? 1);
        $email = (string)($_SESSION['reset_email'] ?? '');
    }
}

$securityQuestionLabel = '';
if ($step >= 2 && !empty($_SESSION['reset_security_question'])) {
    $securityQuestionLabel = hs_security_question_label((string)$_SESSION['reset_security_question']);
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

                        <div class="mb-4">
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span class="<?php echo $step === 1 ? 'fw-bold text-primary' : ''; ?>">1. Email</span>
                                <span class="<?php echo $step === 2 ? 'fw-bold text-primary' : ''; ?>">2. Security</span>
                                <span class="<?php echo $step === 3 ? 'fw-bold text-primary' : ''; ?>">3. Password</span>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?php echo $step; ?>" aria-valuemin="1" aria-valuemax="3">
                                <div class="progress-bar" style="width: <?php echo (int)(($step / 3) * 100); ?>%"></div>
                            </div>
                        </div>

                        <?php if ($step === 1): ?>
                            <form method="post" novalidate>
                                <input type="hidden" name="action" value="email">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Registered Email</label>
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
                                <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                    <button type="submit" class="btn btn-primary">Continue</button>
                                    <a href="login.php" class="btn btn-link">Back to Login</a>
                                </div>
                            </form>

                        <?php elseif ($step === 2): ?>
                            <p class="text-muted small mb-3">
                                Answer the security question you chose when registering.
                            </p>
                            <form method="post" novalidate>
                                <input type="hidden" name="action" value="answer">
                                <div class="mb-3">
                                    <label class="form-label">Security Question</label>
                                    <p class="form-control-plaintext fw-semibold border rounded px-3 py-2 bg-light mb-0">
                                        <?php echo htmlspecialchars($securityQuestionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label for="security_answer" class="form-label">Your Answer</label>
                                    <input type="text"
                                           class="form-control <?php echo isset($errors['security_answer']) ? 'is-invalid' : ''; ?>"
                                           id="security_answer" name="security_answer"
                                           value="<?php echo htmlspecialchars($security_answer, ENT_QUOTES, 'UTF-8'); ?>"
                                           autocomplete="off" required>
                                    <?php if (isset($errors['security_answer'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['security_answer'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                    <button type="submit" class="btn btn-primary">Verify Answer</button>
                                    <a href="forgot_password.php?restart=1" class="btn btn-link">Start Over</a>
                                </div>
                            </form>

                        <?php else: ?>
                            <form method="post" novalidate>
                                <input type="hidden" name="action" value="password">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password"
                                           class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                           id="new_password" name="new_password" required autocomplete="new-password">
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
                                           id="confirm_password" name="confirm_password" required autocomplete="new-password">
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                    <a href="forgot_password.php?restart=1" class="btn btn-link">Start Over</a>
                                </div>
                            </form>
                        <?php endif; ?>
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
