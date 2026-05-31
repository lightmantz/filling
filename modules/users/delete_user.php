<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$conn = getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
    // Get username for logging
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        // Delete user
        $delete = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            auditLog($_SESSION['user_id'], 'delete_user', "Deleted user: {$user['username']}");
            $_SESSION['flash_message'] = "User has been deleted successfully.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to delete user.";
            $_SESSION['flash_type'] = 'error';
        }
    }
}

header('Location: add_user.php');
exit();
?>