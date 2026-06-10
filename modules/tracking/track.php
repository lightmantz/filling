<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Track Document';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$tracking_number = $_GET['tracking_no'] ?? '';

$document = null;
$workflow = null;

if (!empty($tracking_number)) {
    // Get document by tracking number with access check
    if ($user_role === 'user') {
        $query = "SELECT d.*, f.name as folder_name 
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  WHERE d.document_number = ? AND d.submitted_by = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $tracking_number, $user_id);
    } else {
        $query = "SELECT d.*, f.name as folder_name 
                  FROM documents d
                  JOIN folders f ON d.folder_id = f.id
                  WHERE d.document_number = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $tracking_number);
    }
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    if ($document) {
        // Get workflow
        $workflow_query = "SELECT w.*, u.full_name as user_name, u.role as user_role
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
        max-width: 900px;
        margin: 0 auto;
    }
    
    .search-box {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        text-align: center;
        margin-bottom: 30px;
    }
    
    .search-box h3 {
        margin-bottom: 20px;
        color: #333;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .search-form input {
        flex: 1;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .search-form button {
        padding: 12px 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .document-info {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .status-flow {
        display: flex;
        justify-content: space-between;
        margin: 30px 0;
        position: relative;
        flex-wrap: wrap;
    }
    
    .status-step {
        flex: 1;
        text-align: center;
        position: relative;
        min-width: 80px;
    }
    
    .step-icon {
        width: 50px;
        height: 50px;
        background: #e0e0e0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        color: #999;
    }
    
    .step-icon.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .step-icon.completed {
        background: #27ae60;
        color: white;
    }
    
    .step-label {
        font-size: 12px;
        color: #666;
    }
    
    .step-label.active {
        color: #667eea;
        font-weight: bold;
    }
    
    .step-label.completed {
        color: #27ae60;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .view-details-btn {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 20px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 8px;
    }
    
    @media (max-width: 768px) {
        .status-step {
            min-width: 60px;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            font-size: 14px;
        }
        .step-label {
            font-size: 10px;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-search"></i> Track Document</h2>
    
    <div class="track-container">
        <div class="search-box">
            <h3>Enter Document Tracking Number</h3>
            <form method="GET" class="search-form">
                <input type="text" name="tracking_no" placeholder="Enter document number (e.g., PF/1/31/05/2026)" 
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
                <p><strong>Document Number:</strong> <?php echo $document['document_number']; ?></p>
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
                    if ($current_index === false) $current_index = -1;
                    
                    $status_icons = [
                        'submitted' => 'fa-file-upload',
                        'in_review' => 'fa-eye',
                        'approved' => 'fa-check-circle',
                        'closed' => 'fa-archive'
                    ];
                    
                    $status_labels = [
                        'submitted' => 'Submitted',
                        'in_review' => 'Under Review',
                        'approved' => 'Approved',
                        'closed' => 'Closed'
                    ];
                    
                    foreach ($statuses as $index => $status):
                        $class = '';
                        $icon_class = '';
                        if ($index < $current_index) {
                            $class = 'completed';
                            $icon_class = 'completed';
                        } elseif ($index == $current_index) {
                            $class = 'active';
                            $icon_class = 'active';
                        }
                    ?>
                        <div class="status-step">
                            <div class="step-icon <?php echo $icon_class; ?>">
                                <i class="fas <?php echo $status_icons[$status]; ?>"></i>
                            </div>
                            <div class="step-label <?php echo $class; ?>">
                                <?php echo $status_labels[$status]; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <?php if ($workflow && $workflow->num_rows > 0): ?>
                <div class="document-info">
                    <h4>Recent Activity</h4>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php 
                        $recent = [];
                        while ($event = $workflow->fetch_assoc()) {
                            $recent[] = $event;
                        }
                        $recent = array_slice(array_reverse($recent), 0, 5);
                        foreach ($recent as $event): 
                        ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <strong><?php echo ucfirst($event['action']); ?></strong>
                                    <small style="color: #999;"><?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?></small>
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    By: <?php echo htmlspecialchars($event['user_name']); ?>
                                    (<?php echo ucfirst(str_replace('_', ' ', $event['user_role'])); ?>)
                                </div>
                                <?php if ($event['notes']): ?>
                                    <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                        "<?php echo htmlspecialchars(substr($event['notes'], 0, 100)); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="history.php?id=<?php echo $document['id']; ?>" class="view-details-btn">
                        <i class="fas fa-history"></i> View Full History
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>