<?php
require_once '../../config/session.php';
requireLogin();
requireRole('admin');

$conn = getConnection();
$action = $_POST['action'] ?? '';

if ($action === 'clear_audit') {
    $delete_query = "DELETE FROM audit_logs";
    if ($conn->query($delete_query)) {
        // Log the clearing action
        $log_query = "INSERT INTO audit_logs (user_id, action, details, ip_address) 
                      VALUES (?, 'cleared_audit_logs', 'Audit logs were cleared', ?)";
        $stmt = $conn->prepare($log_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("is", $_SESSION['user_id'], $ip);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}
?>