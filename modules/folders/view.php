<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'View Folder';
$base_url = '../../';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getConnection();
$folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($folder_id <= 0) {
    die("Invalid folder ID");
}

// Get folder details
$folder_query = "SELECT f.*, c.name as category_name 
                 FROM folders f
                 LEFT JOIN categories c ON f.category_id = c.id
                 WHERE f.id = ?";
$stmt = $conn->prepare($folder_query);
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$folder = $stmt->get_result()->fetch_assoc();

if (!$folder) {
    die("Folder not found");
}

// Get documents in this folder (newest first)
$documents_query = "SELECT d.*, u.full_name as submitted_by_name
                    FROM documents d
                    LEFT JOIN users u ON d.submitted_by = u.id
                    WHERE d.folder_id = ?
                    ORDER BY d.folio_number DESC";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$documents = $stmt->get_result();

$user_role = $_SESSION['user_role'];

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .folder-header {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .folder-info h2 {
        margin: 0 0 10px 0;
    }
    
    .folder-info p {
        margin: 8px 0;
    }
    
    .folder-stats {
        text-align: right;
    }
    
    .folder-stats .stat {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 18px;
    }
    
    .documents-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
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
    
    .status-draft { background-color: #95a5a6; color: white; }
    .status-submitted { background-color: #3498db; color: white; }
    .status-in_review { background-color: #f39c12; color: white; }
    .status-approved { background-color: #27ae60; color: white; }
    .status-rejected { background-color: #e74c3c; color: white; }
    .status-closed { background-color: #2c3e50; color: white; }
    
    .btn-small {
        display: inline-block;
        padding: 4px 10px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        background-color: #3498db;
        color: white;
    }
    
    .btn-small:hover {
        background-color: #2980b9;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
    }
    
    .add-document-btn {
        margin-bottom: 20px;
        text-align: right;
    }
    
    .btn-primary {
        display: inline-block;
        padding: 10px 20px;
        background-color: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 5px;
    }
    
    .confidential-badge {
        background: #f44336;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        margin-left: 10px;
    }
</style>

<div class="content-wrapper">
    <div class="folder-header">
        <div class="folder-info">
            <h2>
                <i class="fas fa-folder-<?php echo $folder['is_confidential'] ? 'lock' : 'open'; ?>"></i>
                <?php echo htmlspecialchars($folder['name']); ?>
                <?php if ($folder['is_confidential']): ?>
                    <span class="confidential-badge">
                        <i class="fas fa-lock"></i> Confidential
                    </span>
                <?php endif; ?>
            </h2>
            <div class="folder-details">
                <p><i class="fas fa-hashtag"></i> <strong>Folder Number:</strong> <?php echo $folder['folder_number']; ?></p>
                <p><i class="fas fa-tag"></i> <strong>Category:</strong> <?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></p>
                <p><i class="fas fa-chart-line"></i> <strong>Status:</strong> <?php echo ucfirst($folder['status']); ?></p>
                <?php if ($folder['description']): ?>
                    <p><i class="fas fa-info-circle"></i> <strong>Description:</strong> <?php echo nl2br(htmlspecialchars($folder['description'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="folder-stats">
            <div class="stat">
                <i class="fas fa-file-alt"></i> <?php echo $documents->num_rows; ?> Documents
            </div>
        </div>
    </div>
    
    <div class="documents-list">
        <div class="add-document-btn">
            <?php if ($user_role !== 'user' || ($user_role === 'user' && $folder['status'] === 'active')): ?>
                <a href="../documents/create.php?folder_id=<?php echo $folder_id; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Add New Document
                </a>
            <?php endif; ?>
        </div>
        
        <h3>Documents (Newest First)</h3>
        
        <?php if ($documents->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Folio #</th>
                        <th>Document Number</th>
                        <th>Title</th>
                        <th>Submitted By</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = $documents->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $doc['folio_number']; ?></td>
                            <td><?php echo $doc['document_number']; ?></td>
                            <td><?php echo htmlspecialchars($doc['title']); ?></td>
                            <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $doc['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td>
                                <a href="../documents/view.php?id=<?php echo $doc['id']; ?>" class="btn-small">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt" style="font-size: 48px; color: #ccc;"></i>
                <p>No documents in this folder yet.</p>
                <a href="../documents/create.php?folder_id=<?php echo $folder_id; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Add First Document
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>