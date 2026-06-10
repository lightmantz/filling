<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Edit Folder';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Check permission - allow records_officer AND super_admin
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: index.php');
    exit();
}

$folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($folder_id <= 0) {
    header('Location: index.php');
    exit();
}

// Get folder details
$query = "SELECT f.*, c.name as category_name 
          FROM folders f
          LEFT JOIN categories c ON f.category_id = c.id
          WHERE f.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$folder = $stmt->get_result()->fetch_assoc();

if (!$folder) {
    header('Location: index.php');
    exit();
}

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    
    if (empty($name)) {
        $error = 'Folder name is required.';
    } else {
        $update_query = "UPDATE folders SET name = ?, category_id = ?, description = ?, is_confidential = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sissi", $name, $category_id, $description, $is_confidential, $folder_id);
        
        if ($update_stmt->execute()) {
            auditLog($_SESSION['user_id'], 'edit_folder', "Edited folder ID: $folder_id");
            $success = "Folder updated successfully!";
            
            // Refresh folder data
            $stmt->execute();
            $folder = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update folder.";
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .edit-container {
        max-width: 700px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .form-group {
        margin-bottom: 25px;
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
    }
    
    .form-group input:focus,
    .form-group select:focus,
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
    
    .checkbox-group label {
        margin: 0;
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
        margin-top: 30px;
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
    }
    
    .folder-info {
        background: #f0f7ff;
        border: 1px solid #cce5ff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .folder-info p {
        margin: 5px 0;
        font-size: 13px;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-edit"></i> Edit Folder</h2>
    
    <div class="edit-container">
        <div class="folder-info">
            <p><strong>Folder Number:</strong> <?php echo htmlspecialchars($folder['folder_number']); ?></p>
            <p><strong>Created:</strong> <?php echo date('F d, Y', strtotime($folder['created_at'])); ?></p>
            <p><strong>Current Status:</strong> 
                <span class="status-badge status-<?php echo $folder['status']; ?>">
                    <?php echo ucfirst($folder['status']); ?>
                </span>
            </p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="required">Folder Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($folder['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                    <option value="">Select Category</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $folder['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?php echo htmlspecialchars($folder['description']); ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_confidential" id="is_confidential" value="1" <?php echo $folder['is_confidential'] ? 'checked' : ''; ?>>
                <label for="is_confidential">Mark as Confidential Folder</label>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
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