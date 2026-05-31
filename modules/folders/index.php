<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'All Folders';
$base_url = '../../';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Get all folders with document counts
$query = "SELECT f.*, c.name as category_name,
          COUNT(DISTINCT d.id) as document_count,
          MAX(d.created_at) as last_activity
          FROM folders f
          LEFT JOIN categories c ON f.category_id = c.id
          LEFT JOIN documents d ON f.id = d.folder_id
          GROUP BY f.id
          ORDER BY f.created_at DESC";

$result = $conn->query($query);

if (!$result) {
    die("Query Error: " . $conn->error);
}

$folders = $result;

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .action-bar {
        margin-bottom: 20px;
    }
    
    .folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .folder-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .folder-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .folder-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .folder-header h3 {
        margin: 0;
        color: #333;
        font-size: 18px;
    }
    
    .confidential-badge {
        background: #f44336;
        color: white;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .folder-details {
        margin-bottom: 15px;
    }
    
    .folder-details p {
        margin: 8px 0;
        font-size: 14px;
    }
    
    .folder-details i {
        width: 20px;
        color: #667eea;
    }
    
    .folder-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
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
        background-color: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #2980b9;
    }
    
    .btn-warning {
        background-color: #ff9800;
        color: white;
    }
    
    .btn-warning:hover {
        background-color: #e68900;
    }
    
    .btn-small {
        padding: 4px 10px;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 8px;
        grid-column: 1 / -1;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #ccc;
        margin-bottom: 15px;
    }
    
    .folder-stats {
        font-size: 12px;
        color: #666;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #f0f0f0;
    }
</style>

<div class="content-wrapper">
    <h2>All Folders</h2>
    
    <?php if ($user_role === 'records_officer'): ?>
        <div class="action-bar">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Folder
            </a>
        </div>
    <?php endif; ?>
    
    <div class="folders-grid">
        <?php if ($folders && $folders->num_rows > 0): ?>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <div class="folder-card">
                    <div class="folder-header">
                        <h3>
                            <i class="fas fa-folder-<?php echo $folder['is_confidential'] ? 'lock' : 'open'; ?>"></i>
                            <?php echo htmlspecialchars($folder['name']); ?>
                        </h3>
                        <?php if ($folder['is_confidential']): ?>
                            <span class="confidential-badge">
                                <i class="fas fa-lock"></i> Confidential
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="folder-details">
                        <p><i class="fas fa-hashtag"></i> <strong>Folder #:</strong> <?php echo htmlspecialchars($folder['folder_number']); ?></p>
                        <p><i class="fas fa-tag"></i> <strong>Category:</strong> <?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></p>
                        <p><i class="fas fa-file-alt"></i> <strong>Documents:</strong> <?php echo $folder['document_count']; ?></p>
                        <p><i class="fas fa-chart-line"></i> <strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $folder['status']; ?>">
                                <?php echo ucfirst($folder['status']); ?>
                            </span>
                        </p>
                    </div>
                    <?php if ($folder['description']): ?>
                        <div class="folder-stats">
                            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(substr($folder['description'], 0, 100)); ?>
                            <?php echo strlen($folder['description']) > 100 ? '...' : ''; ?>
                        </div>
                    <?php endif; ?>
                    <div class="folder-actions">
                        <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-primary btn-small">
                            <i class="fas fa-eye"></i> View Folder
                        </a>
                        <?php if ($user_role === 'records_officer' && $folder['status'] === 'active'): ?>
                            <a href="archive.php?archive=1&id=<?php echo $folder['id']; ?>" class="btn btn-warning btn-small" 
                               onclick="return confirm('Archive this folder?')">
                                <i class="fas fa-archive"></i> Archive
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Folders Found</h3>
                <p>There are no folders in the system yet.</p>
                <?php if ($user_role === 'records_officer'): ?>
                    <a href="create.php" class="btn btn-primary">Create Your First Folder</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>