<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'View Document';
$base_url = '../../';

$conn = getConnection();
$document_id = $_GET['id'] ?? 0;

if ($document_id <= 0) {
    header('Location: ' . BASE_URL . '/modules/users/dashboard.php');
    exit();
}

// Get document details with access control
$query = "SELECT d.*, f.name as folder_name, f.is_confidential as folder_confidential,
          u.full_name as submitter_name, u2.full_name as holder_name
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          LEFT JOIN users u ON d.submitted_by = u.id
          LEFT JOIN users u2 ON d.current_holder = u2.id
          WHERE d.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();

if (!$document) {
    die("Document not found");
}

// Check access permissions - INCLUDING SUPER ADMIN
$can_view = false;
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Super admin can view ALL documents
if ($user_role === 'super_admin') {
    $can_view = true;
}
// Records officer can view documents not submitted to admin
elseif ($user_role === 'records_officer') {
    $can_view = !in_array($document['status'], ['submitted_to_admin', 'in_review']);
} 
// Admin can view documents assigned to them
elseif ($user_role === 'admin') {
    $can_view = ($document['current_holder'] == $user_id || $document['status'] == 'submitted_to_admin');
} 
// Normal user can only view their own documents
elseif ($user_role === 'user') {
    $can_view = ($document['submitted_by'] == $user_id);
}

if (!$can_view) {
    // Show error message with more details
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                text-align: center;
                max-width: 500px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            }
            .error-icon {
                font-size: 64px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
            }
            p {
                color: #666;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
            .btn:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Access Denied</h1>
            <p>You don't have permission to view this document.</p>
            <p><strong>Document:</strong> <?php echo htmlspecialchars($document['title']); ?><br>
            <strong>Your Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?><br>
            <strong>Document Status:</strong> <?php echo $document['status']; ?></p>
            <a href="<?php echo BASE_URL; ?>/modules/users/<?php echo $user_role; ?>_dashboard.php" class="btn">Back to Dashboard</a>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
        <script>
// Debug script to test if mentions are working
document.addEventListener('DOMContentLoaded', function() {
    console.log('View page loaded');
    
    // Test if textarea exists
    const textarea = document.getElementById('commentInput');
    if (textarea) {
        console.log('Comment textarea found');
        
        // Add a test event listener
        textarea.addEventListener('keydown', function(e) {
            if (e.key === '@') {
                console.log('@ key pressed!');
            }
        });
    } else {
        console.log('Comment textarea NOT found');
    }
    
    // Test API endpoint
    fetch('../../modules/notifications/get_users.php')
        .then(response => response.json())
        .then(data => {
            console.log('API Test Response:', data);
        })
        .catch(error => {
            console.error('API Test Error:', error);
        });
});
</script>
    </body>
    </html>
    <?php
    exit();
}

// Handle confidential document password
$show_content = true;
$error = '';
if ($document['is_confidential'] || $document['folder_confidential']) {
    if (!isset($_POST['access_token']) && !isset($_SESSION['confidential_access'][$document_id])) {
        $show_content = false;
    } elseif (isset($_POST['access_token'])) {
        $valid_token = ($_POST['access_token'] === $document['access_token']);
        if ($valid_token) {
            $_SESSION['confidential_access'][$document_id] = true;
            $log_query = "INSERT INTO confidential_access_logs (document_id, user_id, access_token, ip_address) 
                          VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $document_id, $user_id, $document['access_token'], $ip);
            $log_stmt->execute();
            $show_content = true;
        } else {
            $error = "Invalid access token";
        }
    }
}

// Get comments with user details
$comments_query = "SELECT c.*, u.full_name, u.role, u.username
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   WHERE c.document_id = ?
                   ORDER BY c.created_at ASC";
$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $document_id);
$comments_stmt->execute();
$comments = $comments_stmt->get_result();

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        addComment($document_id, $user_id, $comment);
        header("Location: view.php?id=$document_id");
        exit();
    }
}

