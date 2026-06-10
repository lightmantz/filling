<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all_logs') {
    // Log the clearing action before deleting
    auditLog($_SESSION['user_id'], 'cleared_all_logs', 'All activity logs were cleared by super admin');
    
    // Delete all logs
    $delete_query = "DELETE FROM audit_logs";
    if ($conn->query($delete_query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit();
?>