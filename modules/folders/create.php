<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Create Folder';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$user_department_id = $_SESSION['department_id'] ?? null;

// Check permission - allow records_officer, super_admin, and department users
if ($user_role !== 'records_officer' && $user_role !== 'super_admin' && $user_role !== 'user') {
    header('Location: ' . BASE_URL . '/modules/folders/index.php');
    exit();
}

$error = '';
$success = '';

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Get departments for folder assignment (only for super_admin and records_officer)
$departments = null;
if ($user_role === 'super_admin' || $user_role === 'records_officer') {
    $departments = $conn->query("SELECT id, name, dept_code FROM departments WHERE is_active = 1 ORDER BY name");
}

// Generate folder number
$year = date('Y');
$count_query = "SELECT COUNT(*) as count FROM folders WHERE YEAR(created_at) = $year";
$count_result = $conn->query($count_query);
$count = $count_result->fetch_assoc()['count'] + 1;
$folder_number = sprintf("FLD-%s-%04d", $year, $count);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $visibility_type = $_POST['visibility_type'];
    $is_confidential = ($visibility_type === 'confidential') ? 1 : 0;
    
    // Set department based on role
    if ($user_role === 'super_admin' || $user_role === 'records_officer') {
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    } else {
        $department_id = $user_department_id;
    }
    
    $created_by = $user_id;
    
    // Validation
    if (empty($name)) {
        $error = "Folder name is required.";
    } elseif (empty($category_id)) {
        $error = "Please select a category.";
    } elseif (empty($visibility_type)) {
        $error = "Please select visibility type.";
    } elseif (($visibility_type === 'private' || $visibility_type === 'confidential') && empty($department_id) && $user_role !== 'super_admin') {
        $error = "Please select a department for private/confidential folders.";
    } else {
        $query = "INSERT INTO folders (folder_number, name, category_id, description, visibility_type, is_confidential, department_id, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssissiii", $folder_number, $name, $category_id, $description, $visibility_type, $is_confidential, $department_id, $created_by);
        
        if ($stmt->execute()) {
            $folder_id = $stmt->insert_id;
            auditLog($user_id, 'create_folder', "Created folder: $name (ID: $folder_id) - Visibility: $visibility_type");
            $success = "Folder created successfully!";
            
            // Generate new folder number for next folder
            $count++;
            $folder_number = sprintf("FLD-%s-%04d", $year, $count);
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Failed to create folder. Please try again.";
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .form-container {
        max-width: 600px;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin: 0 auto;
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
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .disabled-input {
        background: #f5f5f5;
        cursor: not-allowed;
        color: #666;
    }
    
    /* Visibility Options */
    .visibility-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 10px;
    }
    
    .visibility-card {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }
    
    .visibility-card:hover {
        border-color: #667eea;
        transform: translateY(-2px);
    }
    
    .visibility-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .visibility-card input {
        display: none;
    }
    
    .visibility-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .visibility-title {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .visibility-desc {
        font-size: 11px;
        color: #666;
    }
    
    .visibility-private .visibility-icon { color: #3498db; }
    .visibility-confidential .visibility-icon { color: #e74c3c; }
    .visibility-public .visibility-icon { color: #27ae60; }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .form-actions {
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
    
    .btn-secondary:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }
    
    .info-note {
        background: #f0f7ff;
        border: 1px solid #cce5ff;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
        font-size: 13px;
        color: #004085;
    }
    
    small {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .visibility-options {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .btn {
            justify-content: center;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-folder-plus"></i> Create New Folder</h2>
    
    <div class="form-container">
        <div class="info-note">
            <i class="fas fa-info-circle"></i> 
            Fill in the details below to create a new folder. Folder numbers are auto-generated.
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <a href="index.php" style="float: right; color: #155724; text-decoration: underline;">View All Folders →</a>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Folder Number</label>
                <input type="text" value="<?php echo $folder_number; ?>" disabled class="disabled-input">
                <small><i class="fas fa-info-circle"></i> Auto-generated sequential number</small>
            </div>
            
            <div class="form-group">
                <label class="required">Folder Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="Enter folder name">
            </div>
            
            <div class="form-group">
                <label class="required">Category</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small>Select the appropriate category for this folder</small>
            </div>
            
            <?php if ($user_role === 'super_admin' || $user_role === 'records_officer'): ?>
                <div class="form-group">
                    <label>Department (Optional)</label>
                    <select name="department_id">
                        <option value="">No Department (Global)</option>
                        <?php 
                        if ($departments) {
                            while ($dept = $departments->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?> (<?php echo $dept['dept_code']; ?>)
                            </option>
                        <?php endwhile; } ?>
                    </select>
                    <small>Assign folder to a specific department</small>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="required">Visibility Type</label>
                <div class="visibility-options">
                    <label class="visibility-card visibility-private <?php echo (!isset($_POST['visibility_type']) || $_POST['visibility_type'] == 'private') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="private" <?php echo (!isset($_POST['visibility_type']) || $_POST['visibility_type'] == 'private') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-lock"></i></div>
                        <div class="visibility-title">Private</div>
                        <div class="visibility-desc">Accessible only within your department</div>
                    </label>
                    <label class="visibility-card visibility-confidential <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'confidential') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="confidential" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'confidential') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="visibility-title">Confidential</div>
                        <div class="visibility-desc">Requires secret code for access</div>
                    </label>
                    <label class="visibility-card visibility-public <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'public') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="public" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'public') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-globe"></i></div>
                        <div class="visibility-title">Public</div>
                        <div class="visibility-desc">Submitted to Records Office for action</div>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Enter folder description (optional)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                <small>Brief description of the folder's purpose</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Folder
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Visibility card selection
document.querySelectorAll('.visibility-card').forEach(card => {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        
        document.querySelectorAll('.visibility-card').forEach(c => {
            c.classList.remove('selected');
        });
        this.classList.add('selected');
    });
});
</script>

<?php
include_once '../../includes/footer.php';
?>