// Handle document actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'approve' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        updateDocumentStatus($document_id, 'approved', $user_id);
        trackWorkflow($document_id, $user_id, null, 'approve', $_POST['notes'] ?? 'Document approved');
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'reject' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        updateDocumentStatus($document_id, 'rejected', $user_id);
        trackWorkflow($document_id, $user_id, null, 'reject', $_POST['notes'] ?? 'Document rejected');
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'assign' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        $assigned_to = $_POST['assigned_to'];
        $query = "UPDATE documents SET current_holder = ?, status = 'in_review' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $assigned_to, $document_id);
        $stmt->execute();
        trackWorkflow($document_id, $user_id, $assigned_to, 'assign', $_POST['notes'] ?? null);
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'return_to_records' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        $query = "UPDATE documents SET current_holder = NULL, status = 'returned' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        trackWorkflow($document_id, $user_id, null, 'return_to_records', $_POST['notes'] ?? null);
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'close' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        updateDocumentStatus($document_id, 'closed', $user_id);
        trackWorkflow($document_id, $user_id, null, 'close', 'Document closed');
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'reopen' && ($user_role === 'records_officer' || $user_role === 'super_admin')) {
        updateDocumentStatus($document_id, 'submitted', $user_id);
        trackWorkflow($document_id, $user_id, null, 'reopen', 'Document reopened');
        header("Location: view.php?id=$document_id");
        exit();
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    /* Document Viewer Styles */
    .document-viewer {
        display: flex;
        gap: 30px;
        min-height: calc(100vh - 200px);
    }
    
    /* Left Panel - Document Content */
    .document-panel {
        flex: 2;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        overflow: hidden;
        position: sticky;
        top: 90px;
        height: fit-content;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
    }
    
    .document-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
    }
    
    .document-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .badge-confidential {
        background: #dc3545;
        color: white;
    }
    
    .badge-normal {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .document-header h1 {
        font-size: 24px;
        margin: 10px 0;
        font-weight: 600;
    }
    
    .document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 15px;
        font-size: 13px;
        opacity: 0.9;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .document-body {
        padding: 30px;
    }
    
    .document-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .info-card {
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 8px;
    }
    
    .info-label {
        font-size: 11px;
        text-transform: uppercase;
        color: #666;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: #333;
    }
    
    .document-content {
        margin-top: 20px;
    }
    
    .document-content h3 {
        font-size: 16px;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .content-body {
        background: #fafbfc;
        padding: 20px;
        border-radius: 8px;
        line-height: 1.6;
        color: #444;
        font-size: 14px;
    }
    
    .attachment-box {
        background: #f8f9fa;
        border: 1px dashed #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    /* Right Panel - Comments */
    .comments-panel {
        flex: 1.2;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        height: calc(100vh - 100px);
        position: sticky;
        top: 90px;
    }
    
    .comments-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        background: #fafbfc;
        border-radius: 12px 12px 0 0;
    }
    
    .comments-header h3 {
        font-size: 18px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .comment-count {
        background: #667eea;
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .comments-list {
        flex: 1;
        overflow-y: auto;
        padding: 20px 25px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .comment-item {
        display: flex;
        gap: 15px;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .comment-content {
        flex: 1;
    }
    
    .comment-header {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    
    .comment-author {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .comment-role {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 12px;
        background: #f0f0f0;
        color: #666;
    }
    
    .comment-time {
        font-size: 11px;
        color: #999;
    }
    
    .comment-text {
        color: #555;
        line-height: 1.5;
        font-size: 14px;
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 12px;
        border-top-left-radius: 4px;
    }
    
    /* Mention Highlighting */
    .comment-text .mention {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 4px;
        border-radius: 4px;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }
    
    .comment-text .mention:hover {
        background: #bbdef5;
        text-decoration: underline;
        cursor: pointer;
    }
    
    /* Comment Form */
    .comment-form-container {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        background: white;
        border-radius: 0 0 12px 12px;
        position: relative;
    }
    
    .comment-input-wrapper {
        position: relative;
    }
    
    .comment-input-wrapper textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        font-family: inherit;
        font-size: 14px;
        resize: vertical;
        transition: all 0.3s;
        line-height: 1.5;
    }
    
    .comment-input-wrapper textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .comment-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 12px;
    }
    
    .btn-post {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 25px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-post:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    /* Mentions Dropdown Styles */
    .mentions-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        min-width: 220px;
        display: none;
    }
    
    .mention-item {
        padding: 8px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.2s;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .mention-item:last-child {
        border-bottom: none;
    }
    
    .mention-item:hover,
    .mention-item.selected {
        background: #f0f7ff;
    }
    
    .mention-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 12px;
        flex-shrink: 0;
    }
    
    .mention-info {
        flex: 1;
    }
    
    .mention-name {
        font-weight: 500;
        font-size: 13px;
        color: #333;
    }
    
    .mention-role {
        font-size: 10px;
        color: #666;
    }
    
    .mention-username {
        font-size: 10px;
        color: #999;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }
    
    .btn-success {
        background: #27ae60;
        color: white;
    }
    
    .btn-success:hover {
        background: #229954;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #e74c3c;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c0392b;
        transform: translateY(-2px);
    }
    
    .btn-warning {
        background: #f39c12;
        color: white;
    }
    
    .btn-warning:hover {
        background: #e67e22;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-submitted { background: #3498db; color: white; }
    .status-in_review { background: #f39c12; color: white; }
    .status-approved { background: #27ae60; color: white; }
    .status-rejected { background: #e74c3c; color: white; }
    .status-closed { background: #2c3e50; color: white; }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 500px;
        max-width: 90%;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        padding: 15px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #999;
    }
    
    .close:hover {
        color: #333;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    
    textarea.form-control {
        resize: vertical;
        font-family: inherit;
    }
    
    /* Empty State */
    .empty-comments {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .empty-comments i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ddd;
    }
    
    /* Responsive */
    @media (max-width: 900px) {
        .document-viewer {
            flex-direction: column;
        }
        
        .document-panel,
        .comments-panel {
            position: static;
            height: auto;
        }
        
        .comments-panel {
            max-height: 500px;
        }
    }
</style>

<div class="content-wrapper">
    <?php if (!$show_content && isset($error)): ?>
        <div class="confidential-access">
            <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; max-width: 500px; margin: 0 auto;">
                <i class="fas fa-lock" style="font-size: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                <h3>Confidential Document</h3>
                <p>This document requires a special access token to view.</p>
                <form method="POST" style="margin-top: 20px;">
                    <input type="password" name="access_token" placeholder="Enter access token" 
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Verify Access</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="document-viewer">
            <!-- Left Panel - Document Content -->
            <div class="document-panel">
                <div class="document-header">
                    <div>
                        <span class="document-badge <?php echo ($document['is_confidential'] || $document['folder_confidential']) ? 'badge-confidential' : 'badge-normal'; ?>">
                            <i class="fas <?php echo ($document['is_confidential'] || $document['folder_confidential']) ? 'fa-lock' : 'fa-file-alt'; ?>"></i>
                            <?php echo ($document['is_confidential'] || $document['folder_confidential']) ? 'CONFIDENTIAL' : 'DOCUMENT'; ?>
                        </span>
                        <span class="status-badge status-<?php echo $document['status']; ?>" style="margin-left: 10px;">
                            <i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $document['status'])); ?>
                        </span>
                    </div>
                    <h1><?php echo htmlspecialchars($document['title']); ?></h1>
                    <div class="document-meta">
                        <div class="meta-item"><i class="fas fa-hashtag"></i> <?php echo $document['document_number']; ?></div>
                        <div class="meta-item"><i class="fas fa-sort-numeric-up"></i> Folio <?php echo $document['folio_number']; ?></div>
                        <div class="meta-item"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($document['folder_name']); ?></div>
                        <div class="meta-item"><i class="fas fa-user"></i> By <?php echo htmlspecialchars($document['submitter_name']); ?></div>
                        <div class="meta-item"><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($document['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="document-body">
                    <div class="document-info-grid">
                        <div class="info-card">
                            <div class="info-label">DOCUMENT NUMBER</div>
                            <div class="info-value"><?php echo $document['document_number']; ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">FOLIO NUMBER</div>
                            <div class="info-value"><?php echo $document['folio_number']; ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">SUBMITTED BY</div>
                            <div class="info-value"><?php echo htmlspecialchars($document['submitter_name']); ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">SUBMISSION DATE</div>
                            <div class="info-value"><?php echo date('F d, Y \a\t H:i', strtotime($document['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="document-content">
                        <h3><i class="fas fa-file-alt"></i> Document Content</h3>
                        <div class="content-body">
                            <?php echo nl2br(htmlspecialchars($document['content'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($document['file_path']): ?>
                        <div class="attachment-box">
                            <div>
                                <i class="fas fa-paperclip" style="color: #667eea;"></i>
                                <strong>Attachment</strong>
                                <span style="font-size: 12px; color: #666; margin-left: 10px;">Click to download</span>
                            </div>
                            <a href="<?php echo $base_url . '/' . $document['file_path']; ?>" class="btn btn-secondary btn-sm" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if (($user_role === 'admin' || $user_role === 'super_admin') && $document['status'] !== 'closed'): ?>
                            <button onclick="showAssignModal()" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Assign to User
                            </button>
                            <button onclick="showReturnModal()" class="btn btn-warning">
                                <i class="fas fa-undo-alt"></i> Return to Records
                            </button>
                            <button onclick="showDecisionModal()" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Make Decision
                            </button>
                            <button onclick="closeDocument()" class="btn btn-danger">
                                <i class="fas fa-ban"></i> Close Document
                            </button>
                        <?php endif; ?>
                        
                        <?php if (($user_role === 'records_officer' || $user_role === 'super_admin') && $document['status'] === 'closed'): ?>
                            <button onclick="reopenDocument()" class="btn btn-success">
                                <i class="fas fa-folder-open"></i> Reopen Document
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo BASE_URL; ?>/modules/users/<?php echo $user_role; ?>_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Comments -->
            <div class="comments-panel">
                <div class="comments-header">
                    <h3>
                        <i class="fas fa-comments"></i> Discussion
                        <span class="comment-count" id="commentCount"><?php echo $comments->num_rows; ?></span>
                    </h3>
                </div>
                
                <div class="comments-list" id="commentsList">
                    <?php if ($comments->num_rows > 0): ?>
                        <?php while ($comment = $comments->fetch_assoc()): 
                            $initial = strtoupper(substr($comment['full_name'], 0, 1));
                        ?>
                            <div class="comment-item">
                                <div class="comment-avatar">
                                    <?php echo $initial; ?>
                                </div>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <span class="comment-author"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                        <span class="comment-role">
                                            <?php echo ucfirst(str_replace('_', ' ', $comment['role'])); ?>
                                        </span>
                                        <span class="comment-time">
                                            <?php echo date('M d, Y \a\t H:i', strtotime($comment['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo highlightMentions(nl2br(htmlspecialchars($comment['comment_text']))); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-comments">
                            <i class="fas fa-comment-dots"></i>
                            <p>No comments yet</p>
                            <p style="font-size: 12px;">Be the first to add a comment. Type <strong>@</strong> to mention someone!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($document['status'] !== 'closed'): ?>
                    <div class="comment-form-container">
                        <form method="POST" id="commentForm">
                            <div class="comment-input-wrapper">
                                <textarea name="comment" id="commentInput" rows="3" placeholder="Write your comment here... Type @ to mention a user"></textarea>
                            </div>
                            <div class="comment-actions">
                                <button type="submit" class="btn-post" id="postCommentBtn">
                                    <i class="fas fa-paper-plane"></i> Post Comment
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Assign Document</h3>
            <span class="close" onclick="closeModal('assignModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="assign">
                <div class="form-group">
                    <label>Assign to:</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">Select User</option>
                        <?php
                        $users_query = "SELECT id, full_name, role FROM users WHERE role IN ('admin', 'records_officer', 'user') AND is_active = 1 ORDER BY full_name";
                        $users_result = $conn->query($users_query);
                        while ($user = $users_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instructions (Optional):</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Add any instructions for the assignee..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Document</button>
            </div>
        </form>
    </div>
</div>

<!-- Decision Modal -->
<div id="decisionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-gavel"></i> Make Decision</h3>
            <span class="close" onclick="closeModal('decisionModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <button onclick="setDecision('approve')" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button onclick="setDecision('reject')" class="btn btn-danger" style="flex: 1;">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
            <form method="POST" id="decisionForm">
                <input type="hidden" name="action" id="decisionAction">
                <div class="form-group">
                    <label>Decision Notes:</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Provide reason for your decision..."></textarea>
                </div>
                <div class="modal-footer" style="padding: 20px 0 0 0;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('decisionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions
function showAssignModal() {
    document.getElementById('assignModal').classList.add('show');
}

function showDecisionModal() {
    document.getElementById('decisionModal').classList.add('show');
}

function showReturnModal() {
    if (confirm('Are you sure you want to return this document to Records Officer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'return_to_records';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeDocument() {
    if (confirm('This will close the document and prevent further comments. Are you sure?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'close';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function reopenDocument() {
    if (confirm('Reopen this document for further comments?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reopen';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function setDecision(decision) {
    document.getElementById('decisionAction').value = decision;
    document.getElementById('decisionForm').submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// Auto-resize textarea
document.getElementById('commentInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

// Initialize mention manager
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('commentInput')) {
        new MentionManager('commentInput', {
            trigger: '@',
            minChars: 1
        });
    }
});
</script>

<!-- User Mentions Script -->
<script src="<?php echo $base_url; ?>/assets/js/mentions.js"></script>

<?php
include_once '../../includes/footer.php';
?>