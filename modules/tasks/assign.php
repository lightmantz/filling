<?php
require_once '../../config/session.php';
requireLogin();
requireRole('super_admin');
require_once '../../includes/functions.php';

$page_title = 'Assign Task';
$base_url = '../../';

$conn = getConnection();
$error = '';
$success = '';

// Generate task number
function generateTaskNumber() {
    $prefix = 'TASK';
    $year = date('Y');
    $month = date('m');
    $random = rand(1000, 9999);
    return sprintf("%s-%s-%s-%d", $prefix, $year, $month, $random);
}

// Get users for assignment (excluding super admin)
$users_query = "SELECT id, full_name, role, department FROM users WHERE role IN ('records_officer', 'admin', 'user') AND is_active = 1 ORDER BY full_name";
$users = $conn->query($users_query);

// Get documents for reference
$documents_query = "SELECT id, document_number, title FROM documents ORDER BY created_at DESC LIMIT 100";
$documents = $conn->query($documents_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = $_POST['assigned_to'];
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];
    $document_id = !empty($_POST['document_id']) ? $_POST['document_id'] : null;
    
    // Validation
    if (empty($title) || empty($assigned_to) || empty($due_date)) {
        $error = 'Please fill all required fields.';
    } else {
        $task_number = generateTaskNumber();
        $assigned_by = $_SESSION['user_id'];
        
        $query = "INSERT INTO tasks (task_number, title, description, assigned_by, assigned_to, document_id, priority, due_date, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiisss", $task_number, $title, $description, $assigned_by, $assigned_to, $document_id, $priority, $due_date);
        
        if ($stmt->execute()) {
            $task_id = $stmt->insert_id;
            
            // Create notification for assigned user
            createNotification($assigned_to, 'New Task Assigned', "You have been assigned a new task: $title", "tasks/my_tasks.php");
            
            // Log activity
            auditLog($_SESSION['user_id'], 'assign_task', "Assigned task: $title to user ID: $assigned_to");
            
            $success = "Task assigned successfully! Task #: $task_number";
            
            // Clear form
            $_POST = array();
        } else {
            $error = 'Failed to assign task. Please try again.';
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .assign-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .form-group {
        margin-bottom: 25px;
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
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .priority-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .priority-low { background: #27ae60; color: white; }
    .priority-medium { background: #3498db; color: white; }
    .priority-high { background: #f39c12; color: white; }
    .priority-urgent { background: #e74c3c; color: white; }
    
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
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        width: 100%;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .task-number-preview {
        background: #f0f7ff;
        border: 1px solid #cce5ff;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
    }
    
    .preview-label {
        font-size: 12px;
        color: #004085;
        margin-bottom: 5px;
    }
    
    .preview-value {
        font-family: monospace;
        font-size: 16px;
        font-weight: bold;
        color: #004085;
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-tasks"></i> Assign New Task</h2>
    
    <div class="assign-container">
        <div class="task-number-preview">
            <div class="preview-label">Task Number (Auto-generated):</div>
            <div class="preview-value" id="taskNumberPreview"><?php echo generateTaskNumber(); ?></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="required">Task Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required placeholder="Enter task title">
            </div>
            
            <div class="form-group">
                <label>Task Description</label>
                <textarea name="description" rows="5" placeholder="Describe the task in detail..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="required">Assign To</label>
                <select name="assigned_to" required>
                    <option value="">Select User</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>)
                            <?php echo $user['department'] ? ' - ' . htmlspecialchars($user['department']) : ''; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Related Document (Optional)</label>
                <select name="document_id">
                    <option value="">None</option>
                    <?php while ($doc = $documents->fetch_assoc()): ?>
                        <option value="<?php echo $doc['id']; ?>" <?php echo (isset($_POST['document_id']) && $_POST['document_id'] == $doc['id']) ? 'selected' : ''; ?>>
                            <?php echo $doc['document_number']; ?> - <?php echo htmlspecialchars(substr($doc['title'], 0, 50)); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="required">Priority</label>
                <select name="priority" required>
                    <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="required">Due Date</label>
                <input type="date" name="due_date" value="<?php echo htmlspecialchars($_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'))); ?>" required>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Assign Task
            </button>
        </form>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>