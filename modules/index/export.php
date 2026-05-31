<?php
require_once '../../config/session.php';
requireLogin();
requireRole('records_officer');
require_once '../../includes/functions.php';

$conn = getConnection();

// Get all folders with document counts
$query = "SELECT 
    f.folder_number,
    f.name as folder_name,
    c.name as category,
    f.status,
    f.is_confidential,
    COUNT(DISTINCT d.id) as document_count,
    MAX(d.created_at) as last_activity,
    f.created_at
FROM folders f
LEFT JOIN categories c ON f.category_id = c.id
LEFT JOIN documents d ON f.id = d.folder_id
GROUP BY f.id
ORDER BY f.folder_number ASC";

$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="file_index_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Folder Number',
    'Folder Name',
    'Category',
    'Status',
    'Security Level',
    'Document Count',
    'Created Date',
    'Last Activity'
]);

// Add data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['folder_number'],
        $row['folder_name'],
        $row['category'] ?? 'Uncategorized',
        ucfirst($row['status']),
        $row['is_confidential'] ? 'Confidential' : 'Normal',
        $row['document_count'],
        date('Y-m-d', strtotime($row['created_at'])),
        $row['last_activity'] ? date('Y-m-d', strtotime($row['last_activity'])) : 'No activity'
    ]);
}

fclose($output);
exit();
?>