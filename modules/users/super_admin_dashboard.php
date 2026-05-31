<?php
// modules/users/super_admin_dashboard.php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Super Admin Dashboard';
$base_url = '../../';

$conn = getConnection();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'records_officer' THEN 1 ELSE 0 END) as records_officers,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
    FROM users";
$stats = $conn->query($stats_query)->fetch_assoc();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <h2>Super Admin Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Active Users</h3>
            <div class="stat-number"><?php echo $stats['active_users']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Records Officers</h3>
            <div class="stat-number"><?php echo $stats['records_officers']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Administrators</h3>
            <div class="stat-number"><?php echo $stats['admins']; ?></div>
        </div>
    </div>
    
    <div class="card">
        <h3>Quick Actions</h3>
        <div class="quick-actions">
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
            <a href="activity_log.php" class="btn btn-secondary">
                <i class="fas fa-history"></i> View Activity Log
            </a>
            <a href="../settings/index.php" class="btn btn-secondary">
                <i class="fas fa-cogs"></i> System Settings
            </a>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        margin-top: 10px;
    }
    
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        color: white;
    }
    
    .btn-primary {
        background: #3498db;
    }
    
    .btn-secondary {
        background: #95a5a6;
    }
</style>

<?php
include_once '../../includes/footer.php';
?>