<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Track Document';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$tracking_number = $_GET['tracking_no'] ?? '';

$document = null;
$workflow = null;

if (!empty($tracking_number)) {
    // Get document by tracking number
    $query = "SELECT d.*, f.name as folder_name 
              FROM documents d
              JOIN folders f ON d.folder_id = f.id
              WHERE d.document_number = ? AND d.submitted_by = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $tracking_number, $user_id);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    if ($document) {
        // Get workflow
        $workflow_query = "SELECT w.*, u.full_name as user_name
                          FROM workflow_tracking w
                          JOIN users u ON w.from_user = u.id
                          WHERE w.document_id = ?
                          ORDER BY w.created_at ASC";
        $stmt = $conn->prepare($workflow_query);
        $stmt->bind_param("i", $document['id']);
        $stmt->execute();
        $workflow = $stmt->get_result();
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .track-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .search-box {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        text-align: center;
        margin-bottom: 30px;
    }
    
    .search-box input {
        width: 70%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 16px;
    }
    
    .search-box button {
        padding: 12px 24px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .document-info {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .timeline {
        position: relative;
        padding: 20px 0;
    }
    
    .timeline-item {
        position: relative;
        padding-left: 40px;
        margin-bottom: 30px;
    }
    
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #667eea;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .timeline-icon.completed {
        background: #27ae60;
    }
    
    .timeline-content {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
    }
    
    .status-flow {
        display: flex;
        justify-content: space-between;
        margin: 20px 0;
    }
    
    .status-step {
        flex: 1;
        text-align: center;
    }
    
    .step-icon {
        width: 40px;
        height: 40px;
        background: #e0e0e0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
    }
    
    .step-icon.active {
        background: #667eea;
        color: white;
    }
    
    .step-icon.completed {
        background: #27ae60;
        color: white;
    }
</style>

<div class="content-wrapper">
    <h2>Track Document Status</h2>
    
    <div class="track-container">
        <div class="search-box">
            <h3>Enter Document Tracking Number</h3>
            <form method="GET">
                <input type="text" name="tracking_no" placeholder="e.g., DOC-2024-0001-001" 
                       value="<?php echo htmlspecialchars($tracking_number); ?>">
                <button type="submit"><i class="fas fa-search"></i> Track</button>
            </form>
        </div>
        
        <?php if ($tracking_number && !$document): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Document not found. Please check the tracking number.
            </div>
        <?php endif; ?>
        
        <?php if ($document): ?>
            <div class="document-info">
                <h3><?php echo htmlspecialchars($document['title']); ?></h3>
                <p><strong>Document #:</strong> <?php echo $document['document_number']; ?></p>
                <p><strong>Folder:</strong> <?php echo htmlspecialchars($document['folder_name']); ?></p>
                <p><strong>Current Status:</strong> 
                    <span class="status-badge status-<?php echo $document['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $document['status'])); ?>
                    </span>
                </p>
            </div>
            
            <!-- Status Flow -->
            <div class="document-info">
                <h4>Progress Status</h4>
                <div class="status-flow">
                    <?php
                    $statuses = ['submitted', 'in_review', 'approved', 'closed'];
                    $current_index = array_search($document['status'], $statuses);
                    foreach ($statuses as $index => $status):
                        $class = '';
                        if ($index < $current_index) $class = 'completed';
                        elseif ($index == $current_index) $class = 'active';
                    ?>
                        <div class="status-step">
                            <div class="step-icon <?php echo $class; ?>">
                                <i class="fas fa-<?php echo $status == 'submitted' ? 'file-upload' : ($status == 'in_review' ? 'eye' : ($status == 'approved' ? 'check' : 'archive')); ?>"></i>
                            </div>
                            <div><?php echo ucfirst(str_replace('_', ' ', $status)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Timeline -->
            <?php if ($workflow && $workflow->num_rows > 0): ?>
                <div class="document-info">
                    <h4>Activity Timeline</h4>
                    <div class="timeline">
                        <?php while ($event = $workflow->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon <?php echo $event['action'] == 'approve' ? 'completed' : ''; ?>">
                                    <i class="fas fa-<?php echo $event['action'] == 'submit' ? 'file' : ($event['action'] == 'assign' ? 'user' : 'comment'); ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <strong><?php echo htmlspecialchars($event['user_name']); ?></strong>
                                    <?php echo $event['action']; ?>d the document
                                    <div class="activity-time">
                                        <?php echo date('F d, Y H:i:s', strtotime($event['created_at'])); ?>
                                    </div>
                                    <?php if ($event['notes']): ?>
                                        <div class="activity-notes">
                                            <em>"<?php echo htmlspecialchars($event['notes']); ?>"</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>