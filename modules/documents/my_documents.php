<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'My Documents';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user's documents
$query = "SELECT d.*, f.name as folder_name
          FROM documents d
          LEFT JOIN folders f ON d.folder_id = f.id
          WHERE d.submitted_by = ?
          ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .search-bar {
        margin-bottom: 20px;
    }
    
    .search-bar input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
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
        background: white;
        border-radius: 8px;
    }
    
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        margin-left: 5px;
    }
    
    .badge.closed {
        background: #2c3e50;
        color: white;
    }
</style>

<div class="content-wrapper">
    <h2>My Submitted Documents</h2>
    
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search documents by title, number, or folder..." onkeyup="searchDocuments()">
    </div>
    
    <?php if ($documents->num_rows > 0): ?>
        <table class="data-table" id="documentsTable">
            <thead>
                <tr>
                    <th>Document #</th>
                    <th>Title</th>
                    <th>Folder</th>
                    <th>Folio</th>
                    <th>Status</th>
                    <th>Submitted Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $doc['document_number']; ?></td>
                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td><?php echo htmlspecialchars($doc['folder_name']); ?></td>
                        <td><?php echo $doc['folio_number']; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $doc['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($doc['created_at'])); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn-small">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if ($doc['status'] === 'closed'): ?>
                                <span class="badge closed">Closed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt" style="font-size: 64px; color: #ccc;"></i>
            <p>You haven't submitted any documents yet.</p>
            <a href="create.php" class="btn btn-primary">Submit Your First Document</a>
        </div>
    <?php endif; ?>
</div>

<script>
function searchDocuments() {
    let input = document.getElementById('searchInput');
    let filter = input.value.toUpperCase();
    let table = document.getElementById('documentsTable');
    if (!table) return;
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < td.length - 1; j++) {
            if (td[j]) {
                let txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        tr[i].style.display = found ? '' : 'none';
    }
}
</script>

<?php
include_once '../../includes/footer.php';
?>