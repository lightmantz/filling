<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$page_title = 'Pending Processing';
$base_url = '../../';

$conn = getConnection();

// Get documents that need processing (submitted but not yet sent to admin)
$query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          JOIN users u ON d.submitted_by = u.id
          WHERE d.status = 'submitted'
          ORDER BY d.created_at ASC";
$documents = $conn->query($query);

// Handle document submission to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_admin'])) {
    $document_id = $_POST['document_id'];
    $admin_id = $_POST['admin_id'];
    
    $update_query = "UPDATE documents SET status = 'submitted_to_admin', current_holder = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $admin_id, $document_id);
    
    if ($stmt->execute()) {
        trackWorkflow($document_id, $_SESSION['user_id'], $admin_id, 'submit_to_admin', 
                     'Document submitted for review and decision');
        $success = "Document submitted to administrator successfully!";
        // Refresh the page to show updated list
        header("Location: pending_processing.php?success=1");
        exit();
    } else {
        $error = "Failed to submit document.";
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .pending-container {
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
        transition: all 0.3s;
    }
    
    .document-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .document-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .document-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    
    .document-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
        font-size: 14px;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .meta-item i {
        color: #667eea;
        width: 20px;
    }
    
    .document-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }
    
    .btn-submit {
        background: #27ae60;
        color: white;
    }
    
    .btn-submit:hover {
        background: #229954;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .admin-select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-width: 200px;
    }
</style>

<div class="content-wrapper">
    <h2>Pending Processing</h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Document submitted to administrator successfully!</div>
    <?php endif; ?>
    
    <div class="pending-container">
        <?php if ($documents->num_rows > 0): ?>
            <?php while ($doc = $documents->fetch_assoc()): ?>
                <div class="document-card">
                    <div class="document-header">
                        <div class="document-title">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </div>
                        <span class="status-badge status-submitted">Pending Processing</span>
                    </div>
                    
                    <div class="document-meta">
                        <div class="meta-item">
                            <i class="fas fa-hashtag"></i>
                            <span>Document #: <?php echo $doc['document_number']; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-folder"></i>
                            <span>Folder: <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Submitted by: <?php echo htmlspecialchars($doc['submitted_by_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Date: <?php echo date('F d, Y H:i', strtotime($doc['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="document-actions">
                        <a href="view.php?id=<?php echo $doc['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> Preview Document
                        </a>
                        
                        <form method="POST" style="display: inline-flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                            <select name="admin_id" class="admin-select" required>
                                <option value="">Select Administrator</option>
                                <?php
                                $admins = $conn->query("SELECT id, full_name FROM users WHERE role = 'admin' AND is_active = 1");
                                while ($admin = $admins->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="submit_to_admin" class="btn btn-submit" 
                                    onclick="return confirm('Submit this document to administrator for review?')">
                                <i class="fas fa-paper-plane"></i> Submit to Administrator
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="font-size: 64px; color: #27ae60;"></i>
                <h3>No Pending Documents</h3>
                <p>All documents have been processed.</p>
                <a href="all_documents.php" class="btn btn-primary">View All Documents</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>