<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$document_id = $_GET['id'] ?? 0;
$conn = getConnection();

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

// Check access permissions
$can_view = false;
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

if ($user_role === 'records_officer') {
    $can_view = !in_array($document['status'], ['submitted_to_admin', 'in_review']);
} elseif ($user_role === 'admin') {
    $can_view = ($document['current_holder'] == $user_id || $document['status'] == 'submitted_to_admin');
} else {
    $can_view = ($document['submitted_by'] == $user_id);
}

if (!$can_view) {
    die("You don't have permission to view this document");
}

// Handle confidential document password
$show_content = true;
if ($document['is_confidential'] || $document['folder_confidential']) {
    if (!isset($_POST['access_token']) && !isset($_SESSION['confidential_access'][$document_id])) {
        $show_content = false;
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Confidential Document Access</title>
            <link rel="stylesheet" href="/filing_system/assets/css/style.css">
        </head>
        <body>
            <div class="container">
                <h2>Confidential Document</h2>
                <p>This document requires a special access token to view.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Access Token:</label>
                        <input type="password" name="access_token" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Verify Access</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    } elseif (isset($_POST['access_token'])) {
        $valid_token = ($_POST['access_token'] === $document['access_token']);
        if ($valid_token) {
            $_SESSION['confidential_access'][$document_id] = true;
            // Log access
            $log_query = "INSERT INTO confidential_access_logs (document_id, user_id, access_token, ip_address) 
                          VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iiss", $document_id, $user_id, $document['access_token'], $ip);
            $log_stmt->execute();
        } else {
            die("Invalid access token");
        }
    }
}

// Get comments
$comments_query = "SELECT c.*, u.full_name, u.role 
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
    
    if ($action === 'assign' && $user_role === 'admin') {
        $assigned_to = $_POST['assigned_to'];
        $query = "UPDATE documents SET current_holder = ?, status = 'in_review' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $assigned_to, $document_id);
        $stmt->execute();
        
        trackWorkflow($document_id, $user_id, $assigned_to, 'assign', $_POST['notes'] ?? null);
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'return_to_records' && $user_role === 'admin') {
        $query = "UPDATE documents SET current_holder = NULL, status = 'returned' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        
        trackWorkflow($document_id, $user_id, null, 'return_to_records', $_POST['notes'] ?? null);
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'close' && $user_role === 'admin') {
        $query = "UPDATE documents SET status = 'closed' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        header("Location: view.php?id=$document_id");
        exit();
    }
    
    if ($action === 'reopen' && $user_role === 'records_officer') {
        $query = "UPDATE documents SET status = 'submitted', current_holder = NULL WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        header("Location: view.php?id=$document_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($document['title']); ?> - Filing System</title>
    <link rel="stylesheet" href="/filing_system/assets/css/style.css">
</head>
<body>
    <div class="container document-view">
        <div class="document-header">
            <h1><?php echo htmlspecialchars($document['title']); ?></h1>
            <div class="document-meta">
                <p><strong>Document Number:</strong> <?php echo htmlspecialchars($document['document_number']); ?></p>
                <p><strong>Folio Number:</strong> <?php echo $document['folio_number']; ?></p>
                <p><strong>Folder:</strong> <?php echo htmlspecialchars($document['folder_name']); ?></p>
                <p><strong>Status:</strong> <span class="status-badge status-<?php echo $document['status']; ?>"><?php echo $document['status']; ?></span></p>
                <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($document['submitter_name']); ?></p>
            </div>
        </div>
        
        <div class="content-area">
            <div class="document-content">
                <h3>Document Content</h3>
                <div class="content-body">
                    <?php if ($show_content): ?>
                        <?php echo nl2br(htmlspecialchars($document['content'])); ?>
                        <?php if ($document['file_path']): ?>
                            <p><a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-small" download>Download Attachment</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="confidential-notice">[Confidential Content - Access Token Required]</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="comments-section">
                <h3>Comments & Discussion</h3>
                <div class="comments-list">
                    <?php while ($comment = $comments->fetch_assoc()): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                <span class="comment-role">(<?php echo $comment['role']; ?>)</span>
                                <span class="comment-date"><?php echo $comment['created_at']; ?></span>
                            </div>
                            <div class="comment-body">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <?php if ($document['status'] !== 'closed' && ($user_role !== 'user' || $document['status'] !== 'closed')): ?>
                    <form method="POST" class="comment-form">
                        <textarea name="comment" rows="3" placeholder="Add your comment..." required></textarea>
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <?php if ($user_role === 'admin' && $document['status'] !== 'closed'): ?>
                <button onclick="showAssignModal()" class="btn btn-secondary">Assign to User</button>
                <button onclick="showReturnModal()" class="btn btn-warning">Return to Records</button>
                <button onclick="closeDocument()" class="btn btn-danger">Close Document</button>
            <?php endif; ?>
            
            <?php if ($user_role === 'records_officer' && $document['status'] === 'closed'): ?>
                <button onclick="reopenDocument()" class="btn btn-success">Reopen Document</button>
            <?php endif; ?>
            
            <a href="/filing_system/modules/users/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <!-- Assignment Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Assign Document</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="form-group">
                    <label>Assign to:</label>
                    <select name="assigned_to" required>
                        <?php
                        $users_query = "SELECT id, full_name, role FROM users WHERE role IN ('admin', 'user')";
                        $users_result = $conn->query($users_query);
                        while ($user = $users_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Assign</button>
            </form>
        </div>
    </div>
    
    <script src="/filing_system/assets/js/document.js"></script>
</body>
</html>