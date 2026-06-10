<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'My Tasks';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get status filter
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

// Build query
$query = "SELECT t.*, 
          u_assigned_by.full_name as assigned_by_name,
          u_assigned_to.full_name as assigned_to_name,
          d.document_number, d.title as document_title
          FROM tasks t
          LEFT JOIN users u_assigned_by ON t.assigned_by = u_assigned_by.id
          LEFT JOIN users u_assigned_to ON t.assigned_to = u_assigned_to.id
          LEFT JOIN documents d ON t.document_id = d.id
          WHERE t.assigned_to = ?";

$params = [$user_id];
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($priority_filter !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

$query .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END ASC,
            t.due_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    
    $update_query = "UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sii", $new_status, $task_id, $user_id);
    
    if ($update_stmt->execute()) {
        if ($new_status === 'completed') {
            $complete_query = "UPDATE tasks SET completed_at = NOW() WHERE id = ?";
            $complete_stmt = $conn->prepare($complete_query);
            $complete_stmt->bind_param("i", $task_id);
            $complete_stmt->execute();
        }
        
        auditLog($user_id, 'update_task_status', "Updated task ID: $task_id to status: $new_status");
        header("Location: my_tasks.php?success=1");
        exit();
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .filters {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .filter-group label {
        font-weight: 500;
        color: #666;
    }
    
    .filter-group select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    
    .tasks-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .task-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border-left: 4px solid;
    }
    
    .task-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.12);
    }
    
    .task-card.urgent { border-left-color: #e74c3c; }
    .task-card.high { border-left-color: #f39c12; }
    .task-card.medium { border-left-color: #3498db; }
    .task-card.low { border-left-color: #27ae60; }
    .task-card.completed { opacity: 0.7; background: #f8f9fa; }
    
    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .task-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    
    .task-number {
        font-family: monospace;
        font-size: 12px;
        color: #667eea;
        background: #f0f4ff;
        padding: 4px 10px;
        border-radius: 5px;
    }
    
    .priority-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .priority-low { background: #27ae60; color: white; }
    .priority-medium { background: #3498db; color: white; }
    .priority-high { background: #f39c12; color: white; }
    .priority-urgent { background: #e74c3c; color: white; }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .status-pending { background: #95a5a6; color: white; }
    .status-in_progress { background: #3498db; color: white; }
    .status-completed { background: #27ae60; color: white; }
    .status-cancelled { background: #e74c3c; color: white; }
    
    .task-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 15px 0;
        font-size: 13px;
        color: #666;
    }
    
    .task-meta i {
        width: 18px;
        color: #667eea;
    }
    
    .task-description {
        color: #555;
        line-height: 1.5;
        margin: 15px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .task-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
    }
    
    .btn-success {
        background: #27ae60;
        color: white;
    }
    
    .btn-warning {
        background: #f39c12;
        color: white;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: white;
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
    
    .btn-sm {
        padding: 5px 12px;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: #ddd;
    }
    
    @media (max-width: 768px) {
        .task-header {
            flex-direction: column;
        }
        .task-meta {
            flex-direction: column;
            gap: 8px;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-tasks"></i> My Tasks</h2>
    
    <div class="filters">
        <div class="filter-group">
            <label>Status:</label>
            <select id="statusFilter" onchange="applyFilters()">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Priority:</label>
            <select id="priorityFilter" onchange="applyFilters()">
                <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        
        <div class="filter-group">
            <a href="my_tasks.php" class="btn btn-secondary btn-sm">Clear Filters</a>
        </div>
    </div>
    
    <div class="tasks-list">
        <?php if ($tasks && $tasks->num_rows > 0): ?>
            <?php while ($task = $tasks->fetch_assoc()): 
                $is_overdue = ($task['due_date'] < date('Y-m-d') && $task['status'] != 'completed');
                $days_left = ceil((strtotime($task['due_date']) - time()) / (60 * 60 * 24));
            ?>
                <div class="task-card <?php echo $task['priority']; ?> <?php echo $task['status']; ?>">
                    <div class="task-header">
                        <div>
                            <span class="task-title"><?php echo htmlspecialchars($task['title']); ?></span>
                            <span class="task-number">#<?php echo $task['task_number']; ?></span>
                        </div>
                        <div>
                            <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                <i class="fas fa-flag"></i> <?php echo ucfirst($task['priority']); ?>
                            </span>
                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="task-meta">
                        <span><i class="fas fa-user"></i> Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?></span>
                        <span><i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                        <?php if ($is_overdue): ?>
                            <span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Overdue by <?php echo abs($days_left); ?> days</span>
                        <?php elseif ($days_left <= 3 && $days_left > 0 && $task['status'] != 'completed'): ?>
                            <span style="color: #f39c12;"><i class="fas fa-clock"></i> Due in <?php echo $days_left; ?> days</span>
                        <?php endif; ?>
                        <?php if ($task['document_number']): ?>
                            <span><i class="fas fa-file-alt"></i> Related Doc: <?php echo $task['document_number']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <div class="task-description">
                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="task-actions">
                        <?php if ($task['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                    <i class="fas fa-play"></i> Start Task
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($task['status'] == 'in_progress'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Mark Complete
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($task['status'] != 'completed' && $task['status'] != 'cancelled'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this task?')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($task['document_id']): ?>
                            <a href="../documents/view.php?id=<?php echo $task['document_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> View Document
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Tasks Found</h3>
                <p>You have no tasks assigned at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const priority = document.getElementById('priorityFilter').value;
    window.location.href = `my_tasks.php?status=${status}&priority=${priority}`;
}
</script>

<?php
include_once '../../includes/footer.php';
?>