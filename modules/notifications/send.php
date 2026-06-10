<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$user_id = $_SESSION['user_id'];
$title = $data['title'] ?? '';
$message = $data['message'] ?? '';
$recipient_id = $data['recipient_id'] ?? null;
$url = $data['url'] ?? '';
$type = $data['type'] ?? 'custom';

if (empty($title) || empty($message)) {
    echo json_encode(['error' => 'Missing title or message']);
    exit();
}

$conn = getConnection();

// Store notification in database for later retrieval
$insert_query = "INSERT INTO notifications (user_id, title, message, link, type, is_read, created_at) 
                 VALUES (?, ?, ?, ?, ?, 0, NOW())";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("issss", $recipient_id, $title, $message, $url, $type);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Notification sent'
]);
?>