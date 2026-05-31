<?php
require_once '../../config/session.php';
requireLogin();
requireRole('admin');

$file = $_GET['file'] ?? '';

if (empty($file)) {
    die("No file specified");
}

$backup_dir = '../../assets/backups/';
$file_path = $backup_dir . basename($file);

if (file_exists($file_path)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit();
} else {
    die("File not found");
}
?>