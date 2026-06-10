<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Edit User';
$base_url = '../../';

$conn = getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($user_id <= 0) {
    header('Location: users_list.php');
    exit();
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users_list.php');
    exit();
}

// Get departments
$departments = $conn->query("SELECT id, name, dept_code FROM departments WHERE is_active = 1 ORDER BY name");

$roles = ['records_officer', 'admin', 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $phone = trim($_POST['phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $change_password = isset($_POST['change_password']) ? 1 : 0;
    
    if (empty($full_name) || empty($email) || empty($role)) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $update_query = "UPDATE users SET full_name = ?, email = ?, role = ?, department_id = ?, phone = ?, is_active = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssssii", $full_name, $email, $role, $department_id, $phone, $is_active, $user_id);
        
        if ($update_stmt->execute()) {
            if ($change_password && !empty($_POST['new_password'])) {
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pass_query = "UPDATE users SET password = ? WHERE id = ?";
                    $pass_stmt = $conn->prepare($pass_query);
                    $pass_stmt->bind_param("si", $hashed_password, $user_id);
                    $pass_stmt->execute();
                    $success = "User updated successfully! Password has been changed.";
                } else {
                    $error = "Password update failed. Passwords must match and be at least 6 characters.";
                }
            } else {
                $success = "User updated successfully!";
            }
            
            auditLog($_SESSION['user_id'], 'edit_user', "Edited user: {$user['username']}");
            
            // Refresh user data
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update user.';
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .edit-container {
        max-width: 600px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-group input {
        width: auto;
    }
    
    .password-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
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
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-edit"></i> Edit User</h2>
    
    <div class="edit-container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small>Username cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <select name="department_id">
                    <option value="">Select Department</option>
                    <?php while ($dept = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo ($user['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?> (<?php echo $dept['dept_code']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <small>Assign user to a department</small>
            </div>
            
            <div class="form-group">
                <label>Role *</label>
                <select name="role" required>
                    <option value="records_officer" <?php echo $user['role'] == 'records_officer' ? 'selected' : ''; ?>>
                        Records Officer
                    </option>
                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>
                        Administrator
                    </option>
                    <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>
                        Normal User
                    </option>
                </select>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active">Account Active</label>
            </div>
            
            <div class="password-section">
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="change_password" id="change_password" value="1">
                    <label for="change_password">Change Password</label>
                </div>
                
                <div id="password_fields" style="display: none; margin-top: 15px;">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password">
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="users_list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('change_password')?.addEventListener('change', function() {
    document.getElementById('password_fields').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php
include_once '../../includes/footer.php';
?>