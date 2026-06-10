<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$last_check = isset($_POST['last_check']) ? (int)$_POST['last_check'] : 0;
$last_check_date = date('Y-m-d H:i:s', $last_check / 1000);

$conn = getConnection();
$notifications = [];

// 1. Check for new tasks assigned
$task_query = "SELECT t.*, u.full_name as assigned_by_name 
               FROM tasks t
               JOIN users u ON t.assigned_by = u.id
               WHERE t.assigned_to = ? 
               AND t.created_at > ?
               AND t.status = 'pending'
               ORDER BY t.created_at DESC
               LIMIT 10";
$stmt = $conn->prepare($task_query);
$stmt->bind_param("is", $user_id, $last_check_date);
$stmt->execute();
$tasks = $stmt->get_result();

while ($task = $tasks->fetch_assoc()) {
    $notifications[] = [
        'id' => $task['id'],
        'type' => 'task',
        'title' => 'New Task Assigned',
        'message' => "You have been assigned a new task: " . htmlspecialchars($task['title']),
        'url' => '../../modules/tasks/my_tasks.php',
        'icon' => '../../assets/images/task-icon.png',
        'requireInteraction' => $task['priority'] === 'urgent',
        'timestamp' => strtotime($task['created_at']) * 1000
    ];
}

// 2. Check for documents assigned for review (for admins)
if ($user_role === 'admin' || $user_role === 'super_admin') {
    $doc_query = "SELECT d.*, u.full_name as submitted_by_name 
                  FROM documents d
                  JOIN users u ON d.submitted_by = u.id
                  WHERE d.status = 'submitted_to_admin'
                  AND d.created_at > ?
                  ORDER BY d.created_at DESC
                  LIMIT 10";
    $stmt = $conn->prepare($doc_query);
    $stmt->bind_param("s", $last_check_date);
    $stmt->execute();
    $documents = $stmt->get_result();
    
    while ($doc = $documents->fetch_assoc()) {
        $notifications[] = [
            'id' => $doc['id'],
            'type' => 'document_review',
            'title' => 'Document Pending Review',
            'message' => "Document '" . htmlspecialchars($doc['title']) . "' requires your review",
            'url' => '../../modules/documents/view.php?id=' . $doc['id'],
            'icon' => '../../assets/images/document-icon.png',
            'requireInteraction' => false,
            'timestamp' => strtotime($doc['created_at']) * 1000
        ];
    }
}

// 3. Check for new comments on user's documents
$comment_query = "SELECT c.*, d.title as document_title, u.full_name as commenter_name
                  FROM comments c
                  JOIN documents d ON c.document_id = d.id
                  JOIN users u ON c.user_id = u.id
                  WHERE d.submitted_by = ?
                  AND c.user_id != ?
                  AND c.created_at > ?
                  ORDER BY c.created_at DESC
                  LIMIT 10";
$stmt = $conn->prepare($comment_query);
$stmt->bind_param("iis", $user_id, $user_id, $last_check_date);
$stmt->execute();
$comments = $stmt->get_result();

while ($comment = $comments->fetch_assoc()) {
    $notifications[] = [
        'id' => $comment['id'],
        'type' => 'comment',
        'title' => 'New Comment',
        'message' => htmlspecialchars($comment['commenter_name']) . " commented on '" . htmlspecialchars($comment['document_title']) . "'",
        'url' => '../../modules/documents/view.php?id=' . $comment['document_id'],
        'icon' => '../../assets/images/comment-icon.png',
        'requireInteraction' => false,
        'timestamp' => strtotime($comment['created_at']) * 1000
    ];
}

// 4. Check for overdue tasks
$overdue_query = "SELECT t.* 
                  FROM tasks t
                  WHERE t.assigned_to = ?
                  AND t.status != 'completed'
                  AND t.due_date < CURDATE()
                  AND t.due_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  ORDER BY t.due_date ASC";
$stmt = $conn->prepare($overdue_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue_tasks = $stmt->get_result();

while ($task = $overdue_tasks->fetch_assoc()) {
    $days_overdue = (strtotime('now') - strtotime($task['due_date'])) / (60 * 60 * 24);
    $notifications[] = [
        'id' => $task['id'],
        'type' => 'overdue',
        'title' => 'Task Overdue',
        'message' => "Task '" . htmlspecialchars($task['title']) . "' is " . round($days_overdue) . " days overdue",
        'url' => '../../modules/tasks/my_tasks.php',
        'icon' => '../../assets/images/urgent-icon.png',
        'requireInteraction' => true,
        'timestamp' => strtotime($task['due_date']) * 1000
    ];
}

// 5. Check for document status updates (for normal users)
if ($user_role === 'user') {
    $status_query = "SELECT d.*, w.action, w.created_at as action_date
                     FROM documents d
                     JOIN workflow_tracking w ON d.id = w.document_id
                     WHERE d.submitted_by = ?
                     AND w.created_at > ?
                     AND w.action IN ('approve', 'reject', 'return_to_records')
                     ORDER BY w.created_at DESC
                     LIMIT 10";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param("is", $user_id, $last_check_date);
    $stmt->execute();
    $status_updates = $stmt->get_result();
    
    while ($update = $status_updates->fetch_assoc()) {
        $action_text = $update['action'] === 'approve' ? 'approved' : ($update['action'] === 'reject' ? 'rejected' : 'returned');
        $notifications[] = [
            'id' => $update['id'],
            'type' => 'status_update',
            'title' => 'Document ' . ucfirst($action_text),
            'message' => "Your document '" . htmlspecialchars($update['title']) . "' has been " . $action_text,
            'url' => '../../modules/documents/view.php?id=' . $update['id'],
            'icon' => '../../assets/images/status-icon.png',
            'requireInteraction' => false,
            'timestamp' => strtotime($update['action_date']) * 1000
        ];
    }
}

// Sort notifications by timestamp (newest first)
usort($notifications, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Limit to 20 most recent
$notifications = array_slice($notifications, 0, 20);

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'count' => count($notifications)
]);
?>