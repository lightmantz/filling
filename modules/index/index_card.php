<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$conn = getConnection();
$folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($folder_id <= 0) {
    die("Invalid folder ID");
}

// Get folder details
$folder_query = "SELECT f.*, c.name as category_name 
                 FROM folders f
                 LEFT JOIN categories c ON f.category_id = c.id
                 WHERE f.id = ?";
$stmt = $conn->prepare($folder_query);
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$folder = $stmt->get_result()->fetch_assoc();

if (!$folder) {
    die("Folder not found");
}

// Get documents in this folder
$documents_query = "SELECT d.*, u.full_name as submitted_by_name
                    FROM documents d
                    LEFT JOIN users u ON d.submitted_by = u.id
                    WHERE d.folder_id = ?
                    ORDER BY d.folio_number ASC";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param("i", $folder_id);
$stmt->execute();
$documents = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index Card - <?php echo htmlspecialchars($folder['name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .index-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .card-header h1 {
            margin-bottom: 5px;
        }
        
        .card-header p {
            opacity: 0.9;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h3 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: baseline;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .documents-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .documents-table th,
        .documents-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .documents-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-confidential {
            background: #f44336;
            color: white;
        }
        
        .card-footer {
            background: #f8f9fa;
            padding: 15px 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        
        .print-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background: #3498db;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .print-btn:hover {
            background: #2980b9;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .print-btn {
                display: none;
            }
            .index-card {
                box-shadow: none;
            }
            .card-header {
                background: #667eea;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="index-card">
        <div class="card-header">
            <h1><i class="fas fa-folder-open"></i> FILE INDEX CARD</h1>
            <p>Records Management System</p>
        </div>
        
        <div class="card-body">
            <div class="info-section">
                <h3>Folder Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Folder Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($folder['folder_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Folder Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($folder['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Category:</span>
                        <span class="info-value"><?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><?php echo ucfirst($folder['status']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Security Level:</span>
                        <span class="info-value">
                            <?php if ($folder['is_confidential']): ?>
                                <span class="badge badge-confidential">CONFIDENTIAL</span>
                            <?php else: ?>
                                Normal
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Created On:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($folder['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($folder['description']): ?>
                <div class="info-section">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($folder['description'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="info-section">
                <h3>Document Index (Folio Order)</h3>
                <?php if ($documents->num_rows > 0): ?>
                    <table class="documents-table">
                        <thead>
                            <tr>
                                <th>Folio #</th>
                                <th>Document Number</th>
                                <th>Title</th>
                                <th>Submitted By</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $documents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $doc['folio_number']; ?></td>
                                    <td><?php echo $doc['document_number']; ?></td>
                                    <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                    <td><?php echo ucfirst($doc['status']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No documents in this folder.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-footer">
            <p>Generated on: <?php echo date('F d, Y H:i:s'); ?></p>
            <p>This is a system-generated index card. For official use only.</p>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Index Card
    </button>
    
    <script>
        // Auto-print option (optional)
        if (window.location.hash === '#print') {
            window.print();
        }
    </script>
</body>
</html>