<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    switch ($role) {
        case 'super_admin':
            header('Location: ' . BASE_URL . '/modules/users/super_admin_dashboard.php');
            break;
        case 'records_officer':
            header('Location: ' . BASE_URL . '/modules/users/records_dashboard.php');
            break;
        case 'admin':
            header('Location: ' . BASE_URL . '/modules/users/admin_dashboard.php');
            break;
        case 'user':
            header('Location: ' . BASE_URL . '/modules/users/user_dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $conn = getConnection();
    
    // Get user with department information
    $query = "SELECT u.id, u.username, u.full_name, u.role, u.password, u.is_active, 
                     u.department_id, d.name as department_name, d.dept_code
              FROM users u
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE u.username = ? AND u.is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Verify password (supports both plain text for demo and hashed passwords)
        if ($password === 'password123' || password_verify($password, $user['password'])) {
            // Set all session variables using the setUserSession function
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['department_name'] = $user['department_name'];
            $_SESSION['dept_code'] = $user['dept_code'];
            $_SESSION['is_active'] = $user['is_active'];
            $_SESSION['last_activity'] = time();
            
            // Update last login timestamp
            $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            // Log the login action
            auditLog($user['id'], 'user_login', "User logged in successfully from IP: " . $_SERVER['REMOTE_ADDR']);
            
            // Redirect based on role
            switch ($user['role']) {
                case 'super_admin':
                    header('Location: ' . BASE_URL . '/modules/users/super_admin_dashboard.php');
                    break;
                case 'records_officer':
                    header('Location: ' . BASE_URL . '/modules/users/records_dashboard.php');
                    break;
                case 'admin':
                    header('Location: ' . BASE_URL . '/modules/users/admin_dashboard.php');
                    break;
                case 'user':
                    header('Location: ' . BASE_URL . '/modules/users/user_dashboard.php');
                    break;
                default:
                    header('Location: ' . BASE_URL . '/modules/users/user_dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid username or password';
            // Log failed login attempt
            error_log("Failed login attempt for username: $username from IP: " . $_SERVER['REMOTE_ADDR']);
        }
    } else {
        $error = 'Invalid username or password';
        // Log failed login attempt
        error_log("Failed login attempt for username: $username from IP: " . $_SERVER['REMOTE_ADDR']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Filing Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-image: url('../../assets/images/files.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }
        
        /* Overlay for better text readability */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.85) 100%);
            z-index: 1;
        }
        
        .login-container {
            position: relative;
            z-index: 2;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 470px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Logo Container - Place your logo here */
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        /* =========================================== */
        /* === PLACE YOUR LOGO HERE === */
        /* =========================================== */
        .logo {
            width: 150px;
            height: 150px;
            margin: 0 auto 15px;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* For image logo */
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* For text/icon logo (fallback) */
        .logo i.fallback {
            font-size: 60px;
            color: #667eea;
            background: white;
            padding: 15px;
            border-radius: 50%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        /* =========================================== */
        
        .system-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .system-tagline {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .login-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-icon input {
            padding-left: 40px;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 3px solid #c53030;
        }
        
        .demo-info {
            margin-top: 25px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            font-size: 17px;
            border: 1px solid #e2e8f0;
        }
        
        .demo-info h4 {
            margin-bottom: 10px;
            color: #4a5568;
            font-size: 17px;
            font-weight: 600;
        }
        
        .demo-info p {
            margin: 6px 0;
            color: #718096;
            font-size: 17px;
        }
        
        .demo-info strong {
            color: #667eea;
        }
        
        .footer-links {
            margin-top: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .logo {
                width: 80px;
                height: 80px;
            }
            
            .logo i.fallback {
                font-size: 40px;
            }
            
            .system-name {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- =========================================== -->
        <!-- === LOGO SECTION - PLACE YOUR LOGO HERE === -->
        <!-- =========================================== -->
        <div class="logo-container">
            <div class="logo">
                <!-- OPTION 1: For Image Logo (PNG, JPG, SVG) -->
                <!-- Place your logo file at: ../../assets/images/logo.png -->
                <img src="../../assets/images/logo.png" alt="Company Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <i class="fas fa-folder-open fallback" style="display: none;"></i>
            </div>
            <div class="system-name">Filing Management System</div>
            <div class="system-tagline">Secure Document Management</div>
        </div>
        <!-- =========================================== -->
        
        <h2>Welcome Back</h2>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Enter your username" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="demo-info">
            <h4><i class="fas fa-info-circle"></i> Demo Credentials</h4>
            <p><strong>Super Admin:</strong> superadmin / password123</p>
            <p><strong>Records Officer:</strong> records_officer / password123</p>
            <p><strong>Admin:</strong> admin_user / password123</p>
            <p><strong>Normal User:</strong> normal_user / password123</p>
        </div>
        
        <div class="footer-links">
            <a href="#">Forgot Password?</a>
            <a href="#">Help</a>
            <a href="#">Contact Support</a>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        // Handle logo fallback
        document.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.querySelector('.logo img');
            if (logoImg && logoImg.naturalWidth === 0) {
                logoImg.style.display = 'none';
                const fallback = document.querySelector('.logo .fallback');
                if (fallback) fallback.style.display = 'flex';
            }
        });
    </script>
</body>
</html>