<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Clean URL by removing multiple slashes
 */
if (!function_exists('cleanUrl')) {
    function cleanUrl($url) {
        return preg_replace('#/+#', '/', $url);
    }
}

/**
 * Build clean URL with base URL
 */
if (!function_exists('buildUrl')) {
    function buildUrl($path) {
        global $base_url;
        if (!isset($base_url)) {
            $base_url = defined('BASE_URL') ? BASE_URL : '';
        }
        $base_url = rtrim($base_url, '/');
        $path = ltrim($path, '/');
        return cleanUrl($base_url . '/' . $path);
    }
}

/**
 * Generate unique document number
 */
function generateDocumentNumber($folder_id) {
    $conn = getConnection();
    $year = date('Y');
    $query = "SELECT COUNT(*) as count FROM documents WHERE folder_id = ? AND YEAR(created_at) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $folder_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    return sprintf("DOC-%s-%04d-%03d", $year, $folder_id, $count);
}

/**
 * Generate folio number for document in folder
 */
function generateFolioNumber($folder_id) {
    $conn = getConnection();
    $query = "SELECT MAX(folio_number) as max_folio FROM documents WHERE folder_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return ($row['max_folio'] ?? 0) + 1;
}

/**
 * Generate access token for confidential documents
 */
function generateAccessToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Get all documents in a folder
 */
function getFolderDocuments($folder_id, $order = 'DESC') {
    $conn = getConnection();
    $order = $order === 'DESC' ? 'DESC' : 'ASC';
    $query = "SELECT d.*, u.full_name as submitted_by_name 
              FROM documents d 
              LEFT JOIN users u ON d.submitted_by = u.id 
              WHERE d.folder_id = ? 
              ORDER BY d.folio_number $order";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Add comment to document
 */
function addComment($document_id, $user_id, $comment) {
    $conn = getConnection();
    $query = "INSERT INTO comments (document_id, user_id, comment_text) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $document_id, $user_id, $comment);
    
    if ($stmt->execute()) {
        // Log the action
        auditLog($user_id, 'added_comment', "Added comment to document ID: $document_id");
        return true;
    }
    return false;
}

/**
 * Update document status
 */
function updateDocumentStatus($document_id, $status, $user_id) {
    $conn = getConnection();
    $query = "UPDATE documents SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $document_id);
    
    if ($stmt->execute()) {
        auditLog($user_id, 'status_update', "Updated document ID: $document_id to status: $status");
        return true;
    }
    return false;
}

/**
 * Track workflow action
 */
function trackWorkflow($document_id, $from_user, $to_user, $action, $notes = null) {
    $conn = getConnection();
    $query = "INSERT INTO workflow_tracking (document_id, from_user, to_user, action, notes) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiss", $document_id, $from_user, $to_user, $action, $notes);
    
    if ($stmt->execute()) {
        auditLog($from_user, $action, "Document ID: $document_id" . ($notes ? " - $notes" : ""));
        return true;
    }
    return false;
}

/**
 * Get documents for a user based on role
 */
function getUserDocuments($user_id, $role) {
    $conn = getConnection();
    
    if ($role === 'records_officer') {
        $query = "SELECT d.*, f.name as folder_name 
                  FROM documents d 
                  JOIN folders f ON d.folder_id = f.id 
                  WHERE d.status NOT IN ('submitted_to_admin', 'in_review') 
                  ORDER BY d.created_at DESC";
        $stmt = $conn->prepare($query);
    } elseif ($role === 'admin') {
        $query = "SELECT d.*, f.name as folder_name 
                  FROM documents d 
                  JOIN folders f ON d.folder_id = f.id 
                  WHERE d.current_holder = ? OR d.status = 'submitted_to_admin'
                  ORDER BY d.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    } else {
        $query = "SELECT d.*, f.name as folder_name 
                  FROM documents d 
                  JOIN folders f ON d.folder_id = f.id 
                  WHERE d.submitted_by = ? 
                  ORDER BY d.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log user activity
 */
function auditLog($user_id, $action, $details = null) {
    $conn = getConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $query = "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    return $stmt->execute();
}

/**
 * Get system setting
 */
function getSystemSetting($key, $default = null) {
    $conn = getConnection();
    $query = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Update system setting
 */
function updateSystemSetting($key, $value) {
    $conn = getConnection();
    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
              ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

/**
 * Format file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get user by ID
 */
function getUserById($user_id) {
    $conn = getConnection();
    $query = "SELECT id, username, full_name, email, role, department FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Send email notification
 */
function sendEmail($to, $subject, $message) {
    $notification_enabled = getSystemSetting('notification_email', '1');
    if ($notification_enabled != '1') {
        return false;
    }
    
    $smtp_host = getSystemSetting('smtp_host', '');
    $from_email = getSystemSetting('system_email', 'noreply@example.com');
    
    $headers = "From: " . $from_email . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Create notification
 */
function createNotification($user_id, $title, $message, $link = null) {
    $conn = getConnection();
    $query = "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $title, $message, $link);
    return $stmt->execute();
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($user_id) {
    $conn = getConnection();
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($file_extension) {
    $allowed = getSystemSetting('allowed_file_types', 'pdf,doc,docx,txt,jpg,png');
    $allowed_array = array_map('trim', explode(',', $allowed));
    return in_array(strtolower($file_extension), $allowed_array);
}

/**
 * Get max upload size in bytes
 */
function getMaxUploadSize() {
    $size_mb = (int)getSystemSetting('max_upload_size', '10');
    return $size_mb * 1024 * 1024;
}

/**
 * Get items per page for pagination
 */
function getItemsPerPage() {
    return (int)getSystemSetting('items_per_page', '20');
}

/**
 * Format date according to system settings
 */
function formatDate($date, $format = null) {
    if (!$format) {
        $format = getSystemSetting('date_format', 'Y-m-d');
    }
    $timestamp = is_string($date) ? strtotime($date) : $date;
    return date($format, $timestamp);
}

/**
 * Log error
 */
function logError($message, $file = null, $line = null) {
    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " - ";
    if ($file) $log_entry .= "File: $file - ";
    if ($line) $log_entry .= "Line: $line - ";
    $log_entry .= $message . PHP_EOL;
    
    error_log($log_entry, 3, $log_dir . 'error.log');
}

/**
 * Create backup of database
 */
function createDatabaseBackup() {
    $backup_dir = __DIR__ . '/../assets/backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s',
        DB_USER,
        DB_PASS,
        DB_HOST,
        DB_NAME,
        $backup_file
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($backup_file)) {
        // Log backup
        $conn = getConnection();
        $query = "INSERT INTO backup_logs (backup_type, file_name, file_size, status) 
                  VALUES ('auto', ?, ?, 'success')";
        $stmt = $conn->prepare($query);
        $file_name = basename($backup_file);
        $file_size = filesize($backup_file);
        $stmt->bind_param("si", $file_name, $file_size);
        $stmt->execute();
        
        // Delete old backups (keep last 10)
        $backups = glob($backup_dir . 'backup_*.sql');
        if (count($backups) > 10) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $to_delete = array_slice($backups, 0, count($backups) - 10);
            foreach ($to_delete as $file) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'success';
        $class = $type === 'success' ? 'alert-success' : 'alert-error';
        echo '<div class="alert ' . $class . '">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}
?>