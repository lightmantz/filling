<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'User Dashboard';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user's submitted documents - FIXED GROUP BY issue
$documents_query = "SELECT d.*, f.name as folder_name,
                    (SELECT w.action FROM workflow_tracking w 
                     WHERE w.document_id = d.id 
                     ORDER BY w.created_at DESC LIMIT 1) as last_action,
                    (SELECT w.created_at FROM workflow_tracking w 
                     WHERE w.document_id = d.id 
                     ORDER BY w.created_at DESC LIMIT 1) as last_action_date
                    FROM documents d
                    LEFT JOIN folders f ON d.folder_id = f.id
                    WHERE d.submitted_by = ?
                    ORDER BY d.created_at DESC
                    LIMIT 10";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_docs,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    COUNT(CASE WHEN status IN ('submitted', 'in_review', 'submitted_to_admin') THEN 1 END) as pending
    FROM documents WHERE submitted_by = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

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
    
    .document-timeline {
        position: relative;
        padding-left: 40px;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }
    
    .timeline-badge {
        position: absolute;
        left: -40px;
        top: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #ccc;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .timeline-badge.status-submitted { background: #3498db; }
    .timeline-badge.status-in_review { background: #f39c12; }
    .timeline-badge.status-approved { background: #27ae60; }
    .timeline-badge.status-rejected { background: #e74c3c; }
    .timeline-badge.status-closed { background: #2c3e50; }
    
    .timeline-content {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }
    
    .timeline-content h4 {
        margin: 0 0 10px 0;
    }
    
    .timeline-content h4 a {
        color: #333;
        text-decoration: none;
    }
    
    .timeline-content h4 a:hover {
        color: #667eea;
    }
    
    .timeline-meta {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
    }
    
    .timeline-meta span {
        margin-right: 15px;
    }
    
    .timeline-status {
        margin-top: 10px;
    }
    
    .timeline-decision {
        margin-top: 10px;
        padding: 10px;
        background: #e8f5e9;
        border-radius: 5px;
    }
    
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
    
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
    }
</style>

<div class="content-wrapper">
    <h2>My Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Documents</h3>
            <div class="stat-number"><?php echo $stats['total_docs'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Approved</h3>
            <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Rejected</h3>
            <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending</h3>
            <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
        </div>
    </div>
    
    <div class="card">
        <h3><i class="fas fa-clock"></i> My Recent Submissions</h3>
        <?php if ($documents && $documents->num_rows > 0): ?>
            <div class="document-timeline">
                <?php while ($doc = $documents->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-badge status-<?php echo $doc['status']; ?>">
                            <?php echo strtoupper(substr($doc['status'], 0, 1)); ?>
                        </div>
                        <div class="timeline-content">
                            <h4>
                                <a href="../documents/view.php?id=<?php echo $doc['id']; ?>">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </a>
                            </h4>
                            <div class="timeline-meta">
                                <span><i class="fas fa-hashtag"></i> <?php echo $doc['document_number']; ?></span>
                                <span><i class="fas fa-sort-numeric-up"></i> Folio: <?php echo $doc['folio_number']; ?></span>
                                <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                            </div>
                            <div class="timeline-status">
                                Status: <span class="status-badge status-<?php echo $doc['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </div>
                            <?php if ($doc['status'] === 'approved' || $doc['status'] === 'rejected'): ?>
                                <div class="timeline-decision">
                                    <i class="fas fa-gavel"></i> <strong>Final Decision:</strong>
                                    <a href="../documents/view.php?id=<?php echo $doc['id']; ?>#comments">
                                        View comments and decision details
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>You haven't submitted any documents yet.</p>
                <a href="../documents/create.php" class="btn btn-primary">Submit Your First Document</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="quick-actions">
            <a href="../documents/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit New Document
            </a>
            <a href="../documents/my_documents.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> View All My Documents
            </a>
            <a href="../documents/status.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> Track Document Status
            </a>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>