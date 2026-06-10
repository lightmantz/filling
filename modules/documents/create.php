<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Submit Document';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_department_id = $_SESSION['department_id'] ?? null;
$error = '';
$success = '';

// Get folders for dropdown (only show folders user has access to)
if ($user_role === 'super_admin' || $user_role === 'records_officer') {
    $folders_query = "SELECT f.*, c.name as category_name, 
                      d.name as department_name
                      FROM folders f
                      LEFT JOIN categories c ON f.category_id = c.id
                      LEFT JOIN departments d ON f.department_id = d.id
                      WHERE f.status = 'active'
                      ORDER BY f.name ASC";
    $folders = $conn->query($folders_query);
} else {
    // Normal users only see folders from their department
    $folders_query = "SELECT f.*, c.name as category_name,
                      d.name as department_name
                      FROM folders f
                      LEFT JOIN categories c ON f.category_id = c.id
                      LEFT JOIN departments d ON f.department_id = d.id
                      WHERE f.status = 'active' 
                      AND (f.department_id = ? OR f.visibility_type = 'public')
                      ORDER BY f.name ASC";
    $stmt = $conn->prepare($folders_query);
    $stmt->bind_param("i", $user_department_id);
    $stmt->execute();
    $folders = $stmt->get_result();
}

// Get departments for document assignment (for super_admin and records_officer)
$departments = null;
if ($user_role === 'super_admin' || $user_role === 'records_officer') {
    $departments = $conn->query("SELECT id, name, dept_code FROM departments WHERE is_active = 1 ORDER BY name");
}

