<?php
require_once __DIR__ . '/../includes/auth.php';

hs_require_doctor();

$firstName = $_SESSION['user_first_name'] ?? '';
$lastName = $_SESSION['user_last_name'] ?? '';
$username = $_SESSION['user_username'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doctor Dashboard - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="index.php">Doctor Portal</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link text-white" href="../logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h4">Welcome, Dr. <?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-muted mb-0">
                Logged in as <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>.
                Appointment management for doctors can be extended in a future module.
            </p>
        </div>
    </div>
</div>
</body>
</html>
