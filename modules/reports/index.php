<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Reports Dashboard';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];

// Get report data
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Documents by status
$status_query = "SELECT status, COUNT(*) as count FROM documents 
                 WHERE DATE(created_at) BETWEEN ? AND ?
                 GROUP BY status";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$status_data = $stmt->get_result();

// Documents by category
$category_query = "SELECT c.name, COUNT(d.id) as count 
                   FROM categories c
                   LEFT JOIN folders f ON c.id = f.category_id
                   LEFT JOIN documents d ON f.id = d.folder_id
                   WHERE DATE(d.created_at) BETWEEN ? AND ? OR d.created_at IS NULL
                   GROUP BY c.id";
$stmt = $conn->prepare($category_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$category_data = $stmt->get_result();

// Monthly trends
$trends_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                 FROM documents 
                 WHERE DATE(created_at) BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month DESC";
$stmt = $conn->prepare($trends_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$trends_data = $stmt->get_result();

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .date-filter {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .date-filter .form-group {
        margin: 0;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        font-size: 12px;
        color: #666;
    }
    
    .form-group input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 20px;
    }
    
    .report-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .report-card.full-width {
        grid-column: 1 / -1;
    }
    
    .report-card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    
    canvas {
        max-width: 100%;
        height: auto;
        margin-bottom: 20px;
    }
    
    .report-data {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    
    .data-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 14px;
    }
    
    .data-row .label {
        font-weight: 500;
    }
    
    .data-row .value {
        color: #666;
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
    
    .btn-primary:hover {
        background-color: #2980b9;
    }
    
    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #7f8c8d;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        color: #999;
    }
    
    @media (max-width: 768px) {
        .reports-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-chart-bar"></i> Reports Dashboard</h2>
    
    <div class="filter-bar">
        <form method="GET" class="date-filter">
            <div class="form-group">
                <label>From Date:</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="form-group">
                <label>To Date:</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filter
            </button>
            <button type="button" onclick="exportReport()" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export to CSV
            </button>
        </form>
    </div>
    
    <div class="reports-grid">
        <div class="report-card">
            <h3><i class="fas fa-chart-pie"></i> Documents by Status</h3>
            <canvas id="statusChart" width="400" height="300"></canvas>
            <div class="report-data">
                <?php 
                $status_data->data_seek(0);
                while ($row = $status_data->fetch_assoc()): 
                ?>
                    <div class="data-row">
                        <span class="label"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>:</span>
                        <span class="value"><?php echo $row['count']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="report-card">
            <h3><i class="fas fa-chart-bar"></i> Documents by Category</h3>
            <canvas id="categoryChart" width="400" height="300"></canvas>
            <div class="report-data">
                <?php 
                $category_data->data_seek(0);
                while ($row = $category_data->fetch_assoc()): 
                ?>
                    <div class="data-row">
                        <span class="label"><?php echo htmlspecialchars($row['name']); ?>:</span>
                        <span class="value"><?php echo $row['count']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="report-card full-width">
            <h3><i class="fas fa-chart-line"></i> Monthly Trends</h3>
            <canvas id="trendsChart" width="800" height="300"></canvas>
            <div class="report-data">
                <?php 
                $trends_data->data_seek(0);
                while ($row = $trends_data->fetch_assoc()): 
                ?>
                    <div class="data-row">
                        <span class="label"><?php echo date('F Y', strtotime($row['month'] . '-01')); ?>:</span>
                        <span class="value"><?php echo $row['count']; ?> documents</span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?php
    $status_labels = [];
    $status_counts = [];
    $status_data->data_seek(0);
    while ($row = $status_data->fetch_assoc()) {
        $status_labels[] = ucfirst(str_replace('_', ' ', $row['status']));
        $status_counts[] = $row['count'];
    }
    echo json_encode(['labels' => $status_labels, 'counts' => $status_counts]);
?>;

if (statusData.labels.length > 0) {
    new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: statusData.labels,
            datasets: [{
                data: statusData.counts,
                backgroundColor: ['#3498db', '#f39c12', '#27ae60', '#e74c3c', '#95a5a6', '#9b59b6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
} else {
    statusCtx.fillStyle = '#ccc';
    statusCtx.fillRect(0, 0, 400, 300);
    statusCtx.fillStyle = '#666';
    statusCtx.font = '14px Arial';
    statusCtx.fillText('No data available', 150, 150);
}

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?php
    $category_labels = [];
    $category_counts = [];
    $category_data->data_seek(0);
    while ($row = $category_data->fetch_assoc()) {
        $category_labels[] = htmlspecialchars($row['name']);
        $category_counts[] = $row['count'];
    }
    echo json_encode(['labels' => $category_labels, 'counts' => $category_counts]);
?>;

if (categoryData.labels.length > 0) {
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: categoryData.labels,
            datasets: [{
                label: 'Number of Documents',
                data: categoryData.counts,
                backgroundColor: '#3498db'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
} else {
    categoryCtx.fillStyle = '#ccc';
    categoryCtx.fillRect(0, 0, 400, 300);
    categoryCtx.fillStyle = '#666';
    categoryCtx.font = '14px Arial';
    categoryCtx.fillText('No data available', 150, 150);
}

// Trends Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
const trendsData = <?php
    $trends_months = [];
    $trends_counts = [];
    $trends_data->data_seek(0);
    while ($row = $trends_data->fetch_assoc()) {
        $trends_months[] = date('M Y', strtotime($row['month'] . '-01'));
        $trends_counts[] = $row['count'];
    }
    echo json_encode(['months' => array_reverse($trends_months), 'counts' => array_reverse($trends_counts)]);
?>;

if (trendsData.months.length > 0) {
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendsData.months,
            datasets: [{
                label: 'Documents Submitted',
                data: trendsData.counts,
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
} else {
    trendsCtx.fillStyle = '#ccc';
    trendsCtx.fillRect(0, 0, 800, 300);
    trendsCtx.fillStyle = '#666';
    trendsCtx.font = '14px Arial';
    trendsCtx.fillText('No data available', 350, 150);
}

function exportReport() {
    const params = new URLSearchParams({
        date_from: '<?php echo $date_from; ?>',
        date_to: '<?php echo $date_to; ?>'
    });
    window.location.href = 'export.php?' + params.toString();
}
</script>

<?php
include_once '../../includes/footer.php';
?>