<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_admin();

$errors = [];
$departments = [];

$deptResult = $mysqli->query('SELECT department_id, name FROM departments ORDER BY name');
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
    $deptResult->free();
}

$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$department_id = '';
$gender = '';
$username = '';
$is_active = true;
$profile_notes = '';

$namePattern = "/^[A-Za-zÇçĞğİıÖöŞşÜü\s'-]{2,}$/u";
$emailPattern = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
$phonePattern = '/^[0-9+\-\s]{7,20}$/';
$usernamePattern = '/^[A-Za-z0-9._-]{3,50}$/';
$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = $_POST['department_id'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_active = isset($_POST['is_active']);
    $profile_notes = trim($_POST['profile_notes'] ?? '');

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
    if ($department_id === '' || !preg_match('/^\d{1,10}$/', (string)$department_id)) {
        $errors['department_id'] = 'Please select a polyclinic.';
    }
    if (!in_array($gender, ['M', 'F', 'Other'], true)) {
        $errors['gender'] = 'Please select a gender.';
    }
    if ($username === '' || !preg_match($usernamePattern, $username)) {
        $errors['username'] = 'Username must be 3–50 characters (letters, numbers, . _ -).';
    }
    if ($password === '' || !preg_match($passwordPattern, $password)) {
        $errors['password'] = 'Password must be at least 8 characters with one letter and one number.';
    }
    if ($confirm_password === '' || $confirm_password !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if ($profile_notes !== '' && !preg_match('/\A.{0,500}\z/us', $profile_notes)) {
        $errors['profile_notes'] = 'Notes must be at most 500 characters.';
    }

    $profile_picture_path = null;
    if (empty($errors) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['profile_picture'] = 'Could not upload profile picture.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['profile_picture'] = 'Profile picture must be at most 2 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $errors['profile_picture'] = 'Allowed formats: JPG, PNG, WEBP.';
            } else {
                $uploadDir = dirname(__DIR__) . '/uploads/doctors';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = 'doctor_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $profile_picture_path = 'uploads/doctors/' . $filename;
                } else {
                    $errors['profile_picture'] = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $deptIdInt = (int)$department_id;
        $isActiveInt = $is_active ? 1 : 0;
        $adminId = (int)$_SESSION['user_id'];

        hs_set_admin_context($mysqli, $adminId);

        $stmt = $mysqli->prepare(
            'INSERT INTO doctors (
                department_id, first_name, last_name, email, phone,
                username, password_hash, gender, profile_picture, is_active
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $stmt->bind_param(
                'issssssssi',
                $deptIdInt,
                $first_name,
                $last_name,
                $email,
                $phone,
                $username,
                $password_hash,
                $gender,
                $profile_picture_path,
                $isActiveInt
            );

            if ($stmt->execute()) {
                $stmt->close();
                hs_clear_admin_context($mysqli);
                header('Location: index.php?status=doctor_added');
                exit;
            }

            if ($mysqli->errno === 1062) {
                $errors['general'] = 'Email or username already exists.';
            } else {
                $errors['general'] = 'Could not create doctor account.';
            }
            $stmt->close();
        } else {
            $errors['general'] = 'Could not prepare insert statement.';
        }

        hs_clear_admin_context($mysqli);
    }
}

$adminNavActive = 'add_doctor';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Doctor - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
<?php require __DIR__ . '/includes_nav.php'; ?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-0">Add Doctor Account</h1>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                       id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                       id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       id="email" name="email"
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                       id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="department_id" class="form-label">Polyclinic</label>
                                <select class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>"
                                        id="department_id" name="department_id" required>
                                    <option value="">Choose polyclinic...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo (int)$dept['department_id']; ?>"
                                            <?php echo (string)$department_id === (string)$dept['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['department_id'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['department_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label d-block">Gender</label>
                                <?php foreach (['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'] as $val => $label): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gender"
                                               id="gender_<?php echo strtolower($val); ?>"
                                               value="<?php echo $val; ?>"
                                            <?php echo $gender === $val ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="gender_<?php echo strtolower($val); ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (isset($errors['gender'])): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errors['gender'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="username" class="form-label">Login Username</label>
                                <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                       id="username" name="username"
                                       value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="profile_picture" class="form-label">Profile Picture</label>
                                <input type="file" class="form-control <?php echo isset($errors['profile_picture']) ? 'is-invalid' : ''; ?>"
                                       id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                                <?php if (isset($errors['profile_picture'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['profile_picture'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                       id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                        <?php echo $is_active ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Account is active
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="profile_notes" class="form-label">Internal Notes</label>
                                <textarea class="form-control <?php echo isset($errors['profile_notes']) ? 'is-invalid' : ''; ?>"
                                          id="profile_notes" name="profile_notes" rows="3"><?php echo htmlspecialchars($profile_notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php if (isset($errors['profile_notes'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['profile_notes'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Create Doctor</button>
                                <a href="index.php" class="btn btn-link">Cancel</a>
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
</body>
</html>
