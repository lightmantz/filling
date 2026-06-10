<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Document Tracking History';
$base_url = '../../';

$conn = getConnection();
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// If no document ID, show list of tracked documents
if ($document_id <= 0) {
    // Get documents for the user based on role
    if ($user_role === 'super_admin' || $user_role === 'records_officer') {
        $query = "SELECT d.id, d.document_number, d.title, d.status, d.created_at,
                  f.name as folder_name,
                  (SELECT COUNT(*) FROM workflow_tracking WHERE document_id = d.id) as activity_count,
                  (SELECT MAX(created_at) FROM workflow_tracking WHERE document_id = d.id) as last_activity
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $documents = $conn->query($query);
    } elseif ($user_role === 'admin') {
        $query = "SELECT d.id, d.document_number, d.title, d.status, d.created_at,
                  f.name as folder_name,
                  (SELECT COUNT(*) FROM workflow_tracking WHERE document_id = d.id) as activity_count,
                  (SELECT MAX(created_at) FROM workflow_tracking WHERE document_id = d.id) as last_activity
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  WHERE d.current_holder = ? OR d.status = 'submitted_to_admin'
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $documents = $stmt->get_result();
    } else {
        $query = "SELECT d.id, d.document_number, d.title, d.status, d.created_at,
                  f.name as folder_name,
                  (SELECT COUNT(*) FROM workflow_tracking WHERE document_id = d.id) as activity_count,
                  (SELECT MAX(created_at) FROM workflow_tracking WHERE document_id = d.id) as last_activity
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  WHERE d.submitted_by = ?
                  ORDER BY d.created_at DESC
                  LIMIT 50";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $documents = $stmt->get_result();
    }
    
    include_once '../../includes/header.php';
    include_once '../../includes/sidebar.php';
    ?>
    
    <style>
        .tracking-list {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .document-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .document-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #667eea;
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .document-number {
            font-family: monospace;
            font-size: 13px;
            color: #667eea;
            background: #f0f4ff;
            padding: 4px 10px;
            border-radius: 5px;
        }
        
        .document-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .activity-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-submitted { background: #3498db; color: white; }
        .status-in_review { background: #f39c12; color: white; }
        .status-approved { background: #27ae60; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        .status-closed { background: #2c3e50; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .document-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
    
    <div class="content-wrapper">
        <h2><i class="fas fa-history"></i> Document Tracking History</h2>
        
        <div class="tracking-list">
            <div class="search-box">
                <input type="text" id="searchDocument" placeholder="Search by document number, title, or folder..." onkeyup="searchDocuments()">
            </div>
            
            <div id="documentsList">
                <?php if ($documents && $documents->num_rows > 0): ?>
                    <?php while ($doc = $documents->fetch_assoc()): ?>
                        <div class="document-card" onclick="viewTracking(<?php echo $doc['id']; ?>)">
                            <div class="document-header">
                                <span class="document-title"><?php echo htmlspecialchars($doc['title']); ?></span>
                                <span class="document-number"><?php echo $doc['document_number']; ?></span>
                            </div>
                            <div class="document-meta">
                                <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                                <span class="activity-badge"><i class="fas fa-tasks"></i> <?php echo $doc['activity_count']; ?> activities</span>
                                <?php if ($doc['last_activity']): ?>
                                    <span class="activity-badge"><i class="fas fa-clock"></i> Last: <?php echo date('M d, Y', strtotime($doc['last_activity'])); ?></span>
                                <?php endif; ?>
                                <span class="status-badge status-<?php echo $doc['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No documents found to track.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function viewTracking(documentId) {
        window.location.href = 'history.php?id=' + documentId;
    }
    
    function searchDocuments() {
        let input = document.getElementById('searchDocument');
        let filter = input.value.toUpperCase();
        let cards = document.getElementsByClassName('document-card');
        
        for (let i = 0; i < cards.length; i++) {
            let title = cards[i].querySelector('.document-title')?.textContent || '';
            let number = cards[i].querySelector('.document-number')?.textContent || '';
            let folder = cards[i].querySelector('.document-meta')?.textContent || '';
            
            if (title.toUpperCase().indexOf(filter) > -1 || 
                number.toUpperCase().indexOf(filter) > -1 ||
                folder.toUpperCase().indexOf(filter) > -1) {
                cards[i].style.display = '';
            } else {
                cards[i].style.display = 'none';
            }
        }
    }
    </script>
    
    <?php
    include_once '../../includes/footer.php';
    exit();
}

// If document ID is provided, show detailed tracking history
$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE d.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();

if (!$document) {
    die("Document not found");
}

// Get workflow tracking with user details
$workflow_query = "SELECT w.*, 
                   u_from.full_name as from_user_name, u_from.role as from_user_role,
                   u_to.full_name as to_user_name, u_to.role as to_user_role
                   FROM workflow_tracking w
                   LEFT JOIN users u_from ON w.from_user = u_from.id
                   LEFT JOIN users u_to ON w.to_user = u_to.id
                   WHERE w.document_id = ?
                   ORDER BY w.created_at ASC";
$stmt = $conn->prepare($workflow_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$workflow = $stmt->get_result();

// Get all comments
$comments_query = "SELECT c.*, u.full_name, u.role 
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   WHERE c.document_id = ?
                   ORDER BY c.created_at ASC";
$stmt = $conn->prepare($comments_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$comments = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .tracking-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    /* Document Info Card */
    .doc-info-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .doc-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .doc-title h2 {
        margin: 0 0 5px 0;
        color: #333;
    }
    
    .doc-number {
        font-family: monospace;
        font-size: 14px;
        color: #667eea;
    }
    
    .status-badge-large {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .doc-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }
    
    .detail-item i {
        width: 20px;
        color: #667eea;
    }
    
    /* Timeline */
    .timeline-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .timeline-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
    }
    
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #667eea, #764ba2);
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 30px;
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .timeline-icon {
        position: absolute;
        left: -30px;
        top: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        border: 2px solid;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    
    .timeline-icon.submit { border-color: #3498db; background: #e3f2fd; }
    .timeline-icon.assign { border-color: #9b59b6; background: #f3e5f5; }
    .timeline-icon.approve { border-color: #27ae60; background: #e8f5e9; }
    .timeline-icon.reject { border-color: #e74c3c; background: #ffebee; }
    .timeline-icon.comment { border-color: #95a5a6; background: #f5f5f5; }
    .timeline-icon.return { border-color: #f39c12; background: #fff3e0; }
    .timeline-icon.close { border-color: #2c3e50; background: #e8e8e8; }
    .timeline-icon.reopen { border-color: #1abc9c; background: #e8f8f5; }
    
    .timeline-content {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
        margin-left: 15px;
    }
    
    .timeline-time {
        font-size: 11px;
        color: #999;
        margin-bottom: 8px;
    }
    
    .timeline-action {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .timeline-action strong {
        color: #333;
    }
    
    .timeline-details {
        font-size: 13px;
        color: #666;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed #ddd;
    }
    
    .timeline-comment {
        background: #fff3cd;
        border-left: 3px solid #ffc107;
        padding: 10px;
        margin-top: 10px;
        border-radius: 5px;
        font-style: italic;
    }
    
    /* Summary Stats */
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 24px;
        font-weight: bold;
    }
    
    .stat-label {
        font-size: 12px;
        opacity: 0.9;
        margin-top: 5px;
    }
    
    /* Print Styles */
    @media print {
        .sidebar, .top-header, .export-buttons, .action-buttons, .footer, .nav, .breadcrumb {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .timeline-container {
            box-shadow: none;
        }
        .timeline-icon {
            background: #f0f0f0;
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .timeline {
            padding-left: 20px;
        }
        .timeline-icon {
            width: 32px;
            height: 32px;
            left: -22px;
        }
        .timeline-icon i {
            font-size: 12px;
        }
        .timeline-content {
            margin-left: 5px;
        }
    }
</style>

<div class="content-wrapper">
    <div class="tracking-container">
        <!-- Document Information Card -->
        <div class="doc-info-card">
            <div class="doc-header">
                <div class="doc-title">
                    <h2><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($document['title']); ?></h2>
                    <div class="doc-number">
                        Document #: <?php echo $document['document_number']; ?> | Folio: <?php echo $document['folio_number']; ?>
                    </div>
                </div>
                <div class="status-badge-large status-<?php echo $document['status']; ?>">
                    <i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $document['status'])); ?>
                </div>
            </div>
            <div class="doc-details">
                <div class="detail-item"><i class="fas fa-folder"></i> Folder: <?php echo htmlspecialchars($document['folder_name']); ?></div>
                <div class="detail-item"><i class="fas fa-user"></i> Submitted by: <?php echo htmlspecialchars($document['submitted_by_name']); ?></div>
                <div class="detail-item"><i class="fas fa-calendar"></i> Created: <?php echo date('F d, Y H:i:s', strtotime($document['created_at'])); ?></div>
                <?php if ($document['updated_at'] && $document['updated_at'] != $document['created_at']): ?>
                    <div class="detail-item"><i class="fas fa-edit"></i> Last Updated: <?php echo date('F d, Y H:i:s', strtotime($document['updated_at'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $workflow->num_rows; ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $comments->num_rows; ?></div>
                <div class="stat-label">Comments</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">
                    <?php
                    $assign_count = 0;
                    $workflow->data_seek(0);
                    while ($w = $workflow->fetch_assoc()) {
                        if ($w['action'] == 'assign') $assign_count++;
                    }
                    $workflow->data_seek(0);
                    echo $assign_count;
                    ?>
                </div>
                <div class="stat-label">Assignments</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">
                    <?php
                    $days = floor((time() - strtotime($document['created_at'])) / (60 * 60 * 24));
                    echo $days;
                    ?>
                </div>
                <div class="stat-label">Days in System</div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="timeline-container">
            <div class="timeline-header">
                <h3><i class="fas fa-stream"></i> Activity Timeline</h3>
                <div class="export-buttons">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button onclick="exportTimeline()" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export PDF
                    </button>
                </div>
            </div>
            
            <?php if ($workflow->num_rows > 0): ?>
                <div class="timeline">
                    <?php 
                    $item_number = 1;
                    while ($event = $workflow->fetch_assoc()): 
                        $icon_class = '';
                        $icon = '';
                        
                        switch($event['action']) {
                            case 'submit':
                                $icon_class = 'submit';
                                $icon = 'fa-upload';
                                $action_text = 'Document Submitted';
                                break;
                            case 'assign':
                                $icon_class = 'assign';
                                $icon = 'fa-user-plus';
                                $action_text = 'Document Assigned';
                                break;
                            case 'approve':
                                $icon_class = 'approve';
                                $icon = 'fa-check';
                                $action_text = 'Document Approved';
                                break;
                            case 'reject':
                                $icon_class = 'reject';
                                $icon = 'fa-times';
                                $action_text = 'Document Rejected';
                                break;
                            case 'comment':
                                $icon_class = 'comment';
                                $icon = 'fa-comment';
                                $action_text = 'Comment Added';
                                break;
                            case 'return_to_records':
                                $icon_class = 'return';
                                $icon = 'fa-undo';
                                $action_text = 'Returned to Records';
                                break;
                            case 'close':
                                $icon_class = 'close';
                                $icon = 'fa-ban';
                                $action_text = 'Document Closed';
                                break;
                            case 'reopen':
                                $icon_class = 'reopen';
                                $icon = 'fa-folder-open';
                                $action_text = 'Document Reopened';
                                break;
                            default:
                                $icon_class = 'submit';
                                $icon = 'fa-clock';
                                $action_text = ucfirst($event['action']);
                        }
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $icon_class; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-time">
                                    <i class="fas fa-clock"></i> <?php echo date('F d, Y H:i:s', strtotime($event['created_at'])); ?>
                                </div>
                                <div class="timeline-action">
                                    <strong><?php echo $action_text; ?></strong>
                                    <?php if ($event['from_user_name']): ?>
                                        by <strong><?php echo htmlspecialchars($event['from_user_name']); ?></strong>
                                        <span class="comment-role">(<?php echo ucfirst(str_replace('_', ' ', $event['from_user_role'])); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($event['to_user_name']): ?>
                                    <div class="timeline-details">
                                        <i class="fas fa-user-check"></i> Assigned to: <strong><?php echo htmlspecialchars($event['to_user_name']); ?></strong>
                                        <span class="comment-role">(<?php echo ucfirst(str_replace('_', ' ', $event['to_user_role'])); ?>)</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($event['notes']): ?>
                                    <div class="timeline-comment">
                                        <i class="fas fa-quote-left"></i> <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                    $item_number++;
                    endwhile; 
                    ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 40px;">
                    <i class="fas fa-history"></i>
                    <p>No activity recorded for this document yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Comments Section -->
        <?php if ($comments->num_rows > 0): ?>
            <div class="timeline-container">
                <h3><i class="fas fa-comments"></i> All Comments</h3>
                <div style="margin-top: 20px;">
                    <?php while ($comment = $comments->fetch_assoc()): ?>
                        <div class="comment-item" style="display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                            <div class="comment-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">
                                <?php echo strtoupper(substr($comment['full_name'], 0, 1)); ?>
                            </div>
                            <div class="comment-content" style="flex: 1;">
                                <div class="comment-header" style="display: flex; align-items: baseline; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">
                                    <span class="comment-author" style="font-weight: 600;"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                    <span class="comment-role" style="font-size: 11px; padding: 2px 8px; border-radius: 12px; background: #e0e0e0;"><?php echo ucfirst(str_replace('_', ' ', $comment['role'])); ?></span>
                                    <span class="comment-time" style="font-size: 11px; color: #999;"><?php echo date('M d, Y H:i:s', strtotime($comment['created_at'])); ?></span>
                                </div>
                                <div class="comment-text" style="color: #555; line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons" style="display: flex; gap: 10px; justify-content: flex-end;">
            <a href="history.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Tracking List
            </a>
            <a href="../documents/view.php?id=<?php echo $document_id; ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Document
            </a>
        </div>
    </div>
</div>

<script>
function exportTimeline() {
    window.print();
}

// Add animation to timeline items
document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.timeline-item');
    items.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php
include_once '../../includes/footer.php';
?>