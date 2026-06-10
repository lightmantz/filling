<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Add Department';
$base_url = '../../';

$conn = getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_code = strtoupper(trim($_POST['dept_code']));
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $head_of_department = trim($_POST['head_of_department']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($dept_code) || empty($name)) {
        $error = "Department code and name are required.";
    } else {
        // Check if code exists
        $check_query = "SELECT id FROM departments WHERE dept_code = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $dept_code);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Department code already exists.";
        } else {
            $insert_query = "INSERT INTO departments (dept_code, name, description, head_of_department, email, phone, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssssssi", $dept_code, $name, $description, $head_of_department, $email, $phone, $is_active);
            
            if ($insert_stmt->execute()) {
                auditLog($_SESSION['user_id'], 'add_department', "Added department: $name");
                $success = "Department added successfully!";
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Failed to add department.";
            }
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .form-container {
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
    
    .form-group label.required:after {
        content: '*';
        color: #e74c3c;
        margin-left: 4px;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-group input {
        width: auto;
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
    
    .btn-group {
        display: flex;
        gap: 15px;
        margin-top: 25px;
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
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-plus"></i> Add Department</h2>
    
    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="required">Department Code</label>
                <input type="text" name="dept_code" value="<?php echo htmlspecialchars($_POST['dept_code'] ?? ''); ?>" 
                       placeholder="e.g., HR, ICT, ACC" required>
                <small>Unique code for the department (max 20 characters)</small>
            </div>
            
            <div class="form-group">
                <label class="required">Department Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                       placeholder="e.g., Human Resources" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Department description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Head of Department</label>
                <input type="text" name="head_of_department" value="<?php echo htmlspecialchars($_POST['head_of_department'] ?? ''); ?>" 
                       placeholder="Full name of department head">
            </div>
            
            <div class="form-group">
                <label>Department Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="dept@example.com">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                       placeholder="Contact number">
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] == '1') ? 'checked' : ''; ?>>
                <label for="is_active">Department Active</label>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Department
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>