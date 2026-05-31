<?php
require_once '../../config/session.php';
requireLogin();
requireRole('admin'); // Only admin can access settings
require_once '../../includes/functions.php';

$page_title = 'System Settings';
$base_url = '../../';

$conn = getConnection();
$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            
            // Handle boolean values
            if ($value === 'on' || $value === '1') {
                $value = '1';
            } elseif ($value === 'off' || $value === '0') {
                $value = '0';
            }
            
            $update_query = "INSERT INTO settings (setting_key, setting_value) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sss", $setting_key, $value, $value);
            $stmt->execute();
        }
    }
    
    // Log the action
    $log_query = "INSERT INTO audit_logs (user_id, action, details, ip_address) 
                  VALUES (?, 'settings_updated', 'System settings were updated', ?)";
    $stmt = $conn->prepare($log_query);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("is", $_SESSION['user_id'], $ip);
    $stmt->execute();
    
    $success = "Settings updated successfully!";
}

// Handle file upload for logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['system_logo'])) {
    if ($_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['system_logo']['name'], PATHINFO_EXTENSION);
        $file_name = 'logo_' . time() . '.' . $file_ext;
        $file_path = 'assets/uploads/' . $file_name;
        
        if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $upload_dir . $file_name)) {
            $update_query = "INSERT INTO settings (setting_key, setting_value) 
                            VALUES ('system_logo', ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $file_path, $file_path);
            $stmt->execute();
            $success = "Logo uploaded successfully!";
        } else {
            $error = "Failed to upload logo.";
        }
    }
}

// Get all settings
$settings_query = "SELECT * FROM settings ORDER BY setting_key";
$settings_result = $conn->query($settings_query);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}

// Get audit logs
$audit_query = "SELECT a.*, u.username, u.full_name 
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC 
                LIMIT 50";
