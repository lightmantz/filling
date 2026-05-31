<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$conn = getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
    // Get current status
    $query = "SELECT is_active, username FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $new_status = $user['is_active'] ? 0 : 1;
        $update = "UPDATE users SET is_active = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update);
        $update_stmt->bind_param("ii", $new_status, $user_id);
        
        if ($update_stmt->execute()) {
            $action = $new_status ? 'activated' : 'deactivated';
            auditLog($_SESSION['user_id'], 'toggle_user', "{$action} user: {$user['username']}");
            $_SESSION['flash_message'] = "User has been {$action}.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to update user status.";
            $_SESSION['flash_type'] = 'error';
        }
    }
}

header('Location: add_user.php');
exit();
?>