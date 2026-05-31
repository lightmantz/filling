<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$page_title = 'All Documents';
$base_url = '../../';

$conn = getConnection();

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
    
    .export-buttons {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
</style>

<div class="content-wrapper">
    <h2>All Documents</h2>
    
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
    
    <div class="export-buttons">
        <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export to CSV
        </a>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $doc['document_number']; ?></                            <td><?php echo $doc['document_number']; ?></td>
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
                            <td>
                                <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn btn-small btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($doc['status'] !== 'archived'): ?>
                                    <a href="archive.php?id=<?php echo $doc['id']; ?>" class="btn btn-small btn-warning" 
                                       onclick="return confirm('Archive this document?')">
                                        <i class="fas fa-archive"></i>
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

    <?php
    include_once '../../includes/footer.php';
    ?>