$selected_folder = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder_id = $_POST['folder_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $visibility_type = $_POST['visibility_type'];
    $is_confidential = ($visibility_type === 'confidential') ? 1 : 0;
    
    // Set department based on role
    if ($user_role === 'super_admin' || $user_role === 'records_officer') {
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    } else {
        $department_id = $user_department_id;
    }
    
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
    
    // Set initial status based on visibility
    $status = ($visibility_type === 'public') ? 'submitted' : 'private';
    
    $query = "INSERT INTO documents (document_number, folio_number, folder_id, title, content, file_path, 
              submitted_by, is_confidential, access_token, visibility_type, department_id, status, current_holder) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siisssisssis", $document_number, $folio_number, $folder_id, $title, $content, 
                      $file_path, $user_id, $is_confidential, $access_token, $visibility_type, $department_id, $status);
    
    if ($stmt->execute()) {
        $doc_id = $stmt->insert_id;
        
        // If public, notify records officer
        if ($visibility_type === 'public') {
            createNotification(null, 'New Public Document', "A new public document '$title' has been submitted for processing.", "modules/documents/view.php?id=$doc_id");
        }
        
        // If confidential, store the token in session for display
        if ($visibility_type === 'confidential') {
            $_SESSION['last_access_token'] = $access_token;
            $success = "Document submitted successfully! Document #: $document_number<br>
                        <strong>Access Token:</strong> <code>$access_token</code><br>
                        <small>Save this token. It will be required to access this confidential document.</small>";
        } else {
            $success = "Document submitted successfully! Document #: $document_number";
        }
        
        // Track workflow
        trackWorkflow($doc_id, $user_id, null, 'submit', "Document submitted as $visibility_type");
        
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
        margin: 0 auto;
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
    
    .form-group label.required:after {
        content: '*';
        color: #e74c3c;
        margin-left: 4px;
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
    
    /* Visibility Options */
    .visibility-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 10px;
    }
    
    .visibility-card {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }
    
    .visibility-card:hover {
        border-color: #667eea;
        transform: translateY(-2px);
    }
    
    .visibility-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .visibility-card input {
        display: none;
    }
    
    .visibility-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .visibility-title {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .visibility-desc {
        font-size: 11px;
        color: #666;
    }
    
    .visibility-private .visibility-icon { color: #3498db; }
    .visibility-confidential .visibility-icon { color: #e74c3c; }
    .visibility-public .visibility-icon { color: #27ae60; }
    
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
    
    .token-box {
        background: #f0f4ff;
        padding: 10px;
        border-radius: 5px;
        font-family: monospace;
        margin-top: 10px;
        word-break: break-all;
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
    
    .document-number-preview {
        background: #f0f7ff;
        border: 1px solid #cce5ff;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 20px;
    }
    
    .document-number-preview label {
        font-weight: 600;
        color: #004085;
        margin-bottom: 5px;
    }
    
    .preview-value {
        font-family: monospace;
        font-size: 16px;
        color: #004085;
    }
    
    @media (max-width: 768px) {
        .visibility-options {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .btn {
            text-align: center;
        }
    }
</style>

<div class="content-wrapper">
    <h2>Submit New Document</h2>
    
    <div class="form-container">
        <div class="document-number-preview">
            <label><i class="fas fa-info-circle"></i> Document Number Format:</label>
            <div class="preview-value">
                PF/[Folio]/[Day]/[Month]/[Year]
            </div>
            <small>Example: PF/1/15/05/2026</small>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="required">Select Folder</label>
                <select name="folder_id" required>
                    <option value="">Select Folder</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <option value="<?php echo $folder['id']; ?>" <?php echo $selected_folder == $folder['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($folder['name']); ?> 
                            (<?php echo htmlspecialchars($folder['category_name']); ?>)
                            <?php if ($folder['department_name']): ?>
                                - <?php echo htmlspecialchars($folder['department_name']); ?>
                            <?php endif; ?>
                            <?php if ($folder['visibility_type'] == 'confidential'): ?>
                                🔒 Confidential
                            <?php elseif ($folder['visibility_type'] == 'private'): ?>
                                🔐 Private
                            <?php elseif ($folder['visibility_type'] == 'public'): ?>
                                🌐 Public
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if ($user_role === 'super_admin' || $user_role === 'records_officer'): ?>
                <div class="form-group">
                    <label>Department (Optional)</label>
                    <select name="department_id">
                        <option value="">No Department (Global)</option>
                        <?php 
                        if ($departments) {
                            while ($dept = $departments->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?> (<?php echo $dept['dept_code']; ?>)
                            </option>
                        <?php endwhile; } ?>
                    </select>
                    <small>Assign document to a specific department</small>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="required">Document Title</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="required">Visibility Type</label>
                <div class="visibility-options">
                    <label class="visibility-card visibility-private <?php echo (!isset($_POST['visibility_type']) || $_POST['visibility_type'] == 'private') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="private" <?php echo (!isset($_POST['visibility_type']) || $_POST['visibility_type'] == 'private') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-lock"></i></div>
                        <div class="visibility-title">Private</div>
                        <div class="visibility-desc">Accessible only within your department</div>
                    </label>
                    <label class="visibility-card visibility-confidential <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'confidential') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="confidential" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'confidential') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="visibility-title">Confidential</div>
                        <div class="visibility-desc">Requires secret code for access</div>
                    </label>
                    <label class="visibility-card visibility-public <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'public') ? 'selected' : ''; ?>">
                        <input type="radio" name="visibility_type" value="public" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] == 'public') ? 'checked' : ''; ?>>
                        <div class="visibility-icon"><i class="fas fa-globe"></i></div>
                        <div class="visibility-title">Public</div>
                        <div class="visibility-desc">Submitted to Records Office for action</div>
                    </label>
                </div>
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
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Document</button>
                <a href="<?php echo $base_url; ?>/modules/users/<?php echo $user_role; ?>_dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Visibility card selection
document.querySelectorAll('.visibility-card').forEach(card => {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        
        document.querySelectorAll('.visibility-card').forEach(c => {
            c.classList.remove('selected');
        });
        this.classList.add('selected');
    });
});
</script>

<?php
include_once '../../includes/footer.php';
?>