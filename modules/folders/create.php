<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$conn = getConnection();
$error = '';
$success = '';

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Generate folder number
$year = date('Y');
$count_query = "SELECT COUNT(*) as count FROM folders WHERE YEAR(created_at) = $year";
$count_result = $conn->query($count_query);
$count = $count_result->fetch_assoc()['count'] + 1;
$folder_number = sprintf("FLD-%s-%04d", $year, $count);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    $created_by = $_SESSION['user_id'];
    
    $query = "INSERT INTO folders (folder_number, name, category_id, description, is_confidential, created_by) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssissi", $folder_number, $name, $category_id, $description, $is_confidential, $created_by);
    
    if ($stmt->execute()) {
        $success = "Folder created successfully!";
        // Generate new folder number for next folder
        $count++;
        $folder_number = sprintf("FLD-%s-%04d", $year, $count);
    } else {
        $error = "Failed to create folder. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Folder - Filing System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Filing Management System</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../modules/auth/logout.php" class="btn btn-small">Logout</a>
        </div>
    </div>
    
    <div class="nav">
        <a href="../users/records_dashboard.php">Dashboard</a>
        <a href="index.php">Folders</a>
        <a href="create.php">Create Folder</a>
    </div>
    
    <div class="container">
        <h2>Create New Folder</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Folder Number</label>
                    <input type="text" value="<?php echo $folder_number; ?>" disabled class="disabled-input">
                    <small>Auto-generated</small>
                </div>
                
                <div class="form-group">
                    <label>Folder Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_confidential">
                        Mark as Confidential Folder
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Folder</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .form-container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .disabled-input {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</body>
</html>