<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: modules/auth/login.php');
    exit();
}

// Redirect to dashboard based on role
$role = $_SESSION['user_role'];
switch ($role) {
    case 'records_officer':
        header('Location: modules/users/records_dashboard.php');
        break;
    case 'admin':
        header('Location: modules/users/admin_dashboard.php');
        break;
    case 'user':
        header('Location: modules/users/user_dashboard.php');
        break;
    default:
        header('Location: modules/auth/login.php');
}
exit();
?>