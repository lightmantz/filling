<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Edit Document';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Allow records_officer AND super_admin
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($document_id <= 0) {
    header('Location: all_documents.php');
    exit();
}

// Get document details
$query = "SELECT d.*, f.name as folder_name 
          FROM documents d
          JOIN folders f ON d.folder_id = f.id
          WHERE d.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();

if (!$document) {
    header('Location: all_documents.php');
    exit();
}

// Get folders for dropdown
$folders_query = "SELECT id, name FROM folders WHERE status = 'active' ORDER BY name";
$folders = $conn->query($folders_query);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $folder_id = $_POST['folder_id'];
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    
    if (empty($title)) {
        $error = "Document title is required.";
    } else {
        $update_query = "UPDATE documents SET title = ?, content = ?, folder_id = ?, is_confidential = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssiii", $title, $content, $folder_id, $is_confidential, $document_id);
        
        if ($update_stmt->execute()) {
            auditLog($user_id, 'edit_document', "Edited document: {$document['document_number']} - $title");
            $success = "Document updated successfully!";
            
            // Refresh document data
            $stmt->execute();
            $document = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update document.";
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .edit-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .document-info {
        background: #f0f7ff;
        border: 1px solid #cce5ff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .document-info p {
        margin: 5px 0;
        font-size: 13px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group label.required:after {
        content: '*';
        color: #e74c3c;
        margin-left: 4px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-group input {
        width: auto;
    }
    
    .checkbox-group label {
        margin: 0;
        font-weight: normal;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .btn-group {
        display: flex;
        gap: 15px;
        margin-top: 25px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #7f8c8d;
    }
    
    .file-attachment {
        background: #f8f9fa;
        border: 1px dashed #ddd;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-edit"></i> Edit Document</h2>
    
    <div class="edit-container">
        <div class="document-info">
            <p><strong>Document Number:</strong> <?php echo $document['document_number']; ?></p>
            <p><strong>Folio Number:</strong> <?php echo $document['folio_number']; ?></p>
            <p><strong>Current Status:</strong> 
                <span class="status-badge status-<?php echo $document['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $document['status'])); ?>
                </span>
            </p>
            <p><strong>Submitted:</strong> <?php echo date('F d, Y H:i', strtotime($document['created_at'])); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="required">Document Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Folder</label>
                <select name="folder_id">
                    <option value="<?php echo $document['folder_id']; ?>"><?php echo htmlspecialchars($document['folder_name']); ?> (Current)</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <?php if ($folder['id'] != $document['folder_id']): ?>
                            <option value="<?php echo $folder['id']; ?>">
                                <?php echo htmlspecialchars($folder['name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </select>
                <small>Leave as is to keep in current folder</small>
            </div>
            
            <div class="form-group">
                <label>Document Content</label>
                <textarea name="content" rows="12"><?php echo htmlspecialchars($document['content']); ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_confidential" id="is_confidential" value="1" <?php echo $document['is_confidential'] ? 'checked' : ''; ?>>
                <label for="is_confidential"><i class="fas fa-lock"></i> Mark as Confidential Document</label>
            </div>
            
            <?php if ($document['file_path']): ?>
                <div class="file-attachment">
                    <i class="fas fa-paperclip"></i> Current Attachment: 
                    <a href="<?php echo $base_url . '/' . $document['file_path']; ?>" target="_blank">View File</a>
                </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="view.php?id=<?php echo $document_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>