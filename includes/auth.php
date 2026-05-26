<?php
/**
 * Session and role helpers for Hospital Appointment System.
 */

declare(strict_types=1);

function hs_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function hs_is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function hs_current_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}

function hs_redirect_if_logged_in(): void
{
    hs_start_session();
    if (!hs_is_logged_in()) {
        return;
    }

    switch (hs_current_role()) {
        case 'admin':
            header('Location: admin/index.php');
            break;
        case 'doctor':
            header('Location: doctor/index.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}

function hs_require_login(): void
{
    hs_start_session();
    if (!hs_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * @param string ...$roles Allowed roles: patient, doctor, admin
 */
function hs_require_role(string ...$roles): void
{
    hs_require_login();
    if (!in_array(hs_current_role(), $roles, true)) {
        header('Location: login.php');
        exit;
    }
}

function hs_require_patient(): void
{
    hs_require_role('patient');
}

function hs_require_doctor(): void
{
    hs_require_role('doctor');
}

function hs_require_admin(): void
{
    hs_require_role('admin');
}

/** Set MySQL user variable consumed by doctor audit triggers. */
function hs_set_admin_context(mysqli $mysqli, int $adminId): void
{
    $mysqli->query('SET @current_admin_id = ' . (int)$adminId);
}

function hs_clear_admin_context(mysqli $mysqli): void
{
    $mysqli->query('SET @current_admin_id = NULL');
}

function hs_login_user(int $userId, string $role, array $sessionData = []): void
{
    hs_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    foreach ($sessionData as $key => $value) {
        $_SESSION[$key] = $value;
    }
}
