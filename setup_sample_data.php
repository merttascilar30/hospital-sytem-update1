<?php
// Development-only script to populate sample data for departments and doctors.

require_once __DIR__ . '/includes/db_connection.php';

// Helper to find or create a department by name
function getOrCreateDepartment(mysqli $mysqli, string $name, string $description = ''): ?int
{
    $selectSql = "SELECT department_id FROM departments WHERE name = ? LIMIT 1";
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

    $insertSql = "INSERT INTO departments (name, description) VALUES (?, ?)";
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

// Helper to create a doctor if email does not already exist
function createDoctorIfNotExists(
    mysqli $mysqli,
    int $departmentId,
    string $firstName,
    string $lastName,
    string $email,
    string $phone = ''
): void {
    $selectSql = "SELECT doctor_id FROM doctors WHERE email = ? LIMIT 1";
    $stmt = $mysqli->prepare($selectSql);
    if (!$stmt) {
        return;
    }

    $email_param = $email;
    $stmt->bind_param('s', $email_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Doctor already exists, do nothing.
        return;
    }

    $insertSql = "INSERT INTO doctors (department_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        return;
    }

    $department_id_param = $departmentId;
    $first_name_param = $firstName;
    $last_name_param = $lastName;
    $phone_param = $phone;

    $stmt->bind_param(
        'issss',
        $department_id_param,
        $first_name_param,
        $last_name_param,
        $email_param,
        $phone_param
    );
    $stmt->execute();
    $stmt->close();
}

// Define sample departments
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

// Define sample doctors mapped to department names
$sampleDoctors = [
    [
        'department' => 'Cardiology',
        'first_name' => 'Alice',
        'last_name'  => 'Heart',
        'email'      => 'alice.heart@example.com',
        'phone'      => '+90 555 000 1111',
    ],
    [
        'department' => 'Cardiology',
        'first_name' => 'Kemal',
        'last_name'  => 'Yılmaz',
        'email'      => 'kemal.yilmaz@example.com',
        'phone'      => '+90 555 000 1112',
    ],
    [
        'department' => 'Neurology',
        'first_name' => 'Nina',
        'last_name'  => 'Brain',
        'email'      => 'nina.brain@example.com',
        'phone'      => '+90 555 000 2222',
    ],
    [
        'department' => 'Orthopedics',
        'first_name' => 'Okan',
        'last_name'  => 'Demir',
        'email'      => 'okan.demir@example.com',
        'phone'      => '+90 555 000 3333',
    ],
    [
        'department' => 'Pediatrics',
        'first_name' => 'Elif',
        'last_name'  => 'Çocuk',
        'email'      => 'elif.cocuk@example.com',
        'phone'      => '+90 555 000 4444',
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
        $doc['phone']
    );
}

echo 'Test data populated!';

