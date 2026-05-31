<?php
require_once '../../config/session.php';
requireLogin();
requireRole('admin');
require_once '../../includes/functions.php';

$page_title = 'Administrator Dashboard';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get statistics - FIXED queries
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'submitted' OR status = 'submitted_to_admin' THEN 1 END) as pending_review,
    COUNT(CASE WHEN status = 'in_review' AND current_holder = ? THEN 1 END) as assigned_to_me,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed
    FROM documents";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get documents assigned to this admin
$assigned_query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  JOIN users u ON d.submitted_by = u.id
                  WHERE (d.current_holder = ? OR d.status = 'submitted_to_admin')
                  ORDER BY d.created_at DESC
                  LIMIT 10";
$stmt = $conn->prepare($assigned_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assigned_docs = $stmt->get_result();

// Get recent activity - FIXED query
$activity_query = "SELECT w.*, d.title as document_title, u.full_name as action_by
                  FROM workflow_tracking w
                  JOIN documents d ON w.document_id = d.id
                  JOIN users u ON w.from_user = u.id
                  WHERE w.to_user = ? OR w.from_user = ?
                  ORDER BY w.created_at DESC
                  LIMIT 10";
$stmt = $conn->prepare($activity_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$activities = $stmt->get_result();

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
        margin: 0 0 10px 0;
        font-size: 14px;
        opacity: 0.9;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
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
    
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-submitted { background-color: #3498db; color: white; }
    .status-submitted_to_admin { background-color: #9b59b6; color: white; }
    .status-in_review { background-color: #f39c12; color: white; }
    .status-approved { background-color: #27ae60; color: white; }
    .status-rejected { background-color: #e74c3c; color: white; }
    .status-closed { background-color: #2c3e50; color: white; }
    
    .btn-small {
        display: inline-block;
        padding: 4px 10px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        background-color: #3498db;
        color: white;
    }
    
    .btn-small:hover {
        background-color: #2980b9;
    }
    
    .activity-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .activity-item:hover {
        background: #f9f9f9;
    }
    
    .activity-icon {
        width: 35px;
        height: 35px;
        background: #f0f0f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #667eea;
    }
    
    .activity-details {
        flex: 1;
    }
    
    .activity-details strong {
        color: #333;
    }
    
    .activity-time {
        font-size: 11px;
        color: #999;
        margin-top: 3px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-block;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background-color: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #2980b9;
    }
    
    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #7f8c8d;
    }
</style>

<div class="content-wrapper">
    <h2>Administrator Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Pending Review</h3>
            <div class="stat-number"><?php echo $stats['pending_review'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Assigned to Me</h3>
            <div class="stat-number"><?php echo $stats['assigned_to_me'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Approved</h3>
            <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Rejected</h3>
            <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="card">
            <h3><i class="fas fa-tasks"></i> Documents Requiring Action</h3>
            <?php if ($assigned_docs && $assigned_docs->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document #</th>
                            <th>Title</th>
                            <th>Folder</th>
                            <th>Submitted By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = $assigned_docs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $doc['document_number']; ?></td>
                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td><?php echo htmlspecialchars($doc['folder_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $doc['status']; ?>">
                                        <?php echo str_replace('_', ' ', $doc['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../documents/view.php?id=<?php echo $doc['id']; ?>" class="btn-small">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No documents require action at this time.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <?php if ($activities && $activities->num_rows > 0): ?>
                <?php while ($activity = $activities->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-<?php echo $activity['action'] == 'assign' ? 'user-plus' : 'comment'; ?>"></i>
                        </div>
                        <div class="activity-details">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['action_by']); ?></strong> 
                                <?php echo $activity['action']; ?>d document 
                                <strong>"<?php echo htmlspecialchars($activity['document_title']); ?>"</strong>
                            </div>
                            <div class="activity-time">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No recent activity found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="quick-actions">
            <a href="../documents/pending.php" class="btn btn-primary">
                <i class="fas fa-clipboard-list"></i> View All Pending
            </a>
            <a href="../documents/assigned_to_me.php" class="btn btn-primary">
                <i class="fas fa-tasks"></i> My Assigned Tasks
            </a>
            <a href="../reports/index.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Generate Reports
            </a>
            <a href="../documents/completed.php" class="btn btn-secondary">
                <i class="fas fa-check-circle"></i> Completed Reviews
            </a>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>