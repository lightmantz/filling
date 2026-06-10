<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Index Reports';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Allow records_officer AND super_admin
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

// Get report data
$report_type = $_GET['type'] ?? 'summary';

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .report-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .btn-group {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        display: inline-block;
        padding: 8px 16px;
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
    
    .btn-primary.active {
        background-color: #2980b9;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
        margin-top: 10px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .data-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
</style>

<div class="content-wrapper">
    <h2>Index Reports</h2>
    
    <div class="report-container">
        <div class="report-header">
            <h3>Report Type</h3>
            <div class="btn-group">
                <a href="?type=summary" class="btn btn-primary <?php echo $report_type == 'summary' ? 'active' : ''; ?>">
                    Summary
                </a>
                <a href="?type=category" class="btn btn-primary <?php echo $report_type == 'category' ? 'active' : ''; ?>">
                    By Category
                </a>
                <a href="?type=status" class="btn btn-primary <?php echo $report_type == 'status' ? 'active' : ''; ?>">
                    By Status
                </a>
            </div>
        </div>
        
        <?php if ($report_type == 'summary'): ?>
            <?php
            $total_folders = $conn->query("SELECT COUNT(*) as count FROM folders")->fetch_assoc()['count'];
            $total_docs = $conn->query("SELECT COUNT(*) as count FROM documents")->fetch_assoc()['count'];
            $confidential = $conn->query("SELECT COUNT(*) as count FROM folders WHERE is_confidential = 1")->fetch_assoc()['count'];
            $active = $conn->query("SELECT COUNT(*) as count FROM folders WHERE status = 'active'")->fetch_assoc()['count'];
            $archived = $conn->query("SELECT COUNT(*) as count FROM folders WHERE status = 'archived'")->fetch_assoc()['count'];
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Folders</h3>
                    <div class="stat-number"><?php echo $total_folders; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Documents</h3>
                    <div class="stat-number"><?php echo $total_docs; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Confidential Folders</h3>
                    <div class="stat-number"><?php echo $confidential; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Folders</h3>
                    <div class="stat-number"><?php echo $active; ?></div>
                </div>
            </div>
        <?php elseif ($report_type == 'category'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Number of Folders</th>
                        <th>Total Documents</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cat_query = "SELECT c.name, COUNT(DISTINCT f.id) as folder_count, 
                                  COUNT(d.id) as doc_count, MAX(d.updated_at) as last_updated
                                  FROM categories c
                                  LEFT JOIN folders f ON c.id = f.category_id
                                  LEFT JOIN documents d ON f.id = d.folder_id
                                  GROUP BY c.id";
                    $cats = $conn->query($cat_query);
                    while ($cat = $cats->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td><?php echo $cat['folder_count']; ?></td>
                            <td><?php echo $cat['doc_count']; ?></td>
                            <td><?php echo $cat['last_updated'] ? date('M d, Y', strtotime($cat['last_updated'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php elseif ($report_type == 'status'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Number of Folders</th>
                        <th>Total Documents</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $status_query = "SELECT status, COUNT(*) as folder_count, 
                                    (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id) as doc_count
                                    FROM folders f
                                    GROUP BY status";
                    $statuses = $conn->query($status_query);
                    while ($status = $statuses->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo ucfirst($status['status']); ?></td>
                            <td><?php echo $status['folder_count']; ?></td>
                            <td><?php echo $status['doc_count']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>