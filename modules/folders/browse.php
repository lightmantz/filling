<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Browse Folders';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Get all folders with search
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT f.*, c.name as category_name,
          COUNT(d.id) as document_count
          FROM folders f
          LEFT JOIN categories c ON f.category_id = c.id
          LEFT JOIN documents d ON f.id = d.folder_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (f.name LIKE ? OR f.folder_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($category)) {
    $query .= " AND f.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

$query .= " GROUP BY f.id ORDER BY f.name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$folders = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .search-filter {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .filter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-form input,
    .filter-form select {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .folder-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .folder-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .folder-icon {
        font-size: 40px;
        color: #667eea;
        margin-bottom: 10px;
    }
    
    .folder-card h3 {
        margin: 0 0 10px 0;
        color: #333;
    }
    
    .folder-stats {
        margin: 15px 0;
        font-size: 14px;
        color: #666;
    }
    
    .folder-stats p {
        margin: 5px 0;
    }
</style>

<div class="content-wrapper">
    <h2>Browse Folders</h2>
    
    <div class="search-filter">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search folders..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (!empty($search) || !empty($category)): ?>
                <a href="browse.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="folders-grid">
        <?php if ($folders->num_rows > 0): ?>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <div class="folder-card">
                    <div class="folder-icon">
                        <i class="fas fa-folder-<?php echo $folder['is_confidential'] ? 'lock' : 'open'; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($folder['name']); ?></h3>
                    <div class="folder-stats">
                        <p><i class="fas fa-hashtag"></i> <?php echo $folder['folder_number']; ?></p>
                        <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name']); ?></p>
                        <p><i class="fas fa-file"></i> <?php echo $folder['document_count']; ?> documents</p>
                    </div>
                    <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-primary btn-block">
                        <i class="fas fa-eye"></i> Open Folder
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 50px;">
                <i class="fas fa-folder-open" style="font-size: 64px; color: #ccc;"></i>
                <p>No folders found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>