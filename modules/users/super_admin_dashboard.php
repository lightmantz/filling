<?php
// modules/users/super_admin_dashboard.php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Super Admin Dashboard';
$base_url = '../../';

$conn = getConnection();

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'records_officer' THEN 1 ELSE 0 END) as records_officers,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
    FROM users";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get document and folder statistics
$doc_stats_query = "SELECT 
    (SELECT COUNT(*) FROM folders) as total_folders,
    (SELECT COUNT(*) FROM folders WHERE status = 'active') as active_folders,
    (SELECT COUNT(*) FROM folders WHERE status = 'archived') as archived_folders,
    (SELECT COUNT(*) FROM documents) as total_documents,
    (SELECT COUNT(*) FROM documents WHERE status = 'submitted') as submitted_documents,
    (SELECT COUNT(*) FROM documents WHERE status = 'approved') as approved_documents,
    (SELECT COUNT(*) FROM documents WHERE status = 'rejected') as rejected_documents,
    (SELECT COUNT(*) FROM documents WHERE status = 'in_review') as in_review_documents,
    (SELECT COUNT(*) FROM documents WHERE status = 'closed') as closed_documents";
$doc_stats = $conn->query($doc_stats_query)->fetch_assoc();

// Get task statistics
$task_stats_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tasks,
    SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks
    FROM tasks";
$task_stats = $conn->query($task_stats_query)->fetch_assoc();

// Get assigned documents statistics (documents assigned to users for review)
$assigned_docs_query = "SELECT 
    COUNT(*) as assigned_documents,
    SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN current_holder IS NOT NULL AND status != 'closed' THEN 1 ELSE 0 END) as currently_assigned
    FROM documents 
    WHERE current_holder IS NOT NULL OR status = 'submitted_to_admin'";
$assigned_docs = $conn->query($assigned_docs_query)->fetch_assoc();

// Get recent activities
$recent_activities_query = "SELECT 
    'user' as type, u.full_name, u.created_at as date, 'joined' as action
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT 5";
$recent_users = $conn->query($recent_activities_query);

$recent_docs_query = "SELECT 
    'document' as type, d.title as name, d.created_at as date, 'submitted' as action,
    u.full_name as user_name
    FROM documents d
    JOIN users u ON d.submitted_by = u.id
    ORDER BY d.created_at DESC 
    LIMIT 5";
$recent_docs = $conn->query($recent_docs_query);

$recent_tasks_query = "SELECT 
    'task' as type, t.title as name, t.created_at as date, 'assigned' as action,
    u.full_name as user_name
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    ORDER BY t.created_at DESC 
    LIMIT 5";
$recent_tasks = $conn->query($recent_tasks_query);

// Combine recent activities
$recent_activities = [];
while ($row = $recent_users->fetch_assoc()) $recent_activities[] = $row;
while ($row = $recent_docs->fetch_assoc()) $recent_activities[] = $row;
while ($row = $recent_tasks->fetch_assoc()) $recent_activities[] = $row;

