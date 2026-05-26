<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_admin();

$errors = [];
$success = false;
$name = '';
$description = '';

$namePattern = "/^[A-Za-zÇçĞğİıÖöŞşÜü0-9\s&.'-]{2,100}$/u";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || !preg_match($namePattern, $name)) {
        $errors['name'] = 'Please enter a valid polyclinic name (2–100 characters).';
    }
    if ($description !== '' && !preg_match('/\A.{0,255}\z/us', $description)) {
        $errors['description'] = 'Description must be at most 255 characters.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare(
            'INSERT INTO departments (name, description) VALUES (?, ?)'
        );
        if ($stmt) {
            $descParam = $description !== '' ? $description : null;
            $stmt->bind_param('ss', $name, $descParam);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: departments.php?status=added');
                exit;
            }
            if ($mysqli->errno === 1062) {
                $errors['name'] = 'A polyclinic with this name already exists.';
            } else {
                $errors['general'] = 'Could not add polyclinic.';
            }
            $stmt->close();
        } else {
            $errors['general'] = 'Could not prepare insert statement.';
        }
    }
}

$departments = [];
$result = $mysqli->query(
    'SELECT department_id, name, description
     FROM departments
     ORDER BY name'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    $result->free();
}

$statusMessage = null;
if (isset($_GET['status']) && $_GET['status'] === 'added') {
    $statusMessage = 'Polyclinic added successfully.';
}

$adminNavActive = 'departments';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Polyclinics - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
<?php require __DIR__ . '/includes_nav.php'; ?>

<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Manage Polyclinics</h1>

    <?php if ($statusMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Add Polyclinic</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Polyclinic Name</label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                   id="name" name="name"
                                   value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>"
                                      id="description" name="description" rows="3"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Polyclinic</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Existing Polyclinics</h2>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($departments)): ?>
                        <p class="text-muted p-3 mb-0">No polyclinics yet. Add one using the form.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Description</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($dept['description'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
