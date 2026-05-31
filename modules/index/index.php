<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'File Index Management';
$base_url = '../../';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Only records officer can access full index management
if ($user_role !== 'records_officer') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT f.id) as total_folders,
    COUNT(DISTINCT d.id) as total_documents,
    COUNT(DISTINCT CASE WHEN d.status = 'approved' THEN d.id END) as approved_docs,
    COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_docs
    FROM folders f
    LEFT JOIN documents d ON f.id = d.folder_id";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get all folders with document counts for index
$folders_query = "SELECT f.*, c.name as category_name,
                  COUNT(DISTINCT d.id) as document_count,
                  MAX(d.created_at) as last_activity,
                  COUNT(DISTINCT CASE WHEN d.status = 'approved' THEN d.id END) as approved_count,
                  COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_count
                  FROM folders f
                  LEFT JOIN categories c ON f.category_id = c.id
                  LEFT JOIN documents d ON f.id = d.folder_id
                  GROUP BY f.id
                  ORDER BY f.folder_number ASC";
$folders = $conn->query($folders_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .index-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-box h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        opacity: 0.9;
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: bold;
    }
    
    .index-controls {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }
    
    .search-box {
        flex: 1;
        max-width: 300px;
    }
    
    .search-box input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    
    .filter-box select {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
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
    
    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #7f8c8d;
    }
    
    .btn-success {
        background-color: #27ae60;
        color: white;
    }
    
    .btn-success:hover {
        background-color: #229954;
    }
    
    .index-table {
        background: white;
        border-radius: 8px;
        overflow-x: auto;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    th {
        background: #f8f9fa;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }
    
    th:hover {
        background: #e9ecef;
    }
    
    tr:hover {
        background: #f9f9f9;
    }
    
    .folder-link {
        color: #3498db;
        text-decoration: none;
        font-weight: 500;
    }
    
    .folder-link:hover {
        text-decoration: underline;
    }
    
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .badge-active {
        background: #27ae60;
        color: white;
    }
    
    .badge-archived {
        background: #95a5a6;
        color: white;
    }
    
    .badge-confidential {
        background: #f44336;
        color: white;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
        padding: 20px;
    }
    
    .pagination a, .pagination span {
        padding: 8px 12px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #3498db;
    }
    
    .pagination .active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }
    
    .index-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        padding: 4px 8px;
        font-size: 12px;
    }
    
    .print-area {
        display: none;
    }
    
    @media print {
        .sidebar, .top-header, .index-controls, .pagination, .footer, .nav, .action-bar {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .content-wrapper {
            padding: 0 !important;
        }
        .index-table {
            box-shadow: none;
        }
        .print-area {
            display: block;
            text-align: center;
            margin-bottom: 20px;
        }
    }
</style>

<div class="content-wrapper">
    <div class="print-area">
        <h2>File Index Report</h2>
        <p>Generated on: <?php echo date('F d, Y H:i:s'); ?></p>
        <hr>
    </div>
    
    <h2><i class="fas fa-index"></i> File Index Management</h2>
    
    <div class="index-stats">
        <div class="stat-box">
            <h3>Total Folders</h3>
            <div class="stat-number"><?php echo $stats['total_folders'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <h3>Total Documents</h3>
            <div class="stat-number"><?php echo $stats['total_documents'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <h3>Approved Documents</h3>
            <div class="stat-number"><?php echo $stats['approved_docs'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <h3>Pending Documents</h3>
            <div class="stat-number"><?php echo $stats['pending_docs'] ?? 0; ?></div>
        </div>
    </div>
    
    <div class="index-controls">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by folder name, number, or category..." onkeyup="filterTable()">
        </div>
        <div class="filter-box">
            <select id="statusFilter" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <div class="filter-box">
            <select id="categoryFilter" onchange="filterTable()">
                <option value="">All Categories</option>
                <?php
                $categories = $conn->query("SELECT DISTINCT name FROM categories");
                while ($cat = $categories->fetch_assoc()):
                ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="export-buttons">
            <button onclick="exportToCSV()" class="btn btn-success">
                <i class="fas fa-download"></i> Export CSV
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <div class="index-table">
        <table id="indexTable">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">Folder # <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable(1)">Folder Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable(2)">Category <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable(3)">Documents <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable(4)">Status <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable(5)">Last Activity <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($folders && $folders->num_rows > 0): ?>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <tr data-status="<?php echo $folder['status']; ?>" 
                            data-category="<?php echo htmlspecialchars($folder['category_name'] ?? ''); ?>">
                            <td><?php echo htmlspecialchars($folder['folder_number']); ?></td>
                            <td>
                                <a href="../folders/view.php?id=<?php echo $folder['id']; ?>" class="folder-link">
                                    <i class="fas fa-folder-<?php echo $folder['is_confidential'] ? 'lock' : 'open'; ?>"></i>
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                    <?php if ($folder['is_confidential']): ?>
                                        <span class="badge badge-confidential">Confidential</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <?php echo $folder['document_count']; ?>
                                <?php if ($folder['pending_count'] > 0): ?>
                                    <span class="badge" style="background:#ff9800; color:white;"><?php echo $folder['pending_count']; ?> pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $folder['status']; ?>">
                                    <?php echo ucfirst($folder['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $folder['last_activity'] ? date('M d, Y', strtotime($folder['last_activity'])) : 'No activity'; ?></td>
                            <td class="index-actions">
                                <a href="../folders/view.php?id=<?php echo $folder['id']; ?>" class="btn btn-primary btn-icon" title="View Folder">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick="generateIndexCard(<?php echo $folder['id']; ?>)" class="btn btn-secondary btn-icon" title="Generate Index Card">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc;"></i>
                            <p>No folders found in the system.</p>
                            <a href="../folders/create.php" class="btn btn-primary">Create First Folder</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable() {
    let searchInput = document.getElementById('searchInput').value.toUpperCase();
    let statusFilter = document.getElementById('statusFilter').value;
    let categoryFilter = document.getElementById('categoryFilter').value;
    let table = document.getElementById('indexTable');
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td');
        if (td.length > 0) {
            let folderName = td[1]?.textContent || td[1]?.innerText || '';
            let folderNumber = td[0]?.textContent || '';
            let category = td[2]?.textContent || '';
            let status = tr[i].getAttribute('data-status') || '';
            let rowCategory = tr[i].getAttribute('data-category') || '';
            
            let matchesSearch = folderName.toUpperCase().indexOf(searchInput) > -1 || 
                               folderNumber.toUpperCase().indexOf(searchInput) > -1;
            let matchesStatus = !statusFilter || status === statusFilter;
            let matchesCategory = !categoryFilter || rowCategory === categoryFilter;
            
            if (matchesSearch && matchesStatus && matchesCategory) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }
}

let sortDirection = {};
function sortTable(columnIndex) {
    let table = document.getElementById('indexTable');
    let tbody = table.getElementsByTagName('tbody')[0];
    let rows = Array.from(tbody.getElementsByTagName('tr'));
    
    sortDirection[columnIndex] = !sortDirection[columnIndex];
    let direction = sortDirection[columnIndex] ? 1 : -1;
    
    rows.sort((a, b) => {
        let aValue = a.getElementsByTagName('td')[columnIndex]?.textContent || '';
        let bValue = b.getElementsByTagName('td')[columnIndex]?.textContent || '';
        
        if (columnIndex === 3) { // Documents count
            aValue = parseInt(aValue) || 0;
            bValue = parseInt(bValue) || 0;
        }
        
        if (aValue < bValue) return -direction;
        if (aValue > bValue) return direction;
        return 0;
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function exportToCSV() {
    let table = document.getElementById('indexTable');
    let rows = table.getElementsByTagName('tr');
    let csv = [];
    
    // Get headers
    let headers = [];
    let headerCells = rows[0].getElementsByTagName('th');
    for (let i = 0; i < headerCells.length - 1; i++) {
        headers.push(headerCells[i].textContent.replace('↓↑', '').trim());
    }
    csv.push(headers.join(','));
    
    // Get data
    for (let i = 1; i < rows.length; i++) {
        if (rows[i].style.display !== 'none') {
            let row = [];
            let cells = rows[i].getElementsByTagName('td');
            for (let j = 0; j < cells.length - 1; j++) {
                let text = cells[j].textContent.trim();
                // Remove extra spaces and quotes
                text = text.replace(/"/g, '""');
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    text = '"' + text + '"';
                }
                row.push(text);
            }
            csv.push(row.join(','));
        }
    }
    
    // Download
    let blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement('a');
    let url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'file_index_' + new Date().toISOString().slice(0,19) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function generateIndexCard(folderId) {
    window.open('index_card.php?id=' + folderId, '_blank', 'width=800,height=600');
}
</script>

<?php
include_once '../../includes/footer.php';
?>