// Sort by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activities = array_slice($recent_activities, 0, 10);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

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
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h3 {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
    }
    
    .stat-sub {
        font-size: 11px;
        opacity: 0.8;
        margin-top: 5px;
    }
    
    /* Section Headers */
    .section-header {
        margin: 30px 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-header h3 {
        margin: 0;
        color: #333;
    }
    
    /* Dashboard Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    
    /* Document Status List */
    .status-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .status-label {
        font-size: 14px;
        color: #666;
    }
    
    .status-value {
        font-weight: bold;
        font-size: 18px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-submitted { background: #3498db; color: white; }
    .status-approved { background: #27ae60; color: white; }
    .status-rejected { background: #e74c3c; color: white; }
    .status-in_review { background: #f39c12; color: white; }
    .status-closed { background: #2c3e50; color: white; }
    .status-pending { background: #95a5a6; color: white; }
    .status-in_progress { background: #3498db; color: white; }
    .status-completed { background: #27ae60; color: white; }
    .status-urgent { background: #e74c3c; color: white; }
    
    /* Activity List */
    .activity-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.3s;
    }
    
    .activity-item:hover {
        background: #f9f9f9;
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .activity-icon.user { background: #e3f2fd; color: #1976d2; }
    .activity-icon.document { background: #e8f5e9; color: #388e3c; }
    .activity-icon.task { background: #fff3e0; color: #f57c00; }
    
    .activity-details {
        flex: 1;
    }
    
    .activity-action {
        font-size: 14px;
        margin-bottom: 3px;
    }
    
    .activity-time {
        font-size: 11px;
        color: #999;
    }
    
    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
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
    
    .btn-success {
        background: #27ae60;
        color: white;
    }
    
    .btn-success:hover {
        background: #229954;
        transform: translateY(-2px);
    }
    
    .btn-warning {
        background: #f39c12;
        color: white;
    }
    
    .btn-warning:hover {
        background: #e67e22;
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-chart-line"></i> Super Admin Dashboard</h2>
    
    <!-- User Statistics Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><i class="fas fa-users"></i> Total Users</h3>
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            <div class="stat-sub">Active: <?php echo $stats['active_users']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-user-tie"></i> Records Officers</h3>
            <div class="stat-number"><?php echo $stats['records_officers']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-user-shield"></i> Administrators</h3>
            <div class="stat-number"><?php echo $stats['admins']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-user"></i> Normal Users</h3>
            <div class="stat-number"><?php echo $stats['users']; ?></div>
        </div>
    </div>
    
    <!-- Document & Folder Statistics Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><i class="fas fa-folder"></i> Total Folders</h3>
            <div class="stat-number"><?php echo $doc_stats['total_folders']; ?></div>
            <div class="stat-sub">Active: <?php echo $doc_stats['active_folders']; ?> | Archived: <?php echo $doc_stats['archived_folders']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-file-alt"></i> Total Documents</h3>
            <div class="stat-number"><?php echo $doc_stats['total_documents']; ?></div>
            <div class="stat-sub">Submitted: <?php echo $doc_stats['submitted_documents']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-check-circle"></i> Approved Documents</h3>
            <div class="stat-number"><?php echo $doc_stats['approved_documents']; ?></div>
            <div class="stat-sub">Rejected: <?php echo $doc_stats['rejected_documents']; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-tasks"></i> Assigned Documents</h3>
            <div class="stat-number"><?php echo $assigned_docs['assigned_documents'] ?? 0; ?></div>
            <div class="stat-sub">Under Review: <?php echo $assigned_docs['under_review'] ?? 0; ?></div>
        </div>
    </div>
    
    <!-- Task Statistics Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><i class="fas fa-tasks"></i> Total Tasks</h3>
            <div class="stat-number"><?php echo $task_stats['total_tasks'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-clock"></i> Pending Tasks</h3>
            <div class="stat-number"><?php echo $task_stats['pending_tasks'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-spinner"></i> In Progress</h3>
            <div class="stat-number"><?php echo $task_stats['in_progress_tasks'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Urgent/Overdue</h3>
            <div class="stat-number"><?php echo ($task_stats['urgent_tasks'] ?? 0) + ($task_stats['overdue_tasks'] ?? 0); ?></div>
            <div class="stat-sub">Urgent: <?php echo $task_stats['urgent_tasks'] ?? 0; ?> | Overdue: <?php echo $task_stats['overdue_tasks'] ?? 0; ?></div>
        </div>
    </div>
    
    <!-- Detailed Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Document Status Breakdown -->
        <div class="card">
            <h3><i class="fas fa-chart-pie"></i> Document Status Breakdown</h3>
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-file-upload"></i> Submitted</span>
                    <span class="status-value status-submitted"><?php echo $doc_stats['submitted_documents']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-eye"></i> In Review</span>
                    <span class="status-value status-in_review"><?php echo $doc_stats['in_review_documents']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-check-circle"></i> Approved</span>
                    <span class="status-value status-approved"><?php echo $doc_stats['approved_documents']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-times-circle"></i> Rejected</span>
                    <span class="status-value status-rejected"><?php echo $doc_stats['rejected_documents']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-archive"></i> Closed</span>
                    <span class="status-value status-closed"><?php echo $doc_stats['closed_documents']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Task Status Breakdown -->
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Task Status Breakdown</h3>
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-clock"></i> Pending</span>
                    <span class="status-value status-pending"><?php echo $task_stats['pending_tasks'] ?? 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-spinner"></i> In Progress</span>
                    <span class="status-value status-in_progress"><?php echo $task_stats['in_progress_tasks'] ?? 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-check-circle"></i> Completed</span>
                    <span class="status-value status-completed"><?php echo $task_stats['completed_tasks'] ?? 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-flag"></i> Urgent Priority</span>
                    <span class="status-value status-urgent"><?php echo $task_stats['urgent_tasks'] ?? 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                    <span class="status-value" style="color: #e74c3c;"><?php echo $task_stats['overdue_tasks'] ?? 0; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Folder Status -->
        <div class="card">
            <h3><i class="fas fa-folder-tree"></i> Folder Status</h3>
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-folder-open"></i> Active Folders</span>
                    <span class="status-value" style="color: #27ae60;"><?php echo $doc_stats['active_folders']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-archive"></i> Archived Folders</span>
                    <span class="status-value" style="color: #95a5a6;"><?php echo $doc_stats['archived_folders']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-lock"></i> Confidential Folders</span>
                    <?php
                    $confidential_query = "SELECT COUNT(*) as count FROM folders WHERE is_confidential = 1";
                    $confidential_result = $conn->query($confidential_query);
                    $confidential_count = $confidential_result->fetch_assoc()['count'];
                    ?>
                    <span class="status-value" style="color: #e74c3c;"><?php echo $confidential_count; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-chart-line"></i> Avg Documents/Folder</span>
                    <?php
                    $avg_docs = $doc_stats['total_folders'] > 0 ? round($doc_stats['total_documents'] / $doc_stats['total_folders'], 1) : 0;
                    ?>
                    <span class="status-value"><?php echo $avg_docs; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Summary -->
        <div class="card">
            <h3><i class="fas fa-chart-simple"></i> System Summary</h3>
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-users"></i> Total Users</span>
                    <span class="status-value"><?php echo $stats['total_users']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-folder"></i> Total Folders</span>
                    <span class="status-value"><?php echo $doc_stats['total_folders']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-file-alt"></i> Total Documents</span>
                    <span class="status-value"><?php echo $doc_stats['total_documents']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-tasks"></i> Total Tasks</span>
                    <span class="status-value"><?php echo $task_stats['total_tasks'] ?? 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><i class="fas fa-check-circle"></i> Completion Rate</span>
                    <?php
                    $total_docs = $doc_stats['total_documents'];
                    $approved_docs = $doc_stats['approved_documents'];
                    $completion_rate = $total_docs > 0 ? round(($approved_docs / $total_docs) * 100, 1) : 0;
                    ?>
                    <span class="status-value" style="color: #27ae60;"><?php echo $completion_rate; ?>%</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="card">
        <h3><i class="fas fa-history"></i> Recent Activities</h3>
        <div class="activity-list">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['type']; ?>">
                            <i class="fas <?php echo $activity['type'] == 'user' ? 'fa-user-plus' : ($activity['type'] == 'document' ? 'fa-file-alt' : 'fa-tasks'); ?>"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-action">
                                <?php if ($activity['type'] == 'user'): ?>
                                    <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong> joined the system
                                <?php elseif ($activity['type'] == 'document'): ?>
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong> submitted document 
                                    <strong>"<?php echo htmlspecialchars(substr($activity['name'], 0, 50)); ?>"</strong>
                                <?php else: ?>
                                    Task <strong>"<?php echo htmlspecialchars(substr($activity['name'], 0, 50)); ?>"</strong> was assigned to 
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($activity['date'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 48px;"></i>
                    <p>No recent activities</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="quick-actions">
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
            <a href="../tasks/assign.php" class="btn btn-success">
                <i class="fas fa-tasks"></i> Assign Task
            </a>
            <a href="../documents/all_documents.php" class="btn btn-primary">
                <i class="fas fa-file-alt"></i> View All Documents
            </a>
            <a href="../folders/index.php" class="btn btn-secondary">
                <i class="fas fa-folder"></i> Manage Folders
            </a>
            <a href="../reports/index.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
            <a href="../settings/index.php" class="btn btn-warning">
                <i class="fas fa-cogs"></i> System Settings
            </a>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>