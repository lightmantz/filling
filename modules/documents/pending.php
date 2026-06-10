<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Pending Reviews';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Allow admin AND super_admin
if ($user_role !== 'admin' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

// Get all pending documents
$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE d.status IN ('submitted', 'submitted_to_admin')
          ORDER BY d.created_at ASC";
$pending_docs = $conn->query($query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .pending-list {
        display: grid;
        gap: 20px;
    }
    
    .pending-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-left: 4px solid #ff9800;
        transition: transform 0.3s;
    }
    
    .pending-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .pending-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .pending-header h3 {
        margin: 0;
        color: #333;
    }
    
    .priority-badge {
        background: #ff9800;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .pending-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .pending-details p {
        margin: 8px 0;
        font-size: 14px;
    }
    
    .pending-actions {
        margin-top: 15px;
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
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 8px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-submitted { background-color: #3498db; color: white; }
    .status-submitted_to_admin { background-color: #9b59b6; color: white; }
</style>

<div class="content-wrapper">
    <h2>Documents Pending Review</h2>
    
    <?php if ($pending_docs->num_rows > 0): ?>
        <div class="pending-list">
            <?php while ($doc = $pending_docs->fetch_assoc()): ?>
                <div class="pending-card">
                    <div class="pending-header">
                        <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                        <span class="priority-badge">
                            <i class="fas fa-clock"></i> Requires Action
                        </span>
                    </div>
                    <div class="pending-details">
                        <p><i class="fas fa-hashtag"></i> <strong>Document #:</strong> <?php echo $doc['document_number']; ?></p>
                        <p><i class="fas fa-folder"></i> <strong>Folder:</strong> <?php echo htmlspecialchars($doc['folder_name']); ?></p>
                        <p><i class="fas fa-user"></i> <strong>Submitted By:</strong> <?php echo htmlspecialchars($doc['submitted_by_name']); ?></p>
                        <p><i class="fas fa-calendar"></i> <strong>Submitted On:</strong> <?php echo date('F d, Y H:i', strtotime($doc['created_at'])); ?></p>
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo $doc['status']; ?>"><?php echo str_replace('_', ' ', $doc['status']); ?></span></p>
                    </div>
                    <div class="pending-actions">
                        <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Review Document
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="font-size: 64px; color: #27ae60;"></i>
            <h3>No Pending Documents</h3>
            <p>All documents have been reviewed.</p>
            <a href="completed.php" class="btn btn-primary">View Completed Reviews</a>
        </div>
    <?php endif; ?>
</div>

<?php
include_once '../../includes/footer.php';
?>