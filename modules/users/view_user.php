<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'View User Details';
$base_url = '../../';

$conn = getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: users_list.php');
    exit();
}

// Get user details with department info
$query = "SELECT u.*, d.name as department_name, d.dept_code, d.description as department_description
          FROM users u
          LEFT JOIN departments d ON u.department_id = d.id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users_list.php');
    exit();
}

// Get user statistics - FIXED: Each ? needs a corresponding parameter
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM documents WHERE submitted_by = ?) as total_documents,
    (SELECT COUNT(*) FROM documents WHERE submitted_by = ? AND status = 'approved') as approved_documents,
    (SELECT COUNT(*) FROM documents WHERE submitted_by = ? AND status = 'rejected') as rejected_documents,
    (SELECT COUNT(*) FROM documents WHERE submitted_by = ? AND status IN ('submitted', 'in_review', 'submitted_to_admin')) as pending_documents,
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'completed') as pending_tasks,
    (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed') as completed_tasks,
    (SELECT COUNT(*) FROM comments WHERE user_id = ?) as total_comments,
    (SELECT COUNT(*) FROM workflow_tracking WHERE from_user = ? OR to_user = ?) as total_activities";
$stmt = $conn->prepare($stats_query);
// 9 parameters: user_id appears 9 times
$stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get user's recent documents
$docs_query = "SELECT d.*, f.name as folder_name 
               FROM documents d
               JOIN folders f ON d.folder_id = f.id
               WHERE d.submitted_by = ?
               ORDER BY d.created_at DESC
               LIMIT 10";
$stmt = $conn->prepare($docs_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_docs = $stmt->get_result();

// Get user's recent activities
$activities_query = "SELECT w.*, d.title as document_title
                    FROM workflow_tracking w
                    JOIN documents d ON w.document_id = d.id
                    WHERE w.from_user = ? OR w.to_user = ?
                    ORDER BY w.created_at DESC
                    LIMIT 15";
$stmt = $conn->prepare($activities_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$activities = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .user-profile {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
    }
    
    /* Profile Sidebar */
    .profile-sidebar {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        text-align: center;
    }
    
    .user-avatar-large {
        width: 120px;
        height: 120px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: white;
        font-weight: bold;
    }
    
    .user-name-large {
        font-size: 22px;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    
    .user-role-large {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin: 10px 0;
    }
    
    .role-super_admin { background: #e74c3c; color: white; }
    .role-records_officer { background: #3498db; color: white; }
    .role-admin { background: #f39c12; color: white; }
    .role-user { background: #27ae60; color: white; }
    
    .status-active {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        background: #f8d7da;
        color: #721c24;
    }
    
    .info-list {
        text-align: left;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .info-item i {
        width: 20px;
        color: #667eea;
    }
    
    .info-label {
        font-size: 12px;
        color: #666;
        margin-bottom: 2px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: #333;
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
        color: #667eea;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    /* Sections */
    .section-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* Tables */
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
        color: #333;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    /* Activity Timeline */
    .timeline {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .timeline-item {
        display: flex;
        gap: 15px;
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.3s;
    }
    
    .timeline-item:hover {
        background: #f9f9f9;
    }
    
    .timeline-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .timeline-icon.submit { background: #e3f2fd; color: #1976d2; }
    .timeline-icon.approve { background: #e8f5e9; color: #388e3c; }
    .timeline-icon.reject { background: #ffebee; color: #d32f2f; }
    .timeline-icon.comment { background: #f3e5f5; color: #7b1fa2; }
    .timeline-icon.assign { background: #fff3e0; color: #f57c00; }
    
    .timeline-content {
        flex: 1;
    }
    
    .timeline-action {
        font-size: 13px;
        margin-bottom: 3px;
    }
    
    .timeline-doc {
        font-size: 12px;
        color: #667eea;
    }
    
    .timeline-date {
        font-size: 11px;
        color: #999;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-submitted { background: #3498db; color: white; }
    .status-approved { background: #27ae60; color: white; }
    .status-rejected { background: #e74c3c; color: white; }
    .status-in_review { background: #f39c12; color: white; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
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
    
    .btn-warning {
        background: #ff9800;
        color: white;
    }
    
    .btn-warning:hover {
        background: #e68900;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 5px 12px;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px;
        color: #999;
    }
    
    @media (max-width: 900px) {
        .user-profile {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h2><i class="fas fa-user-circle"></i> User Profile</h2>
        <div style="display: flex; gap: 10px;">
            <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <a href="users_list.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="user-profile">
        <!-- Left Column - Profile Info -->
        <div class="profile-sidebar">
            <div class="user-avatar-large">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-name-large"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="user-role-large role-<?php echo $user['role']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
            </div>
            <div class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $user['is_active'] ? 'Active Account' : 'Inactive Account'; ?>
            </div>
            
            <div class="info-list">
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <div class="info-label">Username</div>
                        <div class="info-value">@<?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-building"></i>
                    <div>
                        <div class="info-label">Department</div>
                        <div class="info-value">
                            <?php if ($user['department_name']): ?>
                                <strong><?php echo htmlspecialchars($user['department_name']); ?></strong>
                                <span style="font-size: 11px; color: #666;">(<?php echo $user['dept_code']; ?>)</span>
                            <?php else: ?>
                                <span style="color: #999;">Not assigned to any department</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
                <?php if ($user['last_login']): ?>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="info-label">Last Login</div>
                            <div class="info-value"><?php echo date('F d, Y H:i', strtotime($user['last_login'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column - Stats and Activity -->
        <div>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_documents'] ?? 0; ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved_documents'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected_documents'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_documents'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_tasks'] ?? 0; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                    <div class="stat-label">Completed Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_comments'] ?? 0; ?></div>
                    <div class="stat-label">Comments Made</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_activities'] ?? 0; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Recent Documents
                </div>
                <?php if ($recent_docs && $recent_docs->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document #</th>
                                <th>Title</th>
                                <th>Folder</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $recent_docs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $doc['document_number']; ?></a></td>
                                    <td><?php echo htmlspecialchars($doc['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($doc['folder_name']); ?></a></td>
                                    <td><span class="status-badge status-<?php echo $doc['status']; ?>"><?php echo ucfirst($doc['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No documents submitted yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Activity Timeline -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Activity
                </div>
                <?php if ($activities && $activities->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($activity = $activities->fetch_assoc()): 
                            $icon_class = '';
                            $icon = '';
                            switch($activity['action']) {
                                case 'submit': $icon_class = 'submit'; $icon = 'fa-upload'; break;
                                case 'approve': $icon_class = 'approve'; $icon = 'fa-check'; break;
                                case 'reject': $icon_class = 'reject'; $icon = 'fa-times'; break;
                                case 'comment': $icon_class = 'comment'; $icon = 'fa-comment'; break;
                                case 'assign': $icon_class = 'assign'; $icon = 'fa-user-plus'; break;
                                default: $icon_class = 'submit'; $icon = 'fa-clock';
                            }
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-icon <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-action">
                                        <strong><?php echo ucfirst($activity['action']); ?></strong>
                                        <?php if ($activity['action'] == 'assign'): ?>
                                            document to user
                                        <?php else: ?>
                                            document "<?php echo htmlspecialchars($activity['document_title']); ?>"
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($activity['notes']): ?>
                                        <div class="timeline-doc">"<?php echo htmlspecialchars(substr($activity['notes'], 0, 100)); ?>"</div>
                                    <?php endif; ?>
                                    <div class="timeline-date">
                                        <?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>