<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Submit Document';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$error = '';
$success = '';

// Get folders for dropdown
$folders_query = "SELECT f.*, c.name as category_name 
                  FROM folders f
                  LEFT JOIN categories c ON f.category_id = c.id
                  WHERE f.status = 'active'";
$folders = $conn->query($folders_query);

$selected_folder = $_GET['folder_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder_id = $_POST['folder_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    
    // Generate document number and folio number
    $document_number = generateDocumentNumber($folder_id);
    $folio_number = generateFolioNumber($folder_id);
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = $document_number . '_' . time() . '.' . $file_ext;
        $file_path = 'assets/uploads/' . $file_name;
        
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $file_name)) {
            $error = "Failed to upload file";
        }
    }
    
    // Generate access token for confidential documents
    $access_token = null;
    if ($is_confidential) {
        $access_token = generateAccessToken();
    }
    
    $query = "INSERT INTO documents (document_number, folio_number, folder_id, title, content, file_path, 
              submitted_by, is_confidential, access_token, status, current_holder) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NULL)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siisssiss", $document_number, $folio_number, $folder_id, $title, $content, 
                      $file_path, $user_id, $is_confidential, $access_token);
    
    if ($stmt->execute()) {
        $doc_id = $stmt->insert_id;
        $success = "Document submitted successfully! Document #: " . $document_number;
        
        // Track workflow
        trackWorkflow($doc_id, $user_id, null, 'submit', 'Document submitted by user');
        
        // Clear form
        $_POST = array();
    } else {
        $error = "Failed to submit document. Please try again.";
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .form-container {
        max-width: 800px;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    textarea {
        font-family: inherit;
        resize: vertical;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn {
        display: inline-block;
        padding: 10px 20px;
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
    
    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #7f8c8d;
    }
    
    small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 12px;
    }
</style>

<div class="content-wrapper">
    <h2>Submit New Document</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select Folder *</label>
                <select name="folder_id" required>
                    <option value="">Select Folder</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <option value="<?php echo $folder['id']; ?>" <?php echo $selected_folder == $folder['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($folder['name']); ?> 
                            (<?php echo htmlspecialchars($folder['category_name']); ?>)
                            <?php echo $folder['is_confidential'] ? ' - Confidential' : ''; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Document Title *</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Document Content</label>
                <textarea name="content" rows="10" placeholder="Enter document content here..."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Attachment (Optional)</label>
                <input type="file" name="attachment">
                <small>Supported formats: PDF, DOC, DOCX, TXT, JPG, PNG (Max: 10MB)</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_confidential" <?php echo isset($_POST['is_confidential']) ? 'checked' : ''; ?>>
                    Mark as Confidential Document
                </label>
                <small>If marked confidential, an access token will be generated and shared with the recipient.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Document</button>
                <a href="<?php echo $base_url; ?>/modules/users/<?php echo $user_role; ?>_dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>