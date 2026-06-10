<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in for header display
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Ensure base_url is defined and clean
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '';
}
$base_url = rtrim($base_url, '/');

// Helper function to clean URLs (remove multiple slashes)
if (!function_exists('cleanUrl')) {
    function cleanUrl($url) {
        return preg_replace('#/+#', '/', $url);
    }
}

// Helper function to build URL
if (!function_exists('buildUrl')) {
    function buildUrl($path) {
        global $base_url;
        $path = ltrim($path, '/');
        return cleanUrl($base_url . '/' . $path);
    }
}

// Get unread notification count
$unread_count = 0;
if ($is_logged_in && function_exists('getConnection')) {
    try {
        $conn = getConnection();
        if ($conn) {
            $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $row = $result->fetch_assoc();
                    $unread_count = $row['count'] ?? 0;
                }
            }
        }
    } catch (Exception $e) {
        $unread_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Filing Management System'; ?></title>
    <link rel="stylesheet" href="<?php echo cleanUrl($base_url . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.json">
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/assets/images/favicon.ico">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            overflow-x: hidden;
        }
        
        /* Layout Structure */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #ff9800;
        }
        
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #ff9800;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 18px;
        }
        
        .sidebar-menu .submenu {
            list-style: none;
            padding-left: 45px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .sidebar-menu .submenu.show {
            max-height: 500px;
        }
        
        .sidebar-menu .submenu a {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .sidebar-menu .has-submenu {
            cursor: pointer;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }
        
        /* Top Header */
        .top-header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .menu-toggle {
            display: none;
            cursor: pointer;
            font-size: 20px;
        }
        
        /* Notification Area */
        .notification-area {
            position: relative;
            margin-right: 15px;
        }
        
        .notification-icon {
            font-size: 20px;
            color: #666;
            cursor: pointer;
            transition: color 0.3s;
            position: relative;
        }
        
        .notification-icon:hover {
            color: #667eea;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-dropdown.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .notification-header {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .notification-item:hover {
            background: #f9f9f9;
        }
        
        .notification-item.unread {
            background: #f0f7ff;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 3px;
            color: #333;
        }
        
        .notification-message {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 10px;
            color: #999;
        }
        
        .mark-all-read {
            padding: 8px 15px;
            text-align: center;
            background: #f8f9fa;
            font-size: 12px;
            cursor: pointer;
            color: #667eea;
            border-top: 1px solid #eee;
        }
        
        .mark-all-read:hover {
            background: #e9ecef;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .user-details {
            line-height: 1.3;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        .user-role {
            font-size: 12px;
            color: #666;
        }
        
        .logout-btn {
            padding: 8px 15px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Content Container */
        .content-wrapper {
            padding: 30px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .content-wrapper {
                padding: 20px;
            }
            
            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
            
            .user-details {
                display: none;
            }
        }
        
        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 5px;
        }
        
        .notification-dropdown::-webkit-scrollbar {
            width: 5px;
        }
        
        .notification-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .notification-dropdown::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 5px;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        /* Footer */
        .footer {
            background: white;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            margin-top: 30px;
        }
        
        /* Empty State */
        .empty-notifications {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .empty-notifications i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #ddd;
        }
        
        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .role-super_admin { background: #e74c3c; color: white; }
        .role-records_officer { background: #3498db; color: white; }
        .role-admin { background: #f39c12; color: white; }
        .role-user { background: #27ae60; color: white; }
    </style>
</head>
<body>
<div class="app-container">

<script>
// Pass PHP variables to JavaScript for notification system
window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
window.baseUrl = '<?php echo $base_url; ?>';
window.userId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;
window.userRole = '<?php echo $_SESSION['user_role'] ?? ''; ?>';
window.unreadCount = <?php echo $unread_count; ?>;
</script>