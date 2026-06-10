<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'User List';
$base_url = '../../';

$conn = getConnection();

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['delete'];
    
    if ($user_id != $_SESSION['user_id']) {
        $delete_query = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            auditLog($_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
            $_SESSION['flash_message'] = "User deleted successfully!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to delete user.";
            $_SESSION['flash_type'] = 'error';
        }
    } else {
        $_SESSION['flash_message'] = "You cannot delete your own account.";
        $_SESSION['flash_type'] = 'error';
    }
    
    header("Location: users_list.php");
    exit();
}

// Get all users with department info
$query = "SELECT u.*, d.name as department_name, d.dept_code 
          FROM users u
          LEFT JOIN departments d ON u.department_id = d.id
          ORDER BY u.created_at DESC";
$users = $conn->query($query);

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
    
    .search-box input {
        padding: 10px;
        width: 300px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    
    .data-table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .data-table th,
    .data-table td {
        padding: 15px;
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
    
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .role-super_admin { background: #e74c3c; color: white; }
    .role-records_officer { background: #3498db; color: white; }
    .role-admin { background: #f39c12; color: white; }
    .role-user { background: #27ae60; color: white; }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
    }
    
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
    
    .dept-badge {
        background: #e9ecef;
        color: #495057;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .btn-view {
        background: #3498db;
        color: white;
    }
    
    .btn-edit {
        background: #ff9800;
        color: white;
    }
    
    .btn-delete {
        background: #dc3545;
        color: white;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
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
    
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
    }
    
    @media (max-width: 768px) {
        .data-table {
            display: block;
            overflow-x: auto;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-users"></i> User Management</h2>
    
    <div class="action-bar">
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
        </a>
        <div class="search-box">
            <input type="text" id="searchUser" placeholder="Search by name, username, email, department..." onkeyup="searchUsers()">
        </div>
    </div>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type']; ?>" id="flashMessage">
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <table class="data-table" id="usersTable">
        <thead>
            <tr>
                <th>User</th>
                <th>Department</th>
                <th>Role</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users && $users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                            <small style="color: #666;">@<?php echo htmlspecialchars($user['username']); ?></small>
                        </td>
                        <td>
                            <?php if ($user['department_name']): ?>
                                <span class="dept-badge">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department_name']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($user['email']); ?><br>
                            <small><?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                        </td>
                        <td class="action-buttons">
                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-icon btn-view" title="View User">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-icon btn-edit" title="Edit User">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="users_list.php?delete=<?php echo $user['id']; ?>" class="btn-icon btn-delete" 
                                   onclick="return confirm('Delete this user? This action cannot be undone.')" title="Delete User">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i>
                        <p>No users found.</p>
                        <a href="add_user.php" class="btn btn-primary">Add First User</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function searchUsers() {
    let input = document.getElementById('searchUser');
    let filter = input.value.toUpperCase();
    let table = document.getElementById('usersTable');
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < td.length - 1; j++) {
            if (td[j]) {
                let txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        tr[i].style.display = found ? '' : 'none';
    }
}

setTimeout(function() {
    let flash = document.getElementById('flashMessage');
    if (flash) {
        flash.style.transition = 'opacity 0.5s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }
}, 5000);
</script>

<?php
include_once '../../includes/footer.php';
?>