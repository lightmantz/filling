<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
requireRole('super_admin'); // Only super admin can add users
require_once '../../includes/functions.php';

$page_title = 'Add New User';
$base_url = '../../';

$conn = getConnection();
$error = '';
$success = '';

// Get all roles for dropdown
$roles = ['records_officer', 'admin', 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($role)) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $insert_query = "INSERT INTO users (username, password, email, full_name, role, phone, department, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssssss", $username, $hashed_password, $email, $full_name, $role, $phone, $department);
            
            if ($insert_stmt->execute()) {
                $user_id = $insert_stmt->insert_id;
                
                // Log activity
                auditLog($_SESSION['user_id'], 'add_user', "Added new user: $username ($role)");
                
                $success = "User added successfully! Password: $password (Please inform the user to change their password)";
                
                // Clear form
                $_POST = array();
            } else {
                $error = 'Failed to add user. Please try again.';
            }
        }
    }
}

// Get all users for display
$users_query = "SELECT id, username, email, full_name, role, phone, department, is_active, created_at, last_login 
                FROM users 
                ORDER BY created_at DESC";
$users = $conn->query($users_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .user-management {
        display: grid;
        grid-template-columns: 450px 1fr;
        gap: 30px;
    }
    
    .add-user-form {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 18px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }
    
    .form-group label.required:after {
        content: '*';
        color: #e74c3c;
        margin-left: 4px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .users-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .users-table th,
    .users-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .users-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    
    .users-table tr:hover {
        background: #f9f9f9;
    }
    
    .role-badge {
        display: inline-block;
        padding: 4px 10px;
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
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
    }
    
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
    
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 12px;
    }
    
    .alert {
        padding: 12px 15px;
        border-radius: 6px;
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
    
    .action-icons {
        display: flex;
        gap: 8px;
    }
    
    .action-icons a {
        color: #666;
        text-decoration: none;
    }
    
    .action-icons a:hover {
        color: #667eea;
    }
    
    .search-box {
        margin-bottom: 20px;
    }
    
    .search-box input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    
    .password-info {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    @media (max-width: 900px) {
        .user-management {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-users"></i> User Management</h2>
    
    <div class="user-management">
        <div class="add-user-form">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="required">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
    <label>Department</label>
    <select name="department_id">
        <option value="">Select Department</option>
        <?php
        $depts = $conn->query("SELECT id, name, dept_code FROM departments WHERE is_active = 1 ORDER BY name");
        while ($dept = $depts->fetch_assoc()):
        ?>
            <option value="<?php echo $dept['id']; ?>">
                <?php echo htmlspecialchars($dept['name']); ?> (<?php echo $dept['dept_code']; ?>)
            </option>
        <?php endwhile; ?>
    </select>
    <small>Assign user to a department</small>
</div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="required">Role / Access Level</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="records_officer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'records_officer') ? 'selected' : ''; ?>>
                            Records Officer - Can manage all documents and folders
                        </option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                            Administrator - Can review and approve documents
                        </option>
                        <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>
                            Normal User - Can submit and track documents
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Password</label>
                    <input type="password" name="password" required>
                    <div class="password-info">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Create User
                </button>
            </form>
        </div>
        
        <div class="users-list">
            <h3><i class="fas fa-list"></i> Existing Users</h3>
            
            <div class="search-box">
                <input type="text" id="searchUser" placeholder="Search by name, username, email or role..." onkeyup="searchUsers()">
            </div>
            
            <div style="overflow-x: auto;">
                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
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
                                    <td class="action-icons">
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="toggle_user.php?id=<?php echo $user['id']; ?>" 
                                               title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User"
                                               onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                            </a>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                                               title="Delete User"
                                               onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i>
                                    <p>No users found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
</script>

<?php
include_once '../../includes/footer.php';
?>