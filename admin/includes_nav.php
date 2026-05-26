<?php
/** @var string $adminNavActive One of: dashboard, add_doctor, departments */
$adminNavActive = $adminNavActive ?? 'dashboard';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Admin Panel</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav"
                aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $adminNavActive === 'dashboard' ? 'active' : ''; ?>" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $adminNavActive === 'add_doctor' ? 'active' : ''; ?>" href="add_doctor.php">Add Doctor</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $adminNavActive === 'departments' ? 'active' : ''; ?>" href="departments.php">Polyclinics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
