<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$page_title = 'Archived Documents';
$base_url = '../../';

$conn = getConnection();

$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE d.status = 'archived'
          ORDER BY d.updated_at DESC";
$archived_docs = $conn->query($query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
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
        background-color: #7f8c8d;
        color: white;
    }
    
    .btn-restore {
        display: inline-block;
        padding: 4px 10px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        background-color: #27ae60;
        color: white;
    }
    
    .btn-restore:hover {
        background-color: #229954;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 8px;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-archive"></i> Archived Documents</h2>
    
    <?php if ($archived_docs->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Document #</th>
                    <th>Title</th>
                    <th>Folder</th>
                    <th>Submitted By</th>
                    <th>Archived Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $archived_docs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $doc['document_number']; ?></td>
                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td><?php echo htmlspecialchars($doc['folder_name']); ?></td>
                        <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($doc['updated_at'])); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn-small">View</a>
                            <a href="restore.php?id=<?php echo $doc['id']; ?>" class="btn-restore" 
                               onclick="return confirm('Restore this document?')">
                                <i class="fas fa-undo"></i> Restore
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-archive" style="font-size: 64px; color: #ccc;"></i>
            <p>No archived documents found.</p>
            <a href="all_documents.php" class="btn btn-primary">View Active Documents</a>
        </div>
    <?php endif; ?>
</div>

<?php
include_once '../../includes/footer.php';
?>