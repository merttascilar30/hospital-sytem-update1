<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_admin();

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : (int)($_POST['doctor_id'] ?? 0);
$errors = [];
$doctor = null;

if ($doctorId > 0) {
    $stmt = $mysqli->prepare(
        'SELECT doctor_id, first_name, last_name, username
         FROM doctors WHERE doctor_id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $doctorId);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$doctor) {
    header('Location: index.php');
    exit;
}

$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '' || !preg_match($passwordPattern, $new_password)) {
        $errors['new_password'] = 'Password must be at least 8 characters with one letter and one number.';
    }
    if ($confirm_password === '' || $confirm_password !== $new_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $mysqli->prepare(
            'UPDATE doctors SET password_hash = ? WHERE doctor_id = ?'
        );
        if ($update) {
            $update->bind_param('si', $password_hash, $doctorId);
            if ($update->execute()) {
                $update->close();
                header('Location: index.php?status=password_reset');
                exit;
            }
            $update->close();
        }
        $errors['general'] = 'Could not update password.';
    }
}

$adminNavActive = 'dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Doctor Password - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
<?php require __DIR__ . '/includes_nav.php'; ?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-0">Reset Password</h1>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Set a new login password for
                        <strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        (<?php echo htmlspecialchars($doctor['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>).
                    </p>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <input type="hidden" name="doctor_id" value="<?php echo (int)$doctorId; ?>">

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                   id="new_password" name="new_password" required autocomplete="new-password">
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['new_password'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                   id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                            <a href="index.php" class="btn btn-link">Cancel</a>
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
</body>
</html>
