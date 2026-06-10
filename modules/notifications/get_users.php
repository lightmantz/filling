<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For testing, let's get users from database
$conn = getConnection();

// Query to get all active users
$query = "SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name ASC";
$result = $conn->query($query);

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $role_display = [
            'super_admin' => 'Super Admin',
            'records_officer' => 'Records Officer',
            'admin' => 'Administrator',
            'user' => 'User'
        ];
        
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'initial' => strtoupper(substr($row['full_name'], 0, 1)),
            'role' => $row['role'],
            'role_display' => $role_display[$row['role']] ?? $row['role']
        ];
    }
}

// If no users found in database, return sample users for testing
if (empty($users)) {
    $users = [
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
        ]
    ];
}

echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]);
?>