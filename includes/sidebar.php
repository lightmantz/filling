<?php
// Sidebar menu configuration
$user_role = $_SESSION['user_role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Ensure base_url is defined and clean
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '';
}
$base_url = rtrim($base_url, '/');

// Functions cleanUrl() and buildUrl() are now in functions.php

// Define menu items based on role
$menus = [];

// Common menus for all users
$common_menus = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => buildUrl('modules/users/' . $user_role . '_dashboard.php'),
        'roles' => ['super_admin', 'records_officer', 'admin', 'user']
    ],
    'folders' => [
        'title' => 'Folders',
        'icon' => 'fas fa-folder',
        'url' => '#',
        'roles' => ['super_admin', 'records_officer', 'admin', 'user'],
        'submenus' => [
            ['title' => 'All Folders', 'url' => buildUrl('modules/folders/index.php')],
            ['title' => 'Browse Folders', 'url' => buildUrl('modules/folders/browse.php')]
        ]
    ]
];

// Super Admin specific menus (Full system access)
$super_admin_menus = [
    'user_management' => [
        'title' => '👥 User Management',
        'icon' => 'fas fa-users-cog',
        'url' => '#',
        'roles' => ['super_admin'],
        'submenus' => [
            ['title' => 'Add New User', 'url' => buildUrl('modules/users/add_user.php')],
            ['title' => 'Manage Users', 'url' => buildUrl('modules/users/add_user.php')],
            ['title' => 'User Roles', 'url' => buildUrl('modules/users/roles.php')],
            ['title' => 'Activity Logs', 'url' => buildUrl('modules/users/activity_log.php')]
        ]
    ],
    'system_overview' => [
        'title' => 'System Overview',
        'icon' => 'fas fa-chart-pie',
        'url' => buildUrl('modules/reports/system_overview.php'),
        'roles' => ['super_admin']
    ],
    'database_backup' => [
        'title' => 'Database Backup',
        'icon' => 'fas fa-database',
        'url' => buildUrl('modules/settings/backup.php'),
        'roles' => ['super_admin']
    ]
];

// Records Officer specific menus
$records_officer_menus = [
    'documents' => [
        'title' => 'Documents',
        'icon' => 'fas fa-file-alt',
        'url' => '#',
        'roles' => ['super_admin', 'records_officer'],
        'submenus' => [
            ['title' => 'All Documents', 'url' => buildUrl('modules/documents/all_documents.php')],
            ['title' => 'Submit Document', 'url' => buildUrl('modules/documents/create.php')],
            ['title' => 'Pending Processing', 'url' => buildUrl('modules/documents/pending_processing.php')],
            ['title' => 'Archived Documents', 'url' => buildUrl('modules/documents/archived.php')]
        ]
    ],
    'folder_management' => [
        'title' => 'Folder Management',
        'icon' => 'fas fa-folder-open',
        'url' => '#',
        'roles' => ['super_admin', 'records_officer'],
        'submenus' => [
            ['title' => 'Create Folder', 'url' => buildUrl('modules/folders/create.php')],
            ['title' => 'Manage Categories', 'url' => buildUrl('modules/categories/index.php')],
            ['title' => 'Archive Folders', 'url' => buildUrl('modules/folders/archive.php')]
        ]
    ],
    'file_index' => [
        'title' => 'File Index',
        'icon' => 'fas fa-index',
        'url' => '#',
        'roles' => ['super_admin', 'records_officer'],
        'submenus' => [
            ['title' => 'View Index', 'url' => buildUrl('modules/index/index.php')],
            ['title' => 'Export Index', 'url' => buildUrl('modules/index/export.php')],
            ['title' => 'Generate Reports', 'url' => buildUrl('modules/index/reports.php')]
        ]
    ],
    'confidential' => [
        'title' => 'Confidential Docs',
        'icon' => 'fas fa-lock',
        'url' => buildUrl('modules/documents/confidential.php'),
        'roles' => ['super_admin', 'records_officer']
    ]
];

