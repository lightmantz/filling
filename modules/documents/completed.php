<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Completed Reviews';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Allow admin AND super_admin
if ($user_role !== 'admin' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name,
          w.created_at as decision_date, w.action as decision
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          LEFT JOIN workflow_tracking w ON d.id = w.document_id AND w.action IN ('approve', 'reject')
          WHERE d.status IN ('approved', 'rejected')
          ORDER BY w.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$documents = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .completed-container {
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
    }
    
    .document-card.approved {
        border-left: 4px solid #27ae60;
    }
    
    .document-card.rejected {
        border-left: 4px solid #e74c3c;
    }
    
    .decision-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .decision-badge.approved {
        background: #27ae60;
        color: white;
    }
    
    .decision-badge.rejected {
        background: #e74c3c;
        color: white;
    }
    
    .filter-bar {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    
    .filter-bar a {
        padding: 8px 15px;
        background: #f0f0f0;
        text-decoration: none;
        color: #333;
        border-radius: 5px;
    }
    
    .filter-bar a.active {
        background: #667eea;
        color: white;
    }
    
    .btn-small {
        display: inline-block;
        padding: 4px 10px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        background-color: #3498db;
        color: white;
    }
</style>

<div class="content-wrapper">
    <h2>Completed Reviews</h2>
    
    <div class="filter-bar">
        <a href="?status=all" class="<?php echo !isset($_GET['status']) || $_GET['status'] == 'all' ? 'active' : ''; ?>">All</a>
        <a href="?status=approved" class="<?php echo isset($_GET['status']) && $_GET['status'] == 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="?status=rejected" class="<?php echo isset($_GET['status']) && $_GET['status'] == 'rejected' ? 'active' : ''; ?>">Rejected</a>
    </div>
    
    <div class="completed-container">
        <?php if ($documents->num_rows > 0): ?>
            <?php while ($doc = $documents->fetch_assoc()): ?>
                <?php if (!isset($_GET['status']) || $_GET['status'] == 'all' || $_GET['status'] == $doc['status']): ?>
                    <div class="document-card <?php echo $doc['status']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($doc['title']); ?></h3>
                                <div style="display: flex; gap: 20px; margin-bottom: 10px; font-size: 14px;">
                                    <span><i class="fas fa-hashtag"></i> <?php echo $doc['document_number']; ?></span>
                                    <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($doc['submitted_by_name']); ?></span>
                                </div>
                            </div>
                            <div>
                                <span class="decision-badge <?php echo $doc['status']; ?>">
                                    <i class="fas fa-<?php echo $doc['status'] == 'approved' ? 'check' : 'times'; ?>"></i>
                                    <?php echo ucfirst($doc['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 10px; font-size: 13px; color: #666;">
                            <i class="fas fa-calendar"></i> Decision made on: <?php echo date('F d, Y H:i', strtotime($doc['decision_date'])); ?>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn-small">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line" style="font-size: 64px; color: #ccc;"></i>
                <p>No completed reviews yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>