<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// For testing, let's just return some sample users
$test_users = [
    [
        'id' => 1,
        'username' => 'superadmin',
        'full_name' => 'Super Administrator',
        'initial' => 'S',
        'role' => 'super_admin',
        'role_display' => 'Super Admin'
    ],
    [
        'id' => 2,
        'username' => 'records_officer',
        'full_name' => 'Records Management Officer',
        'initial' => 'R',
        'role' => 'records_officer',
        'role_display' => 'Records Officer'
    ],
    [
        'id' => 3,
        'username' => 'admin_user',
        'full_name' => 'Top Administrator',
        'initial' => 'T',
        'role' => 'admin',
        'role_display' => 'Administrator'
    ],
    [
        'id' => 4,
        'username' => 'normal_user',
        'full_name' => 'Normal User',
        'initial' => 'N',
        'role' => 'user',
        'role_display' => 'User'
    ]
];

echo json_encode([
    'success' => true,
    'users' => $test_users
]);
?>