// Admin specific menus
$admin_menus = [
    'reviews' => [
        'title' => 'Reviews',
        'icon' => 'fas fa-clipboard-list',
        'url' => '#',
        'roles' => ['super_admin', 'admin'],
        'submenus' => [
            ['title' => 'Pending Reviews', 'url' => buildUrl('modules/documents/pending.php')],
            ['title' => 'Assigned to Me', 'url' => buildUrl('modules/documents/assigned_to_me.php')],
            ['title' => 'Completed Reviews', 'url' => buildUrl('modules/documents/completed.php')]
        ]
    ],
    'approvals' => [
        'title' => 'Approvals',
        'icon' => 'fas fa-check-circle',
        'url' => '#',
        'roles' => ['super_admin', 'admin'],
        'submenus' => [
            ['title' => 'Pending Approval', 'url' => buildUrl('modules/approvals/pending.php')],
            ['title' => 'Approval History', 'url' => buildUrl('modules/approvals/history.php')]
        ]
    ],
    'task_management' => [
        'title' => 'Task Management',
        'icon' => 'fas fa-tasks',
        'url' => '#',
        'roles' => ['super_admin', 'admin'],
        'submenus' => [
            ['title' => 'Assign Tasks', 'url' => buildUrl('modules/tasks/assign.php')],
            ['title' => 'My Tasks', 'url' => buildUrl('modules/tasks/my_tasks.php')],
            ['title' => 'Task Overview', 'url' => buildUrl('modules/tasks/overview.php')]
        ]
    ],
    'settings' => [
        'title' => 'System Settings',
        'icon' => 'fas fa-cogs',
        'url' => '#',
        'roles' => ['super_admin', 'admin'],
        'submenus' => [
            ['title' => 'General Settings', 'url' => buildUrl('modules/settings/index.php#general')],
            ['title' => 'Security', 'url' => buildUrl('modules/settings/index.php#security')],
            ['title' => 'Document Settings', 'url' => buildUrl('modules/settings/index.php#documents')],
            ['title' => 'Email Settings', 'url' => buildUrl('modules/settings/index.php#email')],
            ['title' => 'Backup', 'url' => buildUrl('modules/settings/index.php#backup')],
            ['title' => 'Audit Logs', 'url' => buildUrl('modules/settings/index.php#audit')]
        ]
    ]
];

// Normal user specific menus
$user_menus = [
    'my_documents' => [
        'title' => 'My Documents',
        'icon' => 'fas fa-file',
        'url' => '#',
        'roles' => ['super_admin', 'user'],
        'submenus' => [
            ['title' => 'Submit Document', 'url' => buildUrl('modules/documents/create.php')],
            ['title' => 'My Submissions', 'url' => buildUrl('modules/documents/my_documents.php')],
            ['title' => 'Document Status', 'url' => buildUrl('modules/documents/status.php')]
        ]
    ],
    'tracking' => [
        'title' => 'Tracking',
        'icon' => 'fas fa-chart-line',
        'url' => '#',
        'roles' => ['super_admin', 'user'],
        'submenus' => [
            ['title' => 'Track Document', 'url' => buildUrl('modules/tracking/track.php')],
            ['title' => 'History', 'url' => buildUrl('modules/tracking/history.php')]
        ]
    ]
];

// Common menus for all (appears after role-specific menus)
$common_menus_extra = [
    'reports' => [
        'title' => 'Reports',
        'icon' => 'fas fa-chart-bar',
        'url' => buildUrl('modules/reports/index.php'),
        'roles' => ['super_admin', 'records_officer', 'admin', 'user']
    ],
    'search' => [
        'title' => 'Search',
        'icon' => 'fas fa-search',
        'url' => buildUrl('modules/search/index.php'),
        'roles' => ['super_admin', 'records_officer', 'admin', 'user']
    ],
    'profile' => [
        'title' => 'My Profile',
        'icon' => 'fas fa-user-circle',
        'url' => buildUrl('modules/users/profile.php'),
        'roles' => ['super_admin', 'records_officer', 'admin', 'user']
    ]
];

