<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Confidential Documents';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Check permission - only records officer and super admin can view confidential documents
if ($user_role !== 'records_officer' && $user_role !== 'super_admin') {
    header('Location: ' . BASE_URL . '/modules/users/' . $user_role . '_dashboard.php');
    exit();
}

// Handle access token verification for confidential document
$access_granted = false;
$selected_document = null;
$access_error = '';

if (isset($_GET['view_doc']) && isset($_GET['token'])) {
    $doc_id = (int)$_GET['view_doc'];
    $token = $_GET['token'];
    
    // Verify token
    $verify_query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name
                     FROM documents d
                     JOIN folders f ON d.folder_id = f.id
                     JOIN users u ON d.submitted_by = u.id
                     WHERE d.id = ? AND d.access_token = ? AND (d.is_confidential = 1 OR f.is_confidential = 1)";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("is", $doc_id, $token);
    $verify_stmt->execute();
    $selected_document = $verify_stmt->get_result()->fetch_assoc();
    
    if ($selected_document) {
        $access_granted = true;
        // Log access
        $log_query = "INSERT INTO confidential_access_logs (document_id, user_id, access_token, ip_address) 
                      VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iiss", $doc_id, $user_id, $token, $ip);
        $log_stmt->execute();
    } else {
        $access_error = "Invalid or expired access token.";
    }
}

// Get all confidential folders
$confidential_folders_query = "SELECT f.*, c.name as category_name,
                                COUNT(DISTINCT d.id) as document_count,
                                COUNT(DISTINCT CASE WHEN d.is_confidential = 1 THEN d.id END) as confidential_docs
                                FROM folders f
                                LEFT JOIN categories c ON f.category_id = c.id
                                LEFT JOIN documents d ON f.id = d.folder_id
                                WHERE f.is_confidential = 1
                                GROUP BY f.id
                                ORDER BY f.name ASC";
$confidential_folders = $conn->query($confidential_folders_query);

// Get all confidential documents (not in confidential folders)
$confidential_docs_query = "SELECT d.*, f.name as folder_name, f.is_confidential as folder_confidential,
                            u.full_name as submitted_by_name
                            FROM documents d
                            JOIN folders f ON d.folder_id = f.id
                            JOIN users u ON d.submitted_by = u.id
                            WHERE d.is_confidential = 1 AND f.is_confidential = 0
                            ORDER BY d.created_at DESC";
$confidential_docs = $conn->query($confidential_docs_query);

// Get access logs
$access_logs_query = "SELECT cal.*, d.title as document_title, d.document_number, u.full_name as user_name
                      FROM confidential_access_logs cal
                      JOIN documents d ON cal.document_id = d.id
                      JOIN users u ON cal.user_id = u.id
                      ORDER BY cal.accessed_at DESC
                      LIMIT 50";
