<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connection.php';

hs_require_admin();

$adminName = $_SESSION['user_full_name'] ?? $_SESSION['user_username'] ?? 'Admin';
$totalDoctors = 0;
$polyclinicStats = [];
$doctors = [];
$recentLogs = [];

$spStmt = $mysqli->prepare('CALL sp_admin_dashboard_stats()');
if ($spStmt) {
    if ($spStmt->execute()) {
        $result = $spStmt->get_result();
        if ($result) {
            $summary = $result->fetch_assoc();
            if ($summary && isset($summary['total_doctors'])) {
                $totalDoctors = (int)$summary['total_doctors'];
            }
            $result->free();
        }
        $mysqli->next_result();
        $result2 = $spStmt->get_result();
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                $polyclinicStats[] = $row;
            }
            $result2->free();
        }
    }
    $spStmt->close();
    while ($mysqli->more_results() && $mysqli->next_result()) {
        $extra = $mysqli->use_result();
        if ($extra instanceof mysqli_result) {
            $extra->free();
        }
    }
}

$listSql = 'SELECT
    d.doctor_id,
    d.first_name,
    d.last_name,
    d.email,
    d.phone,
    d.username,
    d.gender,
    d.profile_picture,
    d.is_active,
    dep.name AS polyclinic_name
FROM doctors d
INNER JOIN departments dep ON dep.department_id = d.department_id
ORDER BY dep.name, d.last_name, d.first_name';

$listResult = $mysqli->query($listSql);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $doctors[] = $row;
    }
    $listResult->free();
}

$logResult = $mysqli->query(
    'SELECT log_id, action_type, details, log_created_at
     FROM system_logs
     ORDER BY log_created_at DESC
     LIMIT 10'
);
if ($logResult) {
    while ($row = $logResult->fetch_assoc()) {
        $recentLogs[] = $row;
    }
    $logResult->free();
}

$statusMessage = null;
$statusAlertClass = 'success';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'doctor_added') {
        $statusMessage = 'Doctor account created successfully.';
    } elseif ($_GET['status'] === 'doctor_deleted') {
        $statusMessage = 'Doctor removed from the system.';
    } elseif ($_GET['status'] === 'delete_failed') {
        $statusMessage = 'Could not delete doctor. They may still have linked appointments.';
        $statusAlertClass = 'warning';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Hospital Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
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
                    <a class="nav-link active" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_doctor.php">Add Doctor</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4 py-md-5">
    <?php if ($statusMessage): ?>
        <div class="alert alert-<?php echo htmlspecialchars($statusAlertClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Welcome, <?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-muted mb-0">System overview (via stored procedure <code>sp_admin_dashboard_stats</code>)</p>
        </div>
        <a href="add_doctor.php" class="btn btn-primary">Add New Doctor</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 admin-stat-card">
                <div class="card-body">
                    <div class="text-muted small">Total Registered Doctors</div>
                    <div class="display-6 fw-bold text-primary"><?php echo (int)$totalDoctors; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Appointments per Polyclinic</h2>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($polyclinicStats)): ?>
                        <p class="text-muted p-3 mb-0">No polyclinic data available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Polyclinic</th>
                                    <th scope="col">Doctors</th>
                                    <th scope="col">Appointments</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($polyclinicStats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['polyclinic_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)$stat['doctor_count']; ?></td>
                                        <td><?php echo (int)$stat['appointment_count']; ?></td>
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

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Doctors (INNER JOIN with Polyclinic)</h2>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($doctors)): ?>
                        <p class="text-muted p-3 mb-0">No doctors registered yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Photo</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Polyclinic</th>
                                    <th scope="col">Email</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($doctors as $doc): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($doc['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($doc['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="" class="admin-doctor-thumb rounded">
                                            <?php else: ?>
                                                <span class="admin-doctor-thumb-placeholder rounded">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['polyclinic_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <form method="post" action="delete_doctor.php" class="d-inline"
                                                  data-confirm="Delete this doctor? Existing appointments may block deletion.">
                                                <input type="hidden" name="doctor_id" value="<?php echo (int)$doc['doctor_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Recent System Logs (Trigger)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLogs)): ?>
                        <p class="text-muted small mb-0">No doctor management actions logged yet.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush admin-log-list">
                            <?php foreach ($recentLogs as $log): ?>
                                <li class="list-group-item px-0">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($log['action_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="small mt-1"><?php echo htmlspecialchars($log['details'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="text-muted smaller"><?php echo htmlspecialchars($log['log_created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="../js/script.js"></script>
</body>
</html>
