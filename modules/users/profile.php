<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'My Profile';
$base_url = '../../';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $department = $_POST['department'] ?? '';
    
    $update_query = "UPDATE users SET full_name = ?, email = ?, department = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssi", $full_name, $email, $department, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $success = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Failed to update profile.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // For demo, using plain text check. In production, use password_verify()
        if ($current_password === 'password123' || $current_password === $user['password']) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = "UPDATE users SET password = ? WHERE id = ?";
            $pass_stmt = $conn->prepare($update_pass);
            $pass_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($pass_stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .profile-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
    }
    
    .profile-sidebar {
        background: white;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: white;
        font-weight: bold;
    }
    
    .profile-info {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        text-align: left;
    }
    
    .profile-info p {
        margin: 10px 0;
    }
    
    .profile-info strong {
        color: #666;
    }
    
    .profile-main {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .form-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .tab-btn {
        padding: 10px 20px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: #666;
        transition: all 0.3s;
    }
    
    .tab-btn.active {
        color: #667eea;
        border-bottom: 2px solid #667eea;
        margin-bottom: -2px;
    }
    
    .tab-pane {
        display: none;
    }
    
    .tab-pane.active {
        display: block;
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
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
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
        background: #667eea;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .btn-save:hover {
        background: #5a67d8;
    }
    
    @media (max-width: 768px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <h2>My Profile</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
            <p style="color: #666;"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
            
            <div class="profile-info">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                <p><strong>Status:</strong> <span class="status-badge status-active">Active</span></p>
            </div>
        </div>
        
        <div class="profile-main">
            <div class="form-tabs">
                <button class="tab-btn active" onclick="showTab('edit-profile')">Edit Profile</button>
                <button class="tab-btn" onclick="showTab('change-password')">Change Password</button>
                <button class="tab-btn" onclick="showTab('activity-log')">Activity Log</button>
            </div>
            
            <!-- Edit Profile Tab -->
            <div id="edit-profile" class="tab-pane active">
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-save">Update Profile</button>
                </form>
            </div>
            
            <!-- Change Password Tab -->
            <div id="change-password" class="tab-pane">
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn-save">Change Password</button>
                </form>
            </div>
            
            <!-- Activity Log Tab -->
            <div id="activity-log" class="tab-pane">
                <?php
                $activity_query = "SELECT * FROM workflow_tracking 
                                  WHERE from_user = ? OR to_user = ? 
                                  ORDER BY created_at DESC LIMIT 20";
                $act_stmt = $conn->prepare($activity_query);
                $act_stmt->bind_param("ii", $user_id, $user_id);
                $act_stmt->execute();
                $activities = $act_stmt->get_result();
                ?>
                
                <?php if ($activities->num_rows > 0): ?>
                    <div class="activity-timeline">
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="activity-details">
                                    <p><strong><?php echo ucfirst($activity['action']); ?></strong></p>
                                    <p class="activity-meta">
                                        <?php echo date('F d, Y H:i:s', strtotime($activity['created_at'])); ?>
                                    </p>
                                    <?php if ($activity['notes']): ?>
                                        <p class="activity-notes"><?php echo htmlspecialchars($activity['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No activity found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>

<?php
include_once '../../includes/footer.php';
?>

<!-- Notification Settings Section -->
<div class="card" style="margin-top: 20px;">
    <h3><i class="fas fa-bell"></i> Notification Settings</h3>
    
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" id="enableNotifications" onchange="toggleNotifications()">
            Enable Desktop Notifications
        </label>
    </div>
    
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" id="enableSound" onchange="toggleSound()">
            Play Sound for Notifications
        </label>
    </div>
    
    <div class="form-group">
        <button onclick="testNotification()" class="btn btn-primary">
            <i class="fas fa-bell"></i> Test Notification
        </button>
    </div>
</div>

<script>
function toggleNotifications() {
    const enabled = document.getElementById('enableNotifications').checked;
    if (notificationManager) {
        notificationManager.updatePreferences({ enabled: enabled });
    }
    localStorage.setItem('notificationsEnabled', enabled);
}

function toggleSound() {
    const enabled = document.getElementById('enableSound').checked;
    if (notificationManager) {
        notificationManager.updatePreferences({ sound: enabled });
    }
    localStorage.setItem('notificationSound', enabled);
}

function testNotification() {
    if (notificationManager && notificationManager.permission) {
        notificationManager.sendNotification(
            'Test Notification',
            'This is a test notification from your Filing Management System!',
            window.location.href,
            'test'
        );
    } else {
        alert('Please enable desktop notifications first');
        Notification.requestPermission();
    }
}

// Load saved preferences
document.addEventListener('DOMContentLoaded', () => {
    const notificationsEnabled = localStorage.getItem('notificationsEnabled') !== 'false';
    const soundEnabled = localStorage.getItem('notificationSound') !== 'false';
    
    document.getElementById('enableNotifications').checked = notificationsEnabled;
    document.getElementById('enableSound').checked = soundEnabled;
});
</script>