$audit_logs = $conn->query($audit_query);

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .settings-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
    }
    
    .settings-sidebar {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .settings-nav {
        list-style: none;
        padding: 0;
    }
    
    .settings-nav li {
        margin-bottom: 5px;
    }
    
    .settings-nav a {
        display: block;
        padding: 10px 15px;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s;
    }
    
    .settings-nav a:hover {
        background: #f0f0f0;
    }
    
    .settings-nav a.active {
        background: #667eea;
        color: white;
    }
    
    .settings-content {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .settings-section {
        display: none;
    }
    
    .settings-section.active {
        display: block;
    }
    
    .settings-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }
    
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="email"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        max-width: 400px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-group input[type="checkbox"] {
        width: auto;
        margin-right: 10px;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .btn-save {
        background: #27ae60;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .btn-save:hover {
        background: #229954;
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .audit-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .audit-table th,
    .audit-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .audit-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .backup-item {
        padding: 15px;
        border: 1px solid #eee;
        border-radius: 5px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    @media (max-width: 768px) {
        .settings-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2><i class="fas fa-cogs"></i> System Settings</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="settings-container">
        <div class="settings-sidebar">
            <ul class="settings-nav">
                <li><a href="#general" class="active" onclick="showSection('general')">
                    <i class="fas fa-globe"></i> General Settings
                </a></li>
                <li><a href="#security" onclick="showSection('security')">
                    <i class="fas fa-shield-alt"></i> Security
                </a></li>
                <li><a href="#documents" onclick="showSection('documents')">
                    <i class="fas fa-file-alt"></i> Document Settings
                </a></li>
                <li><a href="#email" onclick="showSection('email')">
                    <i class="fas fa-envelope"></i> Email Settings
                </a></li>
                <li><a href="#backup" onclick="showSection('backup')">
                    <i class="fas fa-database"></i> Backup
                </a></li>
                <li><a href="#audit" onclick="showSection('audit')">
                    <i class="fas fa-history"></i> Audit Logs
                </a></li>
            </ul>
        </div>
        
        <div class="settings-content">
            <form method="POST" enctype="multipart/form-data">
                <!-- General Settings Section -->
                <div id="general" class="settings-section active">
                    <h3><i class="fas fa-globe"></i> General Settings</h3>
                    
                    <div class="form-group">
                        <label>System Name</label>
                        <input type="text" name="setting_system_name" 
                               value="<?php echo htmlspecialchars($settings['system_name']['setting_value'] ?? 'Filing Management System'); ?>">
                        <small>The name displayed throughout the system</small>
                    </div>
                    
                    <div class="form-group">
                        <label>System Logo</label>
                        <input type="file" name="system_logo" accept="image/*">
                        <?php if (!empty($settings['system_logo']['setting_value'])): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo $base_url . '/' . $settings['system_logo']['setting_value']; ?>" 
                                     style="max-width: 150px; max-height: 50px;" alt="Current Logo">
                                <br><small>Current logo</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>System Email</label>
                        <input type="email" name="setting_system_email" 
                               value="<?php echo htmlspecialchars($settings['system_email']['setting_value'] ?? 'admin@example.com'); ?>">
                        <small>Default email address for system notifications</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Date Format</label>
                        <select name="setting_date_format">
                            <option value="Y-m-d" <?php echo ($settings['date_format']['setting_value'] ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            <option value="m/d/Y" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                            <option value="F d, Y" <?php echo ($settings['date_format']['setting_value'] ?? '') == 'F d, Y' ? 'selected' : ''; ?>>Month DD, YYYY</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Time Zone</label>
                        <select name="setting_timezone">
                            <option value="UTC" <?php echo ($settings['timezone']['setting_value'] ?? 'UTC') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                            <option value="America/Chicago" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                            <option value="America/Denver" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                            <option value="America/Los_Angeles" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                            <option value="Europe/London" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'Europe/London' ? 'selected' : ''; ?>>London</option>
                            <option value="Asia/Tokyo" <?php echo ($settings['timezone']['setting_value'] ?? '') == 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="setting_enable_registration" 
                                   <?php echo ($settings['enable_registration']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            Enable User Registration
                        </label>
                        <small>Allow new users to register themselves</small>
                    </div>
                </div>
                
                <!-- Security Settings Section -->
                <div id="security" class="settings-section">
                    <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                    
                    <div class="form-group">
                        <label>Session Timeout (seconds)</label>
                        <input type="number" name="setting_session_timeout" 
                               value="<?php echo htmlspecialchars($settings['session_timeout']['setting_value'] ?? '3600'); ?>">
                        <small>How long before inactive users are logged out (default: 3600 seconds = 1 hour)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="setting_maintenance_mode" 
                                   <?php echo ($settings['maintenance_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            Maintenance Mode
                        </label>
                        <small>When enabled, only administrators can access the system</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="setting_audit_log_enabled" 
                                   <?php echo ($settings['audit_log_enabled']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            Enable Audit Logging
                        </label>
                        <small>Log all system activities for security auditing</small>
                    </div>
                </div>
                
                <!-- Document Settings Section -->
                <div id="documents" class="settings-section">
                    <h3><i class="fas fa-file-alt"></i> Document Settings</h3>
                    
                    <div class="form-group">
                        <label>Items Per Page</label>
                        <input type="number" name="setting_items_per_page" 
                               value="<?php echo htmlspecialchars($settings['items_per_page']['setting_value'] ?? '20'); ?>">
                        <small>Number of items to display per page in lists</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Upload Size (MB)</label>
                        <input type="number" name="setting_max_upload_size" 
                               value="<?php echo htmlspecialchars($settings['max_upload_size']['setting_value'] ?? '10'); ?>">
                        <small>Maximum file size for document uploads</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Allowed File Types</label>
                        <input type="text" name="setting_allowed_file_types" 
                               value="<?php echo htmlspecialchars($settings['allowed_file_types']['setting_value'] ?? 'pdf,doc,docx,txt,jpg,png'); ?>">
                        <small>Comma-separated list of allowed file extensions</small>
                    </div>
                </div>
                
                <!-- Email Settings Section -->
                <div id="email" class="settings-section">
                    <h3><i class="fas fa-envelope"></i> Email Settings</h3>
                    
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="setting_smtp_host" 
                               value="<?php echo htmlspecialchars($settings['smtp_host']['setting_value'] ?? ''); ?>">
                        <small>SMTP server address (leave empty to use PHP mail function)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="setting_smtp_port" 
                               value="<?php echo htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="setting_smtp_user" 
                               value="<?php echo htmlspecialchars($settings['smtp_user']['setting_value'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="setting_smtp_pass" 
                               value="<?php echo htmlspecialchars($settings['smtp_pass']['setting_value'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="setting_notification_email" 
                                   <?php echo ($settings['notification_email']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            Send Email Notifications
                        </label>
                        <small>Send email notifications for document updates and assignments</small>
                    </div>
                </div>
                
                <!-- Backup Section -->
                <div id="backup" class="settings-section">
                    <h3><i class="fas fa-database"></i> Backup Settings</h3>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="setting_backup_enabled" 
                                   <?php echo ($settings['backup_enabled']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            Enable Automatic Backups
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Backup Frequency</label>
                        <select name="setting_backup_frequency">
                            <option value="daily" <?php echo ($settings['backup_frequency']['setting_value'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="createBackup()" class="btn-save">
                            <i class="fas fa-database"></i> Create Backup Now
                        </button>
                    </div>
                    
                    <div id="backupList">
                        <h4>Recent Backups</h4>
                        <div id="backupFiles"></div>
                    </div>
                </div>
                
                <!-- Audit Logs Section -->
                <div id="audit" class="settings-section">
                    <h3><i class="fas fa-history"></i> Audit Logs</h3>
                    
                    <?php if ($audit_logs->num_rows > 0): ?>
                        <table class="audit-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $audit_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System'); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td><?php echo $log['ip_address']; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No audit logs found.</p>
                    <?php endif; ?>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="button" onclick="clearAuditLogs()" class="btn-danger" 
                                onclick="return confirm('Are you sure? This will delete all audit logs.')">
                            <i class="fas fa-trash"></i> Clear Audit Logs
                        </button>
                    </div>
                </div>
                
                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" name="update_settings" class="btn-save">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(sectionId).classList.add('active');
    
    // Update active nav link
    document.querySelectorAll('.settings-nav a').forEach(link => {
        link.classList.remove('active');
    });
    document.querySelector(`.settings-nav a[href="#${sectionId}"]`).classList.add('active');
}

function createBackup() {
    if (confirm('Create a database backup now?')) {
        fetch('backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_backup'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Backup created successfully!');
                loadBackupList();
            } else {
                alert('Backup failed: ' + data.message);
            }
        });
    }
}

function loadBackupList() {
    fetch('backup.php?action=list_backups')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('backupFiles');
            if (data.backups && data.backups.length > 0) {
                container.innerHTML = data.backups.map(backup => `
                    <div class="backup-item">
                        <div>
                            <strong>${backup.name}</strong><br>
                            <small>Size: ${backup.size} | Created: ${backup.date}</small>
                        </div>
                        <div>
                            <a href="download_backup.php?file=${backup.name}" class="btn-save" style="padding: 5px 10px;">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p>No backups found.</p>';
            }
        });
}

function clearAuditLogs() {
    if (confirm('Are you sure you want to clear all audit logs? This action cannot be undone.')) {
        fetch('clear_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_audit'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to clear logs.');
            }
        });
    }
}

// Load backup list on page load
if (document.getElementById('backupFiles')) {
    loadBackupList();
}

// Auto-refresh backup list every 30 seconds if on backup section
setInterval(() => {
    const backupSection = document.getElementById('backup');
    if (backupSection && backupSection.classList.contains('active')) {
        loadBackupList();
    }
}, 30000);
</script>

<?php
include_once '../../includes/footer.php';
?>