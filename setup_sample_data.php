<?php
// Development-only script to populate sample data for departments, doctors, and admin.

require_once __DIR__ . '/includes/db_connection.php';

function getOrCreateDepartment(mysqli $mysqli, string $name, string $description = ''): ?int
{
    $selectSql = 'SELECT department_id FROM departments WHERE name = ? LIMIT 1';
    $stmt = $mysqli->prepare($selectSql);
    if (!$stmt) {
        return null;
    }

    $name_param = $name;
    $stmt->bind_param('s', $name_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int)$row['department_id'];
    }

    $insertSql = 'INSERT INTO departments (name, description) VALUES (?, ?)';
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        return null;
    }

    $description_param = $description;
    $stmt->bind_param('ss', $name_param, $description_param);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return (int)$id;
    }

    $stmt->close();
    return null;
}

function seedAdminIfNotExists(mysqli $mysqli): void
{
    $username = 'admin';
    $check = $mysqli->prepare('SELECT admin_id FROM admins WHERE username = ? LIMIT 1');
    if (!$check) {
        return;
    }
    $check->bind_param('s', $username);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        return;
    }

    $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
    $fullName = 'System Administrator';
    $insert = $mysqli->prepare(
        'INSERT INTO admins (username, password_hash, full_name) VALUES (?, ?, ?)'
    );
    if ($insert) {
        $insert->bind_param('sss', $username, $hash, $fullName);
        $insert->execute();
        $insert->close();
    }
}

function createDoctorIfNotExists(
    mysqli $mysqli,
    int $departmentId,
    string $firstName,
    string $lastName,
    string $email,
    string $phone,
    string $username,
    string $plainPassword
): void {
    $selectSql = 'SELECT doctor_id FROM doctors WHERE email = ? LIMIT 1';
    $stmt = $mysqli->prepare($selectSql);
    if (!$stmt) {
        return;
    }

    $email_param = $email;
    $stmt->bind_param('s', $email_param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return;
    }

    $password_hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $gender = 'M';

    $insertSql = 'INSERT INTO doctors (
        department_id, first_name, last_name, email, phone,
        username, password_hash, gender, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)';

    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'isssssss',
        $departmentId,
        $firstName,
        $lastName,
        $email_param,
        $phone,
        $username,
        $password_hash,
        $gender
    );
    $stmt->execute();
    $stmt->close();
}

seedAdminIfNotExists($mysqli);

$sampleDepartments = [
    ['Cardiology', 'Heart and cardiovascular system'],
    ['Neurology', 'Nervous system and brain'],
    ['Orthopedics', 'Bones, joints, and muscles'],
    ['Pediatrics', 'Healthcare for children and adolescents'],
];

$departmentIds = [];

foreach ($sampleDepartments as $dept) {
    [$name, $description] = $dept;
    $id = getOrCreateDepartment($mysqli, $name, $description);
    if ($id !== null) {
        $departmentIds[$name] = $id;
    }
}

$sampleDoctors = [
    [
        'department' => 'Cardiology',
        'first_name' => 'Alice',
        'last_name'  => 'Heart',
        'email'      => 'alice.heart@example.com',
        'phone'      => '+90 555 000 1111',
        'username'   => 'alice.heart',
        'password'   => 'Doctor123!',
    ],
    [
        'department' => 'Cardiology',
        'first_name' => 'Kemal',
        'last_name'  => 'Yilmaz',
        'email'      => 'kemal.yilmaz@example.com',
        'phone'      => '+90 555 000 1112',
        'username'   => 'kemal.yilmaz',
        'password'   => 'Doctor123!',
    ],
    [
        'department' => 'Neurology',
        'first_name' => 'Nina',
        'last_name'  => 'Brain',
        'email'      => 'nina.brain@example.com',
        'phone'      => '+90 555 000 2222',
        'username'   => 'nina.brain',
        'password'   => 'Doctor123!',
    ],
    [
        'department' => 'Orthopedics',
        'first_name' => 'Okan',
        'last_name'  => 'Demir',
        'email'      => 'okan.demir@example.com',
        'phone'      => '+90 555 000 3333',
        'username'   => 'okan.demir',
        'password'   => 'Doctor123!',
    ],
    [
        'department' => 'Pediatrics',
        'first_name' => 'Elif',
        'last_name'  => 'Cocuk',
        'email'      => 'elif.cocuk@example.com',
        'phone'      => '+90 555 000 4444',
        'username'   => 'elif.cocuk',
        'password'   => 'Doctor123!',
    ],
];

foreach ($sampleDoctors as $doc) {
    $deptName = $doc['department'];
    if (!isset($departmentIds[$deptName])) {
        continue;
    }

    createDoctorIfNotExists(
        $mysqli,
        $departmentIds[$deptName],
        $doc['first_name'],
        $doc['last_name'],
        $doc['email'],
        $doc['phone'],
        $doc['username'],
        $doc['password']
    );
}

echo 'Sample data populated. Admin: admin / Admin123! — Doctors: &lt;username&gt; / Doctor123!';
