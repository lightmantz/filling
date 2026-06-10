<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Task Overview';
$base_url = '../../';

$conn = getConnection();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low,
    SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue
    FROM tasks";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get tasks by user
$user_tasks_query = "SELECT 
    u.id, u.full_name, u.role,
    COUNT(t.id) as total,
    SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.role IN ('records_officer', 'admin', 'user')
    GROUP BY u.id
    ORDER BY total DESC";
$user_tasks = $conn->query($user_tasks_query);

// Get recent tasks
$recent_tasks_query = "SELECT t.*, 
                       u_assigned_by.full_name as assigned_by_name,
                       u_assigned_to.full_name as assigned_to_name,
                       d.document_number
                       FROM tasks t
                       LEFT JOIN users u_assigned_by ON t.assigned_by = u_assigned_by.id
                       LEFT JOIN users u_assigned_to ON t.assigned_to = u_assigned_to.id
                       LEFT JOIN documents d ON t.document_id = d.id
                       ORDER BY t.created_at DESC
                       LIMIT 20";
$recent_tasks = $conn->query($recent_tasks_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h3 {
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #333;
    }
    
    .stat-card.urgent .stat-number { color: #e74c3c; }
    .stat-card.overdue .stat-number { color: #e74c3c; }
    .stat-card.completed .stat-number { color: #27ae60; }
    
    .overview-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    
    .overview-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .overview-card h3 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .data-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    .progress-bar {
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        height: 6px;
        width: 100px;
    }
    
    .progress-fill {
        background: #27ae60;
        height: 100%;
        border-radius: 10px;
    }
    
    .task-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        transition: background 0.3s;
    }
    
    .task-item:hover {
        background: #f9f9f9;
    }
    
    .task-title {
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .task-meta {
        font-size: 12px;
        color: #666;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .priority-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .priority-low { background: #27ae60; color: white; }
    .priority-medium { background: #3498db; color: white; }
    .priority-high { background: #f39c12; color: white; }
    .priority-urgent { background: #e74c3c; color: white; }
    
    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .status-pending { background: #95a5a6; color: white; }
    .status-in_progress { background: #3498db; color: white; }
    .status-completed { background: #27ae60; color: white; }
    
    @media (max-width: 900px) {
        .overview-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-chart-line"></i> Task Overview</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Tasks</h3>
            <div class="stat-number"><?php echo $stats['total_tasks'] ?? 0; ?></div>
        </div>
        <div class="stat-card pending">
            <h3>Pending</h3>
            <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
        </div>
        <div class="stat-card in_progress">
            <h3>In Progress</h3>
            <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
        </div>
        <div class="stat-card completed">
            <h3>Completed</h3>
            <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
        </div>
        <div class="stat-card urgent">
            <h3>Urgent Tasks</h3>
            <div class="stat-number"><?php echo $stats['urgent'] ?? 0; ?></div>
        </div>
        <div class="stat-card overdue">
            <h3>Overdue</h3>
            <div class="stat-number"><?php echo $stats['overdue'] ?? 0; ?></div>
        </div>
    </div>
    
    <div class="overview-container">
        <div class="overview-card">
            <h3><i class="fas fa-users"></i> Tasks by User</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Total</th>
                        <th>Pending</th>
                        <th>In Progress</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_tasks && $user_tasks->num_rows > 0): ?>
                        <?php while ($user = $user_tasks->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></td>
                                <td><?php echo $user['total']; ?></td>
                                <td><?php echo $user['pending']; ?></td>
                                <td><?php echo $user['in_progress']; ?></td>
                                <td><?php echo $user['completed']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="overview-card">
            <h3><i class="fas fa-chart-pie"></i> Task Summary by Priority</h3>
            <div style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Urgent</span>
                        <span><?php echo $stats['urgent'] ?? 0; ?> tasks</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($stats['total_tasks'] > 0 ? ($stats['urgent'] / $stats['total_tasks']) * 100 : 0); ?>%; background: #e74c3c;"></div>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>High</span>
                        <span><?php echo $stats['high'] ?? 0; ?> tasks</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($stats['total_tasks'] > 0 ? ($stats['high'] / $stats['total_tasks']) * 100 : 0); ?>%; background: #f39c12;"></div>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Medium</span>
                        <span><?php echo $stats['medium'] ?? 0; ?> tasks</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($stats['total_tasks'] > 0 ? ($stats['medium'] / $stats['total_tasks']) * 100 : 0); ?>%; background: #3498db;"></div>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Low</span>
                        <span><?php echo $stats['low'] ?? 0; ?> tasks</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($stats['total_tasks'] > 0 ? ($stats['low'] / $stats['total_tasks']) * 100 : 0); ?>%; background: #27ae60;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="overview-card" style="margin-top: 30px;">
        <h3><i class="fas fa-clock"></i> Recent Tasks</h3>
        <?php if ($recent_tasks && $recent_tasks->num_rows > 0): ?>
            <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                <div class="task-item">
                    <div class="task-title">
                        <?php echo htmlspecialchars($task['title']); ?>
                        <span class="priority-badge priority-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                        <span class="status-badge status-<?php echo $task['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span>
                    </div>
                    <div class="task-meta">
                        <span><i class="fas fa-user"></i> Assigned to: <?php echo htmlspecialchars($task['assigned_to_name']); ?></span>
                        <span><i class="fas fa-user-check"></i> By: <?php echo htmlspecialchars($task['assigned_by_name']); ?></span>
                        <span><i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                        <?php if ($task['document_number']): ?>
                            <span><i class="fas fa-file-alt"></i> Doc: <?php echo $task['document_number']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-inbox" style="font-size: 48px;"></i>
                <p>No tasks found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>