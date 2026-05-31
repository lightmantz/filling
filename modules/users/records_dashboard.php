<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$page_title = 'Records Officer Dashboard';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_docs,
    COUNT(CASE WHEN status = 'in_review' THEN 1 END) as in_review_docs,
    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_docs,
    COUNT(CASE WHEN status = 'archived' THEN 1 END) as archived_docs
    FROM documents";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent documents
$recent_query = "SELECT d.*, f.name as folder_name 
                 FROM documents d
                 JOIN folders f ON d.folder_id = f.id
                 WHERE d.status NOT IN ('submitted_to_admin', 'in_review')
                 ORDER BY d.created_at DESC LIMIT 10";
$recent_docs = $conn->query($recent_query);

// Get folders
$folders_query = "SELECT f.*, c.name as category_name,
                  COUNT(d.id) as document_count
                  FROM folders f
                  LEFT JOIN categories c ON f.category_id = c.id
                  LEFT JOIN documents d ON f.id = d.folder_id
                  GROUP BY f.id
                  ORDER BY f.created_at DESC LIMIT 5";
$folders = $conn->query($folders_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        opacity: 0.9;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
    }
    
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    
    .document-list, .folder-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .document-item, .folder-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .document-item:hover, .folder-item:hover {
        background: #f9f9f9;
    }
    
    .doc-info, .folder-info {
        flex: 1;
    }
    
    .doc-meta, .folder-meta {
        font-size: 12px;
        color: #666;
        display: block;
        margin-top: 5px;
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
    
    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
    
    .btn-small {
        padding: 4px 10px;
        font-size: 12px;
    }
</style>

<div class="content-wrapper">
    <h2>Records Officer Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Pending Documents</h3>
            <div class="stat-number"><?php echo $stats['pending_docs']; ?></div>
        </div>
        <div class="stat-card">
            <h3>In Review</h3>
            <div class="stat-number"><?php echo $stats['in_review_docs']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Closed Documents</h3>
            <div class="stat-number"><?php echo $stats['closed_docs']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Archived</h3>
            <div class="stat-number"><?php echo $stats['archived_docs']; ?></div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="card">
            <h3><i class="fas fa-file-alt"></i> Recent Documents</h3>
            <div class="document-list">
                <?php if ($recent_docs->num_rows > 0): ?>
                    <?php while ($doc = $recent_docs->fetch_assoc()): ?>
                        <div class="document-item">
                            <div class="doc-info">
                                <a href="../documents/view.php?id=<?php echo $doc['id']; ?>">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </a>
                                <span class="doc-meta">
                                    <i class="fas fa-hashtag"></i> Folio: <?php echo $doc['folio_number']; ?> | 
                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?>
                                </span>
                            </div>
                            <span class="status-badge status-<?php echo $doc['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No documents found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-folder"></i> Recent Folders</h3>
            <div class="folder-list">
                <?php if ($folders->num_rows > 0): ?>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <div class="folder-item">
                            <div class="folder-info">
                                <strong><?php echo htmlspecialchars($folder['name']); ?></strong>
                                <span class="folder-meta">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name']); ?> | 
                                    <i class="fas fa-file"></i> <?php echo $folder['document_count']; ?> documents
                                </span>
                            </div>
                            <a href="../folders/view.php?id=<?php echo $folder['id']; ?>" class="btn btn-small btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No folders created yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="quick-actions">
            <a href="../documents/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit New Document
            </a>
            <a href="../folders/create.php" class="btn btn-primary">
                <i class="fas fa-folder-plus"></i> Create New Folder
            </a>
            <a href="../reports/index.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Generate Reports
            </a>
            <a href="../documents/pending_processing.php" class="btn btn-secondary">
                <i class="fas fa-clock"></i> Process Documents
            </a>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>