$access_logs = $conn->query($access_logs_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .confidential-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card h3 {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: bold;
    }
    
    /* Section Styles */
    .section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .section-header h3 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
    }
    
    .confidential-badge {
        background: #f44336;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
    }
    
    /* Folder Grid */
    .folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .folder-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 18px;
        transition: all 0.3s;
        background: #fff5f5;
        border-left: 4px solid #f44336;
    }
    
    .folder-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .folder-icon {
        font-size: 24px;
        color: #f44336;
        margin-bottom: 10px;
    }
    
    .folder-card h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #333;
    }
    
    .folder-number {
        font-family: monospace;
        font-size: 11px;
        color: #f44336;
        background: #ffe5e5;
        padding: 2px 8px;
        border-radius: 4px;
    }
    
    .folder-stats {
        display: flex;
        gap: 15px;
        margin: 12px 0;
        font-size: 12px;
        color: #666;
    }
    
    /* Documents Table */
    .documents-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .documents-table th,
    .documents-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .documents-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    
    .documents-table tr:hover {
        background: #f9f9f9;
    }
    
    .confidential-row {
        background: #fff5f5;
    }
    
    .token-display {
        font-family: monospace;
        font-size: 11px;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .token-display:hover {
        background: #e0e0e0;
    }
    
    /* Access Log Table */
    .log-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .log-table th,
    .log-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .log-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 11px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 50px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ddd;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    
    .modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 500px;
        max-width: 90%;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #999;
    }
    
    .close:hover {
        color: #333;
    }
    
    .token-box {
        background: #f0f4ff;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        word-break: break-all;
        font-family: monospace;
        font-size: 14px;
        margin: 15px 0;
    }
    
    @media (max-width: 768px) {
        .documents-table {
            display: block;
            overflow-x: auto;
        }
        
        .folders-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-lock"></i> Confidential Documents & Folders</h2>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Confidential Folders</h3>
            <div class="stat-number"><?php echo $confidential_folders->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Confidential Documents</h3>
            <div class="stat-number"><?php echo $confidential_docs->num_rows; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Access Logs</h3>
            <div class="stat-number"><?php echo $access_logs->num_rows; ?></div>
        </div>
    </div>
    
    <div class="confidential-container">
        <!-- Confidential Folders Section -->
        <div class="section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-folder-lock" style="color: #f44336;"></i>
                    Confidential Folders
                    <span class="confidential-badge">Restricted Access</span>
                </h3>
            </div>
            
            <?php if ($confidential_folders && $confidential_folders->num_rows > 0): ?>
                <div class="folders-grid">
                    <?php while ($folder = $confidential_folders->fetch_assoc()): ?>
                        <div class="folder-card">
                            <div class="folder-icon">
                                <i class="fas fa-folder-lock"></i>
                            </div>
                            <h4>
                                <?php echo htmlspecialchars($folder['name']); ?>
                                <span class="folder-number"><?php echo $folder['folder_number']; ?></span>
                            </h4>
                            <div class="folder-stats">
                                <span><i class="fas fa-file-alt"></i> <?php echo $folder['document_count']; ?> docs</span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name'] ?? 'Uncategorized'); ?></span>
                            </div>
                            <?php if ($folder['description']): ?>
                                <div style="font-size: 12px; color: #666; margin: 10px 0;">
                                    <?php echo htmlspecialchars(substr($folder['description'], 0, 80)); ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 15px;">
                                <a href="../folders/view.php?id=<?php echo $folder['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Folder
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No confidential folders found.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Confidential Documents Section -->
        <div class="section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-file-lock" style="color: #f44336;"></i>
                    Confidential Documents
                    <span class="confidential-badge">Token Required</span>
                </h3>
            </div>
            
            <?php if ($confidential_docs && $confidential_docs->num_rows > 0): ?>
                <table class="documents-table">
                    <thead>
                        <tr>
                            <th>Document #</th>
                            <th>Title</th>
                            <th>Folder</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Access Token</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = $confidential_docs->fetch_assoc()): ?>
                            <tr class="confidential-row">
                                <td><?php echo $doc['document_number']; ?></td>
                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td><?php echo htmlspecialchars($doc['folder_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['submitted_by_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                <td>
                                    <span class="token-display" onclick="copyToken('<?php echo $doc['access_token']; ?>')" title="Click to copy">
                                        <i class="fas fa-key"></i> <?php echo substr($doc['access_token'], 0, 16); ?>...
                                    </span>
                                </td>
                                <td>
                                    <button onclick="showAccessModal(<?php echo $doc['id']; ?>, '<?php echo $doc['access_token']; ?>')" class="btn btn-danger btn-sm">
                                        <i class="fas fa-lock-open"></i> Access
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No confidential documents found.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Access Logs Section -->
        <div class="section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-history"></i>
                    Recent Access Logs
                </h3>
            </div>
            
            <?php if ($access_logs && $access_logs->num_rows > 0): ?>
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Accessed By</th>
                            <th>Token Used</th>
                            <th>IP Address</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $access_logs->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['document_title']); ?></strong><br>
                                    <small><?php echo $log['document_number']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                <td><span class="token-display"><?php echo substr($log['access_token'], 0, 16); ?>...</span></td>
                                <td><?php echo $log['ip_address']; ?></td>
                                <td><?php echo date('M d, Y H:i:s', strtotime($log['accessed_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>No access logs recorded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Access Modal -->
<div id="accessModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-lock"></i> Confidential Document Access</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>This document requires an access token to view.</p>
            <div class="token-box" id="modalToken">
                Token will appear here
            </div>
            <p style="font-size: 13px; color: #666;">
                <i class="fas fa-info-circle"></i> 
                Share this token with authorized personnel only. The token is required to view the document.
            </p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="copyModalToken()" class="btn btn-primary">
                    <i class="fas fa-copy"></i> Copy Token
                </button>
                <a href="#" id="accessDocumentLink" class="btn btn-danger">
                    <i class="fas fa-lock-open"></i> Access Document
                </a>
                <button onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentDocId = null;
let currentToken = null;

function showAccessModal(docId, token) {
    currentDocId = docId;
    currentToken = token;
    document.getElementById('modalToken').innerHTML = '<i class="fas fa-key"></i> ' + token;
    document.getElementById('accessDocumentLink').href = '?view_doc=' + docId + '&token=' + encodeURIComponent(token);
    document.getElementById('accessModal').classList.add('show');
}

function closeModal() {
    document.getElementById('accessModal').classList.remove('show');
    currentDocId = null;
    currentToken = null;
}

function copyToken(token) {
    navigator.clipboard.writeText(token).then(function() {
        alert('Token copied to clipboard!');
    }, function() {
        alert('Failed to copy token. Please copy manually.');
    });
}

function copyModalToken() {
    if (currentToken) {
        navigator.clipboard.writeText(currentToken).then(function() {
            alert('Token copied to clipboard!');
        });
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}

// Display success message if document was accessed
<?php if ($access_granted && $selected_document): ?>
    setTimeout(function() {
        alert('Access granted! You can now view the confidential document.');
    }, 100);
<?php endif; ?>
</script>

<?php
include_once '../../includes/footer.php';
?>