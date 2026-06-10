<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Departments';
$base_url = '../../';

$conn = getConnection();

// Handle delete department
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $dept_id = (int)$_GET['delete'];
    
    // Check if department has users
    $check_query = "SELECT COUNT(*) as count FROM users WHERE department_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $dept_id);
    $check_stmt->execute();
    $user_count = $check_stmt->get_result()->fetch_assoc()['count'];
    
    if ($user_count > 0) {
        $_SESSION['flash_message'] = "Cannot delete department. It has $user_count user(s) assigned.";
        $_SESSION['flash_type'] = 'error';
    } else {
        $delete_query = "DELETE FROM departments WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $dept_id);
        
        if ($delete_stmt->execute()) {
            auditLog($_SESSION['user_id'], 'delete_department', "Deleted department ID: $dept_id");
            $_SESSION['flash_message'] = "Department deleted successfully!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to delete department.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    header("Location: index.php");
    exit();
}

// Get all departments with stats
$query = "SELECT d.*, 
          COUNT(DISTINCT u.id) as user_count,
          COUNT(DISTINCT f.id) as folder_count,
          COUNT(DISTINCT doc.id) as document_count
          FROM departments d
          LEFT JOIN users u ON d.id = u.department_id
          LEFT JOIN folders f ON d.id = f.department_id
          LEFT JOIN documents doc ON d.id = doc.department_id
          GROUP BY d.id
          ORDER BY d.name ASC";
$departments = $conn->query($query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .action-bar {
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .departments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .dept-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: all 0.3s;
    }
    
    .dept-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    .dept-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .dept-code {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .dept-name {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 10px 0;
    }
    
    .dept-stats {
        display: flex;
        gap: 15px;
        margin: 15px 0;
        font-size: 13px;
        color: #666;
    }
    
    .dept-stats span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .dept-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        grid-column: 1 / -1;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-building"></i> Departments</h2>
    
    <div class="action-bar">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Department
        </a>
    </div>
    
    <div class="departments-grid">
        <?php if ($departments && $departments->num_rows > 0): ?>
            <?php while ($dept = $departments->fetch_assoc()): ?>
                <div class="dept-card">
                    <div class="dept-header">
                        <span class="dept-code"><?php echo htmlspecialchars($dept['dept_code']); ?></span>
                        <span class="status-badge status-<?php echo $dept['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <div class="dept-name">
                        <?php echo htmlspecialchars($dept['name']); ?>
                    </div>
                    <?php if ($dept['description']): ?>
                        <div style="font-size: 13px; color: #666; margin: 10px 0;">
                            <?php echo htmlspecialchars(substr($dept['description'], 0, 100)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="dept-stats">
                        <span><i class="fas fa-users"></i> <?php echo $dept['user_count']; ?> Users</span>
                        <span><i class="fas fa-folder"></i> <?php echo $dept['folder_count']; ?> Folders</span>
                        <span><i class="fas fa-file-alt"></i> <?php echo $dept['document_count']; ?> Docs</span>
                    </div>
                    <div class="dept-actions">
                        <a href="view.php?id=<?php echo $dept['id']; ?>" class="btn btn-primary btn-small">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit.php?id=<?php echo $dept['id']; ?>" class="btn btn-warning btn-small">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="index.php?delete=<?php echo $dept['id']; ?>" class="btn btn-danger btn-small" 
                           onclick="return confirm('Delete this department? This will remove all associated data.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-building" style="font-size: 64px; color: #ccc;"></i>
                <h3>No Departments</h3>
                <p>Create your first department to get started.</p>
                <a href="add.php" class="btn btn-primary">Add Department</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>