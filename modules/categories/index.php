<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$page_title = 'Manage Categories';
$base_url = '../../';

$conn = getConnection();
$error = '';
$success = '';

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (!empty($name)) {
        $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            $success = "Category added successfully!";
        } else {
            $error = "Failed to add category. Name might already exist.";
        }
    } else {
        $error = "Category name is required.";
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if category has folders
    $check = $conn->prepare("SELECT COUNT(*) as count FROM folders WHERE category_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count == 0) {
        $delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $delete->bind_param("i", $id);
        if ($delete->execute()) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Failed to delete category.";
        }
    } else {
        $error = "Cannot delete category. It has $count folder(s) associated with it.";
    }
}

// Get all categories
$categories = $conn->query("SELECT c.*, COUNT(f.id) as folder_count 
                            FROM categories c
                            LEFT JOIN folders f ON c.id = f.category_id
                            GROUP BY c.id
                            ORDER BY c.name");

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .categories-container {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 30px;
    }
    
    .add-category-form {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .categories-list {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .category-item {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.3s;
    }
    
    .category-item:hover {
        background: #f9f9f9;
    }
    
    .category-info h4 {
        margin: 0 0 5px 0;
        color: #333;
    }
    
    .category-info p {
        margin: 0;
        font-size: 13px;
        color: #666;
    }
    
    .category-stats {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }
    
    .delete-btn {
        color: #dc3545;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 18px;
        padding: 5px 10px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    
    .delete-btn:hover {
        background: #dc3545;
        color: white;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
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
    
    .btn-submit {
        background: #667eea;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }
    
    .btn-submit:hover {
        background: #5a67d8;
    }
    
    @media (max-width: 768px) {
        .categories-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2>Manage Categories</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="categories-container">
        <div class="add-category-form">
            <h3>Add New Category</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Administrative, Staff, etc.">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Optional description"></textarea>
                </div>
                
                <button type="submit" name="add_category" class="btn-submit">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </form>
        </div>
        
        <div class="categories-list">
            <div style="padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee;">
                <h3 style="margin: 0;">Categories</h3>
            </div>
            
            <?php if ($categories->num_rows > 0): ?>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <div class="category-item">
                        <div class="category-info">
                            <h4><?php echo htmlspecialchars($cat['name']); ?></h4>
                            <?php if ($cat['description']): ?>
                                <p><?php echo htmlspecialchars($cat['description']); ?></p>
                            <?php endif; ?>
                            <div class="category-stats">
                                <i class="fas fa-folder"></i> <?php echo $cat['folder_count']; ?> folders
                            </div>
                        </div>
                        <button onclick="deleteCategory(<?php echo $cat['id']; ?>)" class="delete-btn" 
                                title="Delete Category">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #999;">
                    <i class="fas fa-tags" style="font-size: 48px;"></i>
                    <p>No categories yet. Create your first category!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteCategory(id) {
    if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
        window.location.href = 'index.php?delete=' + id;
    }
}
</script>

<?php
include_once '../../includes/footer.php';
?>