// Merge menus based on role
$menus = array_merge($common_menus);

// Super Admin gets ALL menus
if ($user_role === 'super_admin') {
    $menus = array_merge($menus, $super_admin_menus);
    $menus = array_merge($menus, $records_officer_menus);
    $menus = array_merge($menus, $admin_menus);
    $menus = array_merge($menus, $user_menus);
} elseif ($user_role === 'records_officer') {
    $menus = array_merge($menus, $records_officer_menus);
} elseif ($user_role === 'admin') {
    $menus = array_merge($menus, $admin_menus);
} elseif ($user_role === 'user') {
    $menus = array_merge($menus, $user_menus);
}

$menus = array_merge($menus, $common_menus_extra);

// Helper function to check if menu item is active
function isActive($url) {
    global $current_page, $current_module;
    if ($url == '#') return false;
    $base = basename($url);
    // Remove anchor/hash from URL for comparison
    $url = strtok($url, '#');
    return strpos($base, $current_page) !== false || strpos($url, $current_module) !== false;
}
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-folder-open"></i> Filing System</h3>
        <p>Document Management</p>
    </div>
    
    <ul class="sidebar-menu">
        <?php foreach ($menus as $menu): ?>
            <?php if (in_array($user_role, $menu['roles'])): ?>
                <?php if (isset($menu['submenus']) && !empty($menu['submenus'])): ?>
                    <li>
                        <a href="javascript:void(0)" class="has-submenu">
                            <i class="<?php echo $menu['icon']; ?>"></i>
                            <span><?php echo $menu['title']; ?></span>
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 12px;"></i>
                        </a>
                        <ul class="submenu">
                            <?php foreach ($menu['submenus'] as $submenu): ?>
                                <li>
                                    <a href="<?php echo $submenu['url']; ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == basename($submenu['url']) ? 'active' : ''; ?>">
                                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 10px;"></i>
                                        <?php echo $submenu['title']; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo $menu['url']; ?>" class="<?php echo isActive($menu['url']) ? 'active' : ''; ?>">
                            <i class="<?php echo $menu['icon']; ?>"></i>
                            <span><?php echo $menu['title']; ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <li style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 50px;">
            <a href="<?php echo buildUrl('modules/auth/logout.php'); ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Header -->
    <div class="top-header">
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        
        <div class="user-menu">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div class="user-role">
                        <?php 
                        $role_display = [
                            'super_admin' => 'Super Administrator',
                            'records_officer' => 'Records Officer',
                            'admin' => 'Administrator',
                            'user' => 'User'
                        ];
                        echo $role_display[$_SESSION['user_role'] ?? 'user'] ?? 'User';
                        ?>
                    </div>
                </div>
            </div>
            <a href="<?php echo buildUrl('modules/auth/logout.php'); ?>" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?php echo buildUrl('modules/users/' . $user_role . '_dashboard.php'); ?>">
            <i class="fas fa-home"></i> Home
        </a>
        <?php
        // Generate breadcrumb based on current URL
        $request_uri = str_replace(BASE_URL, '', $_SERVER['REQUEST_URI']);
        $request_uri = ltrim($request_uri, '/');
        $path_parts = explode('/', $request_uri);
        $breadcrumb_items = [];
        
        foreach ($path_parts as $part) {
            if ($part == 'modules') continue;
            if (!empty($part) && !strpos($part, '.php')) {
                $display = ucwords(str_replace(['_', '-'], ' ', $part));
                $breadcrumb_items[] = '<span class="separator"> / </span><span>' . $display . '</span>';
            } elseif (strpos($part, '.php')) {
                $display = ucwords(str_replace(['_', '.php'], ' ', $part));
                $breadcrumb_items[] = '<span class="separator"> / </span><span>' . $display . '</span>';
            }
        }
        
        echo implode('', $breadcrumb_items);
        ?>
    </div>
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">