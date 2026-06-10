<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Archive Folders';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Check permission - only records officer and super admin can archive
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: index.php');
    exit();
}

// Handle archive action
if (isset($_GET['archive']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get folder name for logging
    $name_query = "SELECT name FROM folders WHERE id = ?";
    $name_stmt = $conn->prepare($name_query);
    $name_stmt->bind_param("i", $id);
    $name_stmt->execute();
    $folder_name = $name_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    
    $update = "UPDATE folders SET status = 'archived' WHERE id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        auditLog($_SESSION['user_id'], 'archive_folder', "Archived folder: $folder_name (ID: $id)");
        $_SESSION['flash_message'] = "Folder archived successfully!";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Failed to archive folder.";
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: archive.php");
    exit();
}

// Handle restore action
if (isset($_GET['restore']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get folder name for logging
    $name_query = "SELECT name FROM folders WHERE id = ?";
    $name_stmt = $conn->prepare($name_query);
    $name_stmt->bind_param("i", $id);
    $name_stmt->execute();
    $folder_name = $name_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    
    $update = "UPDATE folders SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        auditLog($_SESSION['user_id'], 'restore_folder', "Restored folder: $folder_name (ID: $id)");
        $_SESSION['flash_message'] = "Folder restored successfully!";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Failed to restore folder.";
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: archive.php");
    exit();
}

// Get archived folders - REMOVED updated_at reference
$archived_query = "SELECT f.*, c.name as category_name, 
                   COUNT(DISTINCT d.id) as document_count,
                   MAX(d.created_at) as last_activity
                   FROM folders f
                   LEFT JOIN categories c ON f.category_id = c.id
                   LEFT JOIN documents d ON f.id = d.folder_id
                   WHERE f.status = 'archived'
                   GROUP BY f.id
                   ORDER BY f.created_at DESC";
$archived_folders = $conn->query($archived_query);

// Get active folders for archiving - REMOVED updated_at reference
$active_query = "SELECT f.*, c.name as category_name, 
                 COUNT(DISTINCT d.id) as document_count,
                 MAX(d.created_at) as last_activity
                 FROM folders f
                 LEFT JOIN categories c ON f.category_id = c.id
                 LEFT JOIN documents d ON f.id = d.folder_id
                 WHERE f.status = 'active'
                 GROUP BY f.id
                 ORDER BY f.name ASC";
$active_folders = $conn->query($active_query);

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
    .archive-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    
    .folder-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .folder-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
    }
    
    .section-badge {
        background: #667eea;
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .folder-list {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .folder-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .folder-item:hover {
        background: #f9f9f9;
        transform: translateX(5px);
    }
    
    .folder-info {
        flex: 1;
    }
    
    .folder-info h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .folder-number {
        font-family: monospace;
        font-size: 11px;
        color: #667eea;
        background: #f0f4ff;
        padding: 2px 8px;
        border-radius: 4px;
    }
    
    .folder-meta {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #666;
        flex-wrap: wrap;
    }
    
    .folder-meta i {
        width: 14px;
        color: #667eea;
    }
    
    .confidential-badge {
        background: #f44336;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
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
    
    .btn-archive {
        background: #ff9800;
        color: white;
    }
    
    .btn-archive:hover {
        background: #e68900;
        transform: translateY(-2px);
    }
    
    .btn-restore {
        background: #27ae60;
        color: white;
    }
    
    .btn-restore:hover {
        background: #229954;
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: #3498db;
        color: white;
    }
    
    .btn-view:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    
    .btn-small {
        padding: 5px 12px;
        font-size: 12px;
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
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ddd;
    }
    
    .stats-summary {
        display: flex;
        justify-content: space-between;
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    
    .stats-summary span {
        font-weight: bold;
        color: #667eea;
    }
    
    @media (max-width: 900px) {
        .archive-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-archive"></i> Folder Archive Management</h2>
    
    <div class="archive-container">
        <!-- Active Folders Section -->
        <div class="folder-section">
            <h3>
                <i class="fas fa-folder-open" style="color: #27ae60;"></i>
                Active Folders
                <span class="section-badge"><?php echo $active_folders->num_rows; ?> folders</span>
            </h3>
            
            <div class="stats-summary">
                <span><i class="fas fa-chart-line"></i> Ready for archiving</span>
                <span>Total: <?php echo $active_folders->num_rows; ?> active folders</span>
            </div>
            
            <div class="folder-list">
                <?php if ($active_folders && $active_folders->num_rows > 0): ?>
                    <?php while ($folder = $active_folders->fetch_assoc()): ?>
                        <div class="folder-item">
                            <div class="folder-info">
                                <h4>
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                    <?php if ($folder['is_confidential']): ?>
                                        <span class="confidential-badge">
                                            <i class="fas fa-lock"></i> Confidential
                                        </span>
                                    <?php endif; ?>
                                    <span class="folder-number"><?php echo $folder['folder_number']; ?></span>
                                </h4>
                                <div class="folder-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></span>
                                    <span><i class="fas fa-file-alt"></i> <?php echo $folder['document_count']; ?> documents</span>
                                    <?php if ($folder['last_activity']): ?>
                                        <span><i class="fas fa-clock"></i> Last: <?php echo date('M d, Y', strtotime($folder['last_activity'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($folder['description']): ?>
                                    <div class="folder-meta" style="margin-top: 5px; font-size: 11px; color: #888;">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(substr($folder['description'], 0, 80)); ?>
                                        <?php echo strlen($folder['description']) > 80 ? '...' : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="folder-actions">
                                <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-view btn-small" title="View Folder">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="?archive=1&id=<?php echo $folder['id']; ?>" class="btn btn-archive btn-small" 
                                   onclick="return confirm('Archive this folder? All documents will remain accessible but the folder will be moved to archives.')" 
                                   title="Archive Folder">
                                    <i class="fas fa-archive"></i> Archive
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                        <p>No active folders to archive.</p>
                        <a href="create.php" class="btn btn-view btn-small">Create New Folder</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Archived Folders Section -->
        <div class="folder-section">
            <h3>
                <i class="fas fa-archive" style="color: #ff9800;"></i>
                Archived Folders
                <span class="section-badge"><?php echo $archived_folders->num_rows; ?> folders</span>
            </h3>
            
            <div class="stats-summary">
                <span><i class="fas fa-database"></i> In archive storage</span>
                <span>Total: <?php echo $archived_folders->num_rows; ?> archived folders</span>
            </div>
            
            <div class="folder-list">
                <?php if ($archived_folders && $archived_folders->num_rows > 0): ?>
                    <?php while ($folder = $archived_folders->fetch_assoc()): ?>
                        <div class="folder-item">
                            <div class="folder-info">
                                <h4>
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                    <?php if ($folder['is_confidential']): ?>
                                        <span class="confidential-badge">
                                            <i class="fas fa-lock"></i> Confidential
                                        </span>
                                    <?php endif; ?>
                                    <span class="folder-number"><?php echo $folder['folder_number']; ?></span>
                                </h4>
                                <div class="folder-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></span>
                                    <span><i class="fas fa-file-alt"></i> <?php echo $folder['document_count']; ?> documents</span>
                                    <?php if ($folder['last_activity']): ?>
                                        <span><i class="fas fa-clock"></i> Last: <?php echo date('M d, Y', strtotime($folder['last_activity'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($folder['description']): ?>
                                    <div class="folder-meta" style="margin-top: 5px; font-size: 11px; color: #888;">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(substr($folder['description'], 0, 80)); ?>
                                        <?php echo strlen($folder['description']) > 80 ? '...' : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="folder-actions">
                                <a href="view.php?id=<?php echo $folder['id']; ?>" class="btn btn-view btn-small" title="View Folder">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="?restore=1&id=<?php echo $folder['id']; ?>" class="btn btn-restore btn-small" 
                                   onclick="return confirm('Restore this folder? It will become active again.')" 
                                   title="Restore Folder">
                                    <i class="fas fa-undo"></i> Restore
                                </a>
                                <?php if ($folder['document_count'] == 0): ?>
                                    <a href="index.php?delete=<?php echo $folder['id']; ?>" class="btn btn-danger btn-small" 
                                       onclick="return confirm('Permanently delete this folder? This action cannot be undone.')" 
                                       title="Delete Permanently">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-archive" style="color: #ccc;"></i>
                        <p>No archived folders.</p>
                        <p style="font-size: 12px;">Archive folders to keep them for reference without cluttering active view.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Information Card -->
    <div style="margin-top: 30px; background: #f0f7ff; border: 1px solid #cce5ff; border-radius: 8px; padding: 15px;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <i class="fas fa-info-circle" style="font-size: 24px; color: #004085;"></i>
            <div style="flex: 1;">
                <strong style="color: #004085;">About Folder Archiving:</strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #004085;">
                    Archived folders are moved to storage and don't appear in the main folders list. 
                    Documents inside archived folders remain accessible. You can restore archived folders at any time.
                    Folders with no documents can be permanently deleted.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
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