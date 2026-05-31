<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$conn = getConnection();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Get all documents within date range
$query = "SELECT 
    d.document_number,
    d.title,
    f.name as folder_name,
    c.name as category,
    u.full_name as submitted_by,
    d.status,
    d.created_at,
    d.updated_at
FROM documents d
JOIN folders f ON d.folder_id = f.id
LEFT JOIN categories c ON f.category_id = c.id
JOIN users u ON d.submitted_by = u.id
WHERE DATE(d.created_at) BETWEEN ? AND ?
ORDER BY d.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $date_from . '_to_' . $date_to . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Add headers
fputcsv($output, [
    'Document Number',
    'Title',
    'Folder',
    'Category',
    'Submitted By',
    'Status',
    'Created Date',
    'Last Updated'
]);

// Add data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['document_number'],
        $row['title'],
        $row['folder_name'],
        $row['category'] ?? 'Uncategorized',
        $row['submitted_by'],
        ucfirst(str_replace('_', ' ', $row['status'])),
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        $row['updated_at'] ? date('Y-m-d H:i:s', strtotime($row['updated_at'])) : 'N/A'
    ]);
}

fclose($output);
exit();
?>