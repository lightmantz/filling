<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'All Folders';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Handle folder deletion
if (isset($_GET['delete']) && ($user_role === 'records_officer' || $user_role === 'super_admin')) {
    $folder_id = (int)$_GET['delete'];
    
    // Check if folder has documents
    $check_query = "SELECT COUNT(*) as count FROM documents WHERE folder_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $folder_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $doc_count = $result->fetch_assoc()['count'];
    
    if ($doc_count > 0) {
        $_SESSION['flash_message'] = "Cannot delete folder. It contains $doc_count document(s). Archive it instead.";
        $_SESSION['flash_type'] = 'error';
    } else {
        $delete_query = "DELETE FROM folders WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $folder_id);
        
        if ($delete_stmt->execute()) {
            auditLog($_SESSION['user_id'], 'delete_folder', "Deleted folder ID: $folder_id");
            $_SESSION['flash_message'] = "Folder deleted successfully!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to delete folder.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    header("Location: index.php");
    exit();
}

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

// Display flash message if exists
if (isset($_SESSION['flash_message'])) {
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    $flash_class = $flash_type === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $flash_class . '" id="flashMessage">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

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
    
    .search-filter {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .search-filter input,
    .search-filter select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .search-filter input {
        width: 250px;
    }
    
    .folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .folder-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
    }
    
    .folder-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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
        border-radius: 4px;
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
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background-color: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #2980b9;
        transform: translateY(-2px);
    }
    
    .btn-warning {
        background-color: #ff9800;
        color: white;
    }
    
    .btn-warning:hover {
        background-color: #e68900;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #c82333;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }
    
    .btn-small {
        padding: 5px 12px;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 12px;
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
    
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-active { background-color: #27ae60; color: white; }
    .status-archived { background-color: #95a5a6; color: white; }
    
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
    
    .document-count {
        display: inline-block;
        background: #667eea;
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        margin-left: 8px;
    }
    
    @media (max-width: 768px) {
        .folders-grid {
            grid-template-columns: 1fr;
        }
        
        .action-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-filter {
            flex-direction: column;
        }
        
        .search-filter input {
            width: 100%;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-folder"></i> All Folders</h2>
    
    <div class="action-bar">
        <?php if ($user_role === 'records_officer' || $user_role === 'super_admin'): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Folder
            </a>
        <?php endif; ?>
        
        <div class="search-filter">
            <input type="text" id="searchFolder" placeholder="Search folders..." onkeyup="filterFolders()">
            <select id="statusFilter" onchange="filterFolders()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
        </div>
    </div>
    
    <div class="folders-grid" id="foldersGrid">
        <?php if ($folders && $folders->num_rows > 0): ?>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <div class="folder-card" data-status="<?php echo $folder['status']; ?>" data-name="<?php echo strtolower(htmlspecialchars($folder['name'])); ?>">
                    <div class="folder-header">
                        <h3>
                            <i class="fas fa-folder-<?php echo $folder['is_confidential'] ? 'lock' : 'open'; ?>"></i>
                            <?php echo htmlspecialchars($folder['name']); ?>
                            <?php if ($folder['document_count'] > 0): ?>
                                <span class="document-count"><?php echo $folder['document_count']; ?> docs</span>
                            <?php endif; ?>
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
                        <?php if ($folder['last_activity']): ?>
                            <p><i class="fas fa-clock"></i> <strong>Last Activity:</strong> <?php echo date('M d, Y', strtotime($folder['last_activity'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($folder['description']): ?>
                        <div class="folder-stats">
                            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(substr($folder['description'], 0, 100)); ?>
                            <?php echo strlen($folder['description']) > 100 ? '...' : ''; ?>
                        </div>
                    <?php endif; ?>
                    <div class="folder-actions">
                        <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-primary btn-small" title="View Folder">
                            <i class="fas fa-eye"></i> View
                        </a>
                        
                        <?php if ($user_role === 'records_officer' || $user_role === 'super_admin'): ?>
                            <a href="edit.php?id=<?php echo $folder['id']; ?>" class="btn btn-secondary btn-small" title="Edit Folder">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        <?php endif; ?>
                        
                        <?php if (($user_role === 'records_officer' || $user_role === 'super_admin') && $folder['status'] === 'active'): ?>
                            <a href="archive.php?archive=1&id=<?php echo $folder['id']; ?>" class="btn btn-warning btn-small" 
                               onclick="return confirm('Archive this folder?')" title="Archive Folder">
                                <i class="fas fa-archive"></i> Archive
                            </a>
                        <?php endif; ?>
                        
                        <?php if (($user_role === 'records_officer' || $user_role === 'super_admin') && $folder['document_count'] == 0 && $folder['status'] !== 'archived'): ?>
                            <a href="index.php?delete=<?php echo $folder['id']; ?>" class="btn btn-danger btn-small" 
                               onclick="return confirm('Are you sure you want to delete this folder? This action cannot be undone.')" title="Delete Folder">
                                <i class="fas fa-trash"></i> Delete
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
                <?php if ($user_role === 'records_officer' || $user_role === 'super_admin'): ?>
                    <a href="create.php" class="btn btn-primary">Create Your First Folder</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterFolders() {
    const searchInput = document.getElementById('searchFolder');
    const searchTerm = searchInput.value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const folders = document.querySelectorAll('.folder-card');
    
    folders.forEach(folder => {
        const folderName = folder.getAttribute('data-name') || '';
        const folderStatus = folder.getAttribute('data-status') || '';
        
        const matchesSearch = folderName.includes(searchTerm);
        const matchesStatus = !statusFilter || folderStatus === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            folder.style.display = '';
        } else {
            folder.style.display = 'none';
        }
    });
}

// Auto-hide flash message after 5 seconds
setTimeout(function() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        flashMessage.style.transition = 'opacity 0.5s';
        flashMessage.style.opacity = '0';
        setTimeout(function() {
            if (flashMessage) flashMessage.remove();
        }, 500);
    }
}, 5000);
</script>

<?php
include_once '../../includes/footer.php';
?>