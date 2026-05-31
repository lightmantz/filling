<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Document Status';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

$query = "SELECT d.*, f.name as folder_name,
          w.action, w.created_at as action_date, w.notes,
          u2.full_name as assigned_to_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          LEFT JOIN workflow_tracking w ON d.id = w.document_id
          LEFT JOIN users u2 ON w.to_user = u2.id
          WHERE d.submitted_by = ?
          GROUP BY d.id
          ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
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
        font-size: 14px;
    }
    
    .timeline-icon.completed {
        background: #27ae60;
    }
    
    .timeline-icon.pending {
        background: #ff9800;
    }
    
    .timeline-content {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .timeline-date {
        font-size: 12px;
        color: #999;
        margin-bottom: 5px;
    }
    
    .status-flow {
        display: flex;
        justify-content: space-between;
        margin: 30px 0;
        position: relative;
    }
    
    .status-step {
        flex: 1;
        text-align: center;
        position: relative;
    }
    
    .status-step .step-icon {
        width: 40px;
        height: 40px;
        background: #e0e0e0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        color: #999;
    }
    
    .status-step.active .step-icon {
        background: #667eea;
        color: white;
    }
    
    .status-step.completed .step-icon {
        background: #27ae60;
        color: white;
    }
    
    .status-step .step-label {
        font-size: 12px;
        color: #666;
    }
    
    .status-step.active .step-label {
        color: #667eea;
        font-weight: bold;
    }
    
    .document-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .document-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
</style>

<div class="content-wrapper">
    <h2>My Document Status</h2>
    
    <?php if ($documents->num_rows > 0): ?>
        <?php while ($doc = $documents->fetch_assoc()): ?>
            <div class="document-card">
                <div class="document-header">
                    <div>
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($doc['title']); ?></h3>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            Document #: <?php echo $doc['document_number']; ?> | 
                            Folder: <?php echo htmlspecialchars($doc['folder_name']); ?>
                        </div>
                    </div>
                    <span class="status-badge status-<?php echo $doc['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                    </span>
                </div>
                
                <!-- Status Flow -->
                <div class="status-flow">
                    <?php
                    $statuses = ['submitted', 'in_review', 'approved', 'closed'];
                    $current_status = $doc['status'];
                    $current_index = array_search($current_status, $statuses);
                    if ($current_index === false) $current_index = -1;
                    
                    foreach ($statuses as $index => $status):
                        $status_class = '';
                        if ($index < $current_index) {
                            $status_class = 'completed';
                        } elseif ($index == $current_index) {
                            $status_class = 'active';
                        }
                        
                        $icon = '';
                        switch($status) {
                            case 'submitted': $icon = 'fa-file-upload'; break;
                            case 'in_review': $icon = 'fa-eye'; break;
                            case 'approved': $icon = 'fa-check'; break