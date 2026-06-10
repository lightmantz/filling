<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Assigned to Me';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Allow admin AND super_admin
if ($user_role !== 'admin' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE d.current_holder = ? AND d.status = 'in_review'
          ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .assigned-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .document-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #ff9800;
    }
    
    .document-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .document-title {
        font-size: 18px;
        font-weight: 600;
    }
    
    .priority-badge {
        background: #ff9800;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .document-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 15px;
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
    
    .btn-approve {
        background: #27ae60;
        color: white;
    }
    
    .btn-reject {
        background: #e74c3c;
        color: white;
    }
    
    .btn-comment {
        background: #3498db;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
    }
</style>

<div class="content-wrapper">
    <h2>Documents Assigned to Me</h2>
    
    <div class="assigned-container">
        <?php if ($documents->num_rows > 0): ?>
            <?php while ($doc = $documents->fetch_assoc()): ?>
                <div class="document-card">
                    <div class="document-header">
                        <div class="document-title">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </div>
                        <div class="priority-badge">
                            <i class="fas fa-clock"></i> Requires Action
                        </div>
                    </div>
                    
                    <div class="document-meta">
                        <div><i class="fas fa-hashtag"></i> <?php echo $doc['document_number']; ?></div>
                        <div><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></div>
                        <div><i class="fas fa-user"></i> From: <?php echo htmlspecialchars($doc['submitted_by_name']); ?></div>
                        <div><i class="fas fa-calendar"></i> Received: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn btn-comment">
                            <i class="fas fa-comment"></i> Review & Comment
                        </a>
                        <a href="approve.php?id=<?php echo $doc['id']; ?>" class="btn btn-approve" 
                           onclick="return confirm('Approve this document?')">
                            <i class="fas fa-check"></i> Approve
                        </a>
                        <a href="reject.php?id=<?php echo $doc['id']; ?>" class="btn btn-reject" 
                           onclick="return confirm('Reject this document?')">
                            <i class="fas fa-times"></i> Reject
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="font-size: 64px; color: #27ae60;"></i>
                <h3>No Assigned Documents</h3>
                <p>You have no documents assigned for review at this time.</p>
                <a href="pending.php" class="btn btn-primary">View Pending Documents</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>