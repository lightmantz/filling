<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Activity Log';
$base_url = '../../';

$conn = getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT a.*, u.username, u.full_name, u.role
          FROM audit_logs a
          LEFT JOIN users u ON a.user_id = u.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM audit_logs a WHERE 1=1";
$params = [];
$types = "";

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $count_query .= " AND user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if (!empty($action_filter)) {
    $query .= " AND a.action LIKE ?";
    $count_query .= " AND action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.created_at) >= ?";
    $count_query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.created_at) <= ?";
    $count_query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get total count for pagination
$count_stmt = $conn->prepare($count_query);
if (!empty($params) && count($params) > 2) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Get users for filter
$users_query = "SELECT id, username, full_name FROM users ORDER BY full_name";
$users = $conn->query($users_query);

// Get action types for filter
$actions_query = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actions = $conn->query($actions_query);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_logs,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT DATE(created_at)) as active_days,
    MIN(created_at) as first_log,
    MAX(created_at) as last_log
    FROM audit_logs";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get action breakdown
$action_stats_query = "SELECT action, COUNT(*) as count 
                       FROM audit_logs 
                       GROUP BY action 
                       ORDER BY count DESC 
                       LIMIT 10";
$action_stats = $conn->query($action_stats_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h4 {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
    }
    
    .stat-label {
        font-size: 11px;
        opacity: 0.8;
        margin-top: 5px;
    }
    
    /* Filters */
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        color: #666;
        font-weight: 500;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    
    /* Logs Table */
    .logs-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        overflow-x: auto;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .logs-table th,
    .logs-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .logs-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
        position: sticky;
        top: 0;
    }
    
    .logs-table tr:hover {
        background: #f9f9f9;
    }
    
    .action-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .action-user_login { background: #3498db; color: white; }
    .action-user_logout { background: #95a5a6; color: white; }
    .action-add_user { background: #27ae60; color: white; }
    .action-edit_user { background: #ff9800; color: white; }
    .action-delete_user { background: #e74c3c; color: white; }
    .action-create_folder { background: #9b59b6; color: white; }
    .action-edit_folder { background: #f39c12; color: white; }
    .action-delete_folder { background: #c0392b; color: white; }
    .action-submit_document { background: #1abc9c; color: white; }
    .action-approve_document { background: #27ae60; color: white; }
    .action-reject_document { background: #e74c3c; color: white; }
    .action-assign_task { background: #e67e22; color: white; }
    .action-settings_updated { background: #34495e; color: white; }
    
    .ip-address {
        font-family: monospace;
        font-size: 12px;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 4px;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 14px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        color: #667eea;
        transition: all 0.3s;
    }
    
    .pagination a:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: #ddd;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        justify-content: flex-end;
    }
    
    .action-stats {
        background: white;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .action-stats h4 {
        margin: 0 0 15px 0;
        color: #333;
    }
    
    .action-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .action-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: #f8f9fa;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .action-tag .count {
        background: #667eea;
        color: white;
        padding: 2px 6px;
        border-radius: 20px;
        font-size: 10px;
    }
    
    @media (max-width: 768px) {
        .logs-table {
            display: block;
            overflow-x: auto;
        }
        
        .filter-form {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-history"></i> System Activity Log</h2>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Activities</h4>
            <div class="stat-number"><?php echo number_format($stats['total_logs'] ?? 0); ?></div>
            <div class="stat-label">All time records</div>
        </div>
        <div class="stat-card">
            <h4>Active Users</h4>
            <div class="stat-number"><?php echo $stats['unique_users'] ?? 0; ?></div>
            <div class="stat-label">Users with activity</div>
        </div>
        <div class="stat-card">
            <h4>Active Days</h4>
            <div class="stat-number"><?php echo $stats['active_days'] ?? 0; ?></div>
            <div class="stat-label">Days with activity</div>
        </div>
        <div class="stat-card">
            <h4>First Activity</h4>
            <div class="stat-number" style="font-size: 14px;"><?php echo $stats['first_log'] ? date('M d, Y', strtotime($stats['first_log'])) : 'N/A'; ?></div>
            <div class="stat-label">System started</div>
        </div>
    </div>
    
    <!-- Action Breakdown -->
    <div class="action-stats">
        <h4><i class="fas fa-chart-pie"></i> Top Actions</h4>
        <div class="action-tags">
            <?php while ($action = $action_stats->fetch_assoc()): ?>
                <span class="action-tag">
                    <i class="fas fa-tag"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                    <span class="count"><?php echo $action['count']; ?></span>
                </span>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>User</label>
                <select name="user_id">
                    <option value="0">All Users</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($user_filter == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Action</label>
                <select name="action">
                    <option value="">All Actions</option>
                    <?php while ($action = $actions->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo ($action_filter == $action['action']) ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="activity_log.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Export Buttons -->
    <div class="export-buttons">
        <button onclick="exportLogs('csv')" class="btn btn-primary">
            <i class="fas fa-download"></i> Export CSV
        </button>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="clearLogs()" class="btn btn-danger" onclick="return confirm('Clear all activity logs? This action cannot be undone.')">
            <i class="fas fa-trash"></i> Clear Logs
        </button>
    </div>
    
    <!-- Logs Table -->
    <div class="logs-container">
        <?php if ($logs && $logs->num_rows > 0): ?>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></strong><br>
                                <small style="color: #666;">@<?php echo htmlspecialchars($log['username'] ?? 'system'); ?></small>
                                <?php if ($log['role']): ?>
                                    <br><span class="role-badge role-<?php echo $log['role']; ?>" style="font-size: 9px; padding: 2px 6px;"><?php echo ucfirst(str_replace('_', ' ', $log['role'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="action-badge action-<?php echo str_replace('_', '-', $log['action']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td style="max-width: 300px;">
                                <?php echo htmlspecialchars($log['details'] ?? 'No details'); ?>
                            </td>
                            <td>
                                <span class="ip-address"><?php echo $log['ip_address']; ?></span>
                            </td>
                            <td>
                                <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Activity Logs Found</h3>
                <p>There are no activity logs matching your criteria.</p>
                <a href="activity_log.php" class="btn btn-primary">View All Logs</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportLogs(format) {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export_logs.php?' + params.toString() + '&format=' + format;
}

function clearLogs() {
    if (confirm('Are you sure you want to clear all activity logs? This action cannot be undone and will delete all historical activity data.')) {
        fetch('clear_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_all_logs'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('All logs cleared successfully!');
                location.reload();
            } else {
                alert('Failed to clear logs: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error clearing logs: ' + error);
        });
    }
}

// Auto-refresh logs every 60 seconds (optional)
let autoRefresh = false;
setInterval(function() {
    if (autoRefresh && document.hasFocus()) {
        location.reload();
    }
}, 60000);
</script>

<?php
include_once '../../includes/footer.php';
?>