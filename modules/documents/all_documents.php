<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'All Documents';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Allow records_officer AND super_admin
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

// Handle document deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $doc_id = (int)$_GET['id'];
    
    // Check if user has permission to delete
    if ($user_role === 'records_officer' || $user_role === 'super_admin') {
        // Get document info for logging
        $doc_query = "SELECT title, document_number FROM documents WHERE id = ?";
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param("i", $doc_id);
        $doc_stmt->execute();
        $doc_info = $doc_stmt->get_result()->fetch_assoc();
        
        // Delete the document
        $delete_query = "DELETE FROM documents WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $doc_id);
        
        if ($delete_stmt->execute()) {
            auditLog($user_id, 'delete_document', "Deleted document: {$doc_info['document_number']} - {$doc_info['title']}");
            $_SESSION['flash_message'] = "Document deleted successfully!";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to delete document.";
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    header("Location: all_documents.php");
    exit();
}

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = $_GET['status'] ?? '';
$folder_filter = $_GET['folder'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM documents d WHERE 1=1";
$params = [];
$types = "";

if (!empty($status_filter)) {
    $query .= " AND d.status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($folder_filter)) {
    $query .= " AND d.folder_id = ?";
    $count_query .= " AND folder_id = ?";
    $params[] = $folder_filter;
    $types .= "i";
}

if (!empty($date_from)) {
    $query .= " AND DATE(d.created_at) >= ?";
    $count_query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(d.created_at) <= ?";
    $count_query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result();

// Get total count for pagination
$count_stmt = $conn->prepare($count_query);
if (!empty($params) && count($params) > 2) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Get folders for filter
$folders = $conn->query("SELECT id, name FROM folders ORDER BY name");

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
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        color: #666;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .data-table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .data-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-submitted { background-color: #3498db; color: white; }
    .status-in_review { background-color: #f39c12; color: white; }
    .status-approved { background-color: #27ae60; color: white; }
    .status-rejected { background-color: #e74c3c; color: white; }
    .status-closed { background-color: #2c3e50; color: white; }
    .status-archived { background-color: #95a5a6; color: white; }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 12px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #667eea;
    }
    
    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .btn-small {
        display: inline-block;
        padding: 5px 12px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .btn-view {
        background-color: #3498db;
        color: white;
    }
    
    .btn-view:hover {
        background-color: #2980b9;
    }
    
    .btn-edit {
        background-color: #ff9800;
        color: white;
    }
    
    .btn-edit:hover {
        background-color: #e68900;
    }
    
    .btn-delete {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-delete:hover {
        background-color: #c82333;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
        padding: 50px;
        background: white;
        border-radius: 8px;
    }
    
    @media (max-width: 768px) {
        .data-table {
            display: block;
            overflow-x: auto;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-file-alt"></i> All Documents</h2>
    
    <div class="filters-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="in_review" <?php echo $status_filter == 'in_review' ? 'selected' : ''; ?>>In Review</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Folder</label>
                <select name="folder">
                    <option value="">All Folders</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <option value="<?php echo $folder['id']; ?>" <?php echo $folder_filter == $folder['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($folder['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="all_documents.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    
    <?php if ($documents->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Document #</th>
                    <th>Title</th>
                    <th>Folder</th>
                    <th>Folio</th>
                    <th>Submitted By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $doc['document_number']; ?></td>
                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td><?php echo htmlspecialchars($doc['folder_name']); ?></td>
                        <td><?php echo $doc['folio_number']; ?></td>
                        <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $doc['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                        <td class="action-buttons">
                            <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn-small btn-view" title="View Document">
                                <i class="fas fa-eye"></i> View
                            </a>
                            
                            <?php if ($user_role === 'records_officer' || $user_role === 'super_admin'): ?>
                                <a href="edit_document.php?id=<?php echo $doc['id']; ?>" class="btn-small btn-edit" title="Edit Document">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="all_documents.php?delete=1&id=<?php echo $doc['id']; ?>" 
                                   class="btn-small btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this document? This action cannot be undone.')"
                                   title="Delete Document">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt" style="font-size: 64px; color: #ccc;"></i>
            <p>No documents found.</p>
        </div>
    <?php endif; ?>
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