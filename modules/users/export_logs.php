<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$conn = getConnection();

// Get filters from URL
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT a.*, u.username, u.full_name, u.role
          FROM audit_logs a
          LEFT JOIN users u ON a.user_id = u.id
          WHERE 1=1";
$params = [];
$types = "";

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if (!empty($action_filter)) {
    $query .= " AND a.action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Log ID',
    'User ID',
    'Username',
    'Full Name',
    'Role',
    'Action',
    'Details',
    'IP Address',
    'Date/Time'
]);

// Add data
while ($log = $logs->fetch_assoc()) {
    fputcsv($output, [
        $log['id'],
        $log['user_id'] ?? 'System',
        $log['username'] ?? 'system',
        $log['full_name'] ?? 'System',
        $log['role'] ?? 'system',
        $log['action'],
        $log['details'],
        $log['ip_address'],
        date('Y-m-d H:i:s', strtotime($log['created_at']))
    ]);
}

fclose($output);
exit();
?>