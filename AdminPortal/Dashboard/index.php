<?php
session_start();
include("./connect2db.php");

// Redirect to login if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- Handle Suspend/Unsuspend Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    header('Content-Type: application/json');
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']); // 'Active' or 'Inactive'
    
    // Check if user exists
    $check = mysqli_query($conn, "SELECT id, name FROM users WHERE id = '$user_id'");
    if ($check && mysqli_num_rows($check) > 0) {
        $user_row = mysqli_fetch_assoc($check);
        $user_name = $user_row['name'];
        
        $update = mysqli_query($conn, "UPDATE users SET status = '$new_status' WHERE id = '$user_id'");
        if ($update) {
            // Log audit entry
            $admin_id = mysqli_real_escape_string($conn, $_SESSION['id']);
            $action_word = ($new_status === 'Active') ? 'unsuspended' : 'suspended';
            $audit_desc = "Admin $action_word user account: <strong>$user_name</strong> ($user_id)";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$admin_id', 'user_status_changed', '$audit_desc')");
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    mysqli_close($conn);
    exit();
}
// --- API Handlers ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'fetch_directory') {
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;
        
        $query = $_GET['q'] ?? '';
        $role_filter = $_GET['role'] ?? 'all';
        $limit = (isset($_GET['limit']) && $_GET['limit'] === 'all') ? 10000 : 6;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = [];
        if (!empty($query)) {
            $safe_query = mysqli_real_escape_string($conn, $query);
            $where_clauses[] = "(u.name LIKE '%$safe_query%' OR u.id LIKE '%$safe_query%' OR t.initials LIKE '%$safe_query%')";
        }
        
        if ($role_filter === 'student') {
            $where_clauses[] = "u.role = 'student'";
        } elseif ($role_filter === 'teacher') {
            $where_clauses[] = "u.role = 'teacher'";
        } else {
            $where_clauses[] = "u.role IN ('student', 'teacher')";
        }
        
        $where_str = implode(' AND ', $where_clauses);
        
        // Count total matching users
        $count_sql = "SELECT COUNT(*) as total
                      FROM users u
                      LEFT JOIN teachers t ON u.id = t.teacher_id
                      WHERE $where_str";
        $count_res = mysqli_query($conn, $count_sql);
        $total_users = 0;
        if ($count_res) {
            $total_users = intval(mysqli_fetch_assoc($count_res)['total']);
        }
        $total_pages = ceil($total_users / $limit);
        if ($total_pages < 1) $total_pages = 1;
        
        // Sort order parameter
        $sort = $_GET['sort'] ?? '';
        $order_by = "u.role ASC, u.name ASC";
        if ($sort === 'name_asc') {
            $order_by = "u.name ASC";
        } elseif ($sort === 'name_desc') {
            $order_by = "u.name DESC";
        } elseif ($sort === 'id_asc') {
            $order_by = "u.id ASC";
        } elseif ($sort === 'id_desc') {
            $order_by = "u.id DESC";
        } elseif ($sort === 'access_asc') {
            $order_by = "u.last_login ASC";
        } elseif ($sort === 'access_desc') {
            $order_by = "u.last_login DESC";
        } elseif ($sort === 'status_asc') {
            $order_by = "u.status ASC";
        } elseif ($sort === 'status_desc') {
            $order_by = "u.status DESC";
        }
        
        // Fetch paginated users
        $sql = "SELECT u.id, u.name, u.role, u.status, u.email, u.last_login,
                       d.name as dept_name, t.position, t.initials, s.academic_year
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.department_id
                LEFT JOIN teachers t ON u.id = t.teacher_id
                LEFT JOIN students s ON u.id = s.student_id
                WHERE $where_str
                ORDER BY $order_by
                LIMIT $limit OFFSET $offset";
                
        $res = mysqli_query($conn, $sql);
        $users = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $users[] = $row;
            }
        }
        
        echo json_encode([
            'users' => $users,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'total_users' => $total_users
        ]);
        mysqli_close($conn);
        exit();
    }

    if ($_GET['action'] === 'search') {
        $query = $_GET['q'] ?? '';
        $role_filter = $_GET['role'] ?? 'all';
        $results = [];
        if (strlen($query) > 0) {
            $safe_query = mysqli_real_escape_string($conn, $query);
            $where_clauses = ["(u.id LIKE '%$safe_query%' OR u.name LIKE '%$safe_query%' OR t.initials LIKE '%$safe_query%')"];
            if ($role_filter === 'student') {
                $where_clauses[] = "u.role = 'student'";
            } elseif ($role_filter === 'teacher') {
                $where_clauses[] = "u.role = 'teacher'";
            } else {
                $where_clauses[] = "u.role IN ('student', 'teacher')";
            }
            $where_str = implode(' AND ', $where_clauses);
            
            $sql = "SELECT u.id, u.name, u.role FROM users u 
                    LEFT JOIN teachers t ON u.id = t.teacher_id
                    WHERE $where_str
                    LIMIT 10";
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $results[] = $row;
                }
            }
        }
        echo json_encode($results);
        mysqli_close($conn);
        exit();
    }

    if ($_GET['action'] === 'user_info') {
        $id = $_GET['id'] ?? '';
        $user_info = [];
        if (!empty($id)) {
            $safe_id = mysqli_real_escape_string($conn, $id);
            $sql = "SELECT u.id, u.name, u.email, u.role, u.status, d.name as dept_name 
                    FROM users u 
                    LEFT JOIN departments d ON u.department_id = d.department_id 
                    WHERE u.id = '$safe_id'";
            $res = mysqli_query($conn, $sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $user_info = $row;
                if ($user_info['role'] === 'student') {
                    $student_sql = "SELECT s.date_of_birth, s.academic_year, s.total_credits_earned, s.cumulative_gpa, p.name as program_name 
                                    FROM students s 
                                    LEFT JOIN programs p ON s.program_id = p.program_id 
                                    WHERE s.student_id = '$safe_id'";
                    $s_res = mysqli_query($conn, $student_sql);
                    if ($s_res && $s_row = mysqli_fetch_assoc($s_res)) {
                        $user_info = array_merge($user_info, $s_row);
                    }
                } elseif ($user_info['role'] === 'teacher') {
                    $teacher_sql = "SELECT position FROM teachers WHERE teacher_id = '$safe_id'";
                    $t_res = mysqli_query($conn, $teacher_sql);
                    if ($t_res && $t_row = mysqli_fetch_assoc($t_res)) {
                        $user_info = array_merge($user_info, $t_row);
                    }
                }
            }
        }
        echo json_encode($user_info);
        mysqli_close($conn);
        exit();
    }

    if ($_GET['action'] === 'fetch_audit_logs') {
        $limit = (isset($_GET['limit']) && $_GET['limit'] === 'all') ? 1000 : 8;
        $audit_api_query = "
            SELECT al.*, u.name as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT $limit
        ";
        $audit_api_result = mysqli_query($conn, $audit_api_query);
        $audit_api_logs = [];
        if ($audit_api_result) {
            while ($row = mysqli_fetch_assoc($audit_api_result)) {
                $row['time_label'] = date('g:i a, j F', strtotime($row['created_at']));
                $audit_api_logs[] = $row;
            }
        }
        echo json_encode($audit_api_logs);
        mysqli_close($conn);
        exit();
    }
}

// --- Fetch Dashboard Data ---

// Total Teachers
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='teacher'");
$total_teachers = mysqli_fetch_assoc($result)['total'];

// Active Students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='student' AND status='Active'");
$active_students = mysqli_fetch_assoc($result)['total'];

// Suspended Users
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status='Inactive'");
$suspended_users = mysqli_fetch_assoc($result)['total'];

// Total Users
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$total_users = mysqli_fetch_assoc($result)['total'];

// Registry: Active Faculty & Students
$registry_query = "
    SELECT u.id, u.name, u.role, u.status, u.department_id, 
           d.name as dept_name, t.position
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN teachers t ON u.id = t.teacher_id
    WHERE u.role IN ('teacher', 'student')
    ORDER BY u.created_at DESC
    LIMIT 5
";
$registry_result = mysqli_query($conn, $registry_query);
$registry_users = [];
while ($row = mysqli_fetch_assoc($registry_result)) {
    $registry_users[] = $row;
}

// Audit Logs
$audit_query = "
    SELECT al.*, u.name as user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 8
";
$audit_result = mysqli_query($conn, $audit_query);
$audit_logs = [];
if ($audit_result) {
    while ($row = mysqli_fetch_assoc($audit_result)) {
        $audit_logs[] = $row;
    }
}

// Admin info from session
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin User';
$admin_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'admin@markmetrics.edu';

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Admin Portal</title>
    <meta name="description"
        content="MarkMetrics Admin Dashboard – Real-time system integrity and academic metrics overview.">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>

<body>
    <div class="app-container">
        <!-- ===== Sidebar ===== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../../Images/Web_logo-removebg-preview.png" alt="MarkMetrics" class="main-logo">
            </div>

            <nav class="sidebar-nav">
                <a href="../Dashboard/index.php" class="nav-item active">
                    <div class="active-indicator"></div>
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="2"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="2"></rect>
                    </svg>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="../CourseManagement/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    <span class="nav-text">Course Management</span>
                </a>
                <a href="../UserManagement/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span class="nav-text">User Management</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item logout-btn" id="logoutBtn">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="nav-text">Log Out</span>
                </a>



                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <img src="../../Images/AdminUser.jpg" alt="Admin">
                    </div>
                    <div class="sidebar-user-info">
                        <span class="sidebar-user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="sidebar-user-email"><?php echo htmlspecialchars($admin_email); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ===== Main Content ===== -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-container">
                    <div class="search-bar" style="position: relative;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" placeholder="Search by name, ID or initial..." id="searchInput" autocomplete="off">
                    </div>
                    <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
                    <select id="roleFilter" style="background: #27272a; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-secondary); padding: 10px 16px; outline: none; font-size: 14px; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;">
                        <option value="all">All Roles</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>

                <div class="top-bar-right" style="position: relative; display: flex; align-items: center; gap: 16px;">
                    <!-- Academic Term Badge -->
                    <div class="term-badge" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(243,112,33,0.1); border: 1px solid rgba(243,112,33,0.25); color: var(--accent-orange); padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; height: 34px;">
                        <span style="width: 6px; height: 6px; background-color: var(--accent-orange); border-radius: 50%; display: inline-block; box-shadow: 0 0 8px var(--accent-orange);"></span>
                        Term: <?php echo SYSTEM_TERM_DISPLAY; ?>
                    </div>

                    <!-- Datetime Widget -->
                    <div class="datetime-display" style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 10px; padding: 6px 14px; height: 34px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; color: var(--accent-orange); flex-shrink: 0;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span style="font-weight: 700; color: var(--text-primary); font-size: 12px; white-space: nowrap;" id="topBarDate"><?php echo date('jS F'); ?></span>
                        <span style="width: 1px; height: 12px; background: var(--border-color); display: inline-block;"></span>
                        <span id="topBarTime" style="font-weight: 600; font-family: monospace; color: var(--text-secondary); font-size: 12px; white-space: nowrap;"><?php echo date('h:i A'); ?></span>
                    </div>
                    <button class="notification-btn" id="notificationBtn" onclick="toggleAdminNoti(event)">
                        <span class="notification-badge" id="notiPulseBadge"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </button>
                    <!-- Notification Dropdown -->
                    <div id="adminNotiDropdown" class="notification-dropdown" style="display:none;">
                        <div class="notification-dropdown-header">
                            <span>🔔 Notifications</span>
                            <span style="font-size:11px; background:rgba(243,112,33,0.12); color:var(--accent-orange); padding:2px 8px; border-radius:20px; font-weight:600;" id="notiCount">0 Recent</span>
                        </div>
                        <div id="notiContent" class="noti-empty">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content-wrapper">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="header-titles">
                        <h1>MarkMetrics Overview</h1>
                        <p>Real time system integrity and academic metrics.</p>
                    </div>
                    <div class="system-status">
                        <span class="system-status-label">System Status</span>
                        <span class="system-status-value">
                            <span class="status-dot"></span>
                            Operational
                        </span>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Total Teachers</span>
                        <span class="stat-value"><?php echo number_format($total_teachers); ?></span>
                        <span class="stat-sub green">All faculty active</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Active Students</span>
                        <span class="stat-value"><?php echo number_format($active_students); ?></span>
                        <span class="stat-sub green">99% Authenticated</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Suspended</span>
                        <span class="stat-value"><?php echo number_format($suspended_users); ?></span>
                        <span class="stat-sub orange">Accounts restricted</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">System Health</span>
                        <span class="stat-value">99.9%</span>
                        <span class="stat-sub green">All services active</span>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Registry / User Directory -->
                    <div class="registry-card">
                        <div class="registry-header" style="flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; display: flex; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 4px; height: 24px; background: var(--accent-orange); border-radius: 2px;"></div>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0;">User Directory</h3>
                            </div>
                            
                            <div class="directory-controls" style="display: flex; align-items: center; gap: 10px;">
                                <select id="dirRoleFilter" style="background: #27272a; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-secondary); padding: 6px 12px; outline: none; font-size: 12px; cursor: pointer; font-family: inherit;">
                                    <option value="all">All Roles</option>
                                    <option value="student">Students</option>
                                    <option value="teacher">Teachers</option>
                                </select>
                            </div>
                        </div>

                        <div style="overflow-x: auto; margin-top: 15px; width: 100%;">
                            <table class="registry-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th id="thSortName" style="cursor: pointer; user-select: none; white-space: nowrap; transition: color 0.2s;">
                                            User Details <span id="sortIndicatorName" style="margin-left: 4px; font-size: 10px; opacity: 0.7;">⇅</span>
                                        </th>
                                        <th id="thSortAccess" style="cursor: pointer; user-select: none; white-space: nowrap; transition: color 0.2s;">
                                            Access time <span id="sortIndicatorAccess" style="margin-left: 4px; font-size: 10px; opacity: 0.7;">⇅</span>
                                        </th>
                                        <th id="thSortId" style="cursor: pointer; user-select: none; white-space: nowrap; transition: color 0.2s;">
                                            ID <span id="sortIndicatorId" style="margin-left: 4px; font-size: 10px; opacity: 0.7;">⇅</span>
                                        </th>
                                        <th>Initial</th>
                                        <th>Role</th>
                                        <th id="thSortStatus" style="cursor: pointer; user-select: none; white-space: nowrap; transition: color 0.2s;">
                                            Status <span id="sortIndicatorStatus" style="margin-left: 4px; font-size: 10px; opacity: 0.7;">⇅</span>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="registryBody">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 15px; width: 100%;">
                            <span id="directoryTotalCount" style="font-size: 12px; color: var(--text-muted);">Total <?php echo number_format($total_users); ?> users</span>
                            <div class="directory-pagination" id="directoryPagination" style="display: flex; align-items: center; gap: 6px;">
                                <!-- Populated dynamically via JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Audit Logs -->
                    <div class="audit-card">
                        <h3>Audit Logs</h3>
                        <div class="audit-list" id="auditList">
                            <?php if (empty($audit_logs)): ?>
                                <div class="audit-item">
                                    <div class="audit-time">—</div>
                                    <p class="audit-text" style="color: #71717a;">No activity recorded yet. Logs will appear here as users log in, accounts are provisioned, or passwords are reset.</p>
                                </div>
                            <?php else: ?>
                            <?php foreach ($audit_logs as $log):
                                $time_label = date('g:i a, j F', strtotime($log['created_at']));
                                ?>
                                <div class="audit-item" data-log-id="<?php echo $log['log_id']; ?>">
                                    <div class="audit-time"><?php echo $time_label; ?></div>
                                    <p class="audit-text"><?php echo $log['description']; ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div style="border-top: 1px solid var(--border-color); margin-top: auto; padding-top: 16px; display: flex; justify-content: center; width: 100%;">
                            <a href="#" class="view-all-link" id="viewAllAuditBtn" style="width: 100%; text-align: center; padding-top: 0; padding-bottom: 0; margin-top: 0;">View All</a>
                        </div>
                    </div>
                </div>

                <!-- Bottom Grid -->
                <div class="bottom-grid">
                    <!-- Total Access Chart -->
                    <div class="access-card">
                        <h3>Total access</h3>
                        <div class="bar-chart" id="accessChart">
                            <div class="bar-col">
                                <div class="bar gray" style="height: 40%;"></div>
                                <span class="bar-label">Sun</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar orange" style="height: 85%;"></div>
                                <span class="bar-label">Mon</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar orange" style="height: 55%;"></div>
                                <span class="bar-label">Tue</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar orange" style="height: 70%;"></div>
                                <span class="bar-label">Wed</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar orange" style="height: 90%;"></div>
                                <span class="bar-label">Thur</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar orange" style="height: 65%;"></div>
                                <span class="bar-label">Fri</span>
                            </div>
                            <div class="bar-col">
                                <div class="bar gray" style="height: 30%;"></div>
                                <span class="bar-label">Sat</span>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Utilization -->
                    <div class="storage-card">
                        <h3>Storage Utilization</h3>
                        <div class="storage-value">
                            <span class="big">2.4TB</span>
                            <span class="small">/10TB</span>
                        </div>
                        <p class="storage-info">
                            System backups are synchronized across 3 regional nodes with <span
                                class="highlight">0ms</span> latency. Encryption <span
                                class="highlight">Standard</span>, AES-256-GC.
                        </p>
                    </div>

                    <!-- AI Predictor -->
                    <div class="ai-card">
                        <div class="ai-card-title">
                            <span>MarkMetrics</span>
                            <span>AI predictor</span>
                        </div>
                        <p class="ai-description">Based on current entry. Velocity, final grading completion for all
                            departments is estimated at:</p>
                        <div class="ai-score">94.8%</div>
                        <div class="ai-labels">
                            <span class="ai-label">Precision</span>
                            <span class="ai-label">Grade</span>
                            <span class="ai-label">Integrity</span>
                            <span class="ai-label">Matrix</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Audit Logs Side Drawer -->
    <div id="auditDrawerOverlay" class="drawer-overlay">
        <div class="drawer-content">
            <div class="drawer-header">
                <h2>System Audit Logs</h2>
                <button class="drawer-close" id="closeDrawerBtn">&times;</button>
            </div>
            
            <div class="drawer-search-container" style="padding: 16px 24px; border-bottom: 1px solid var(--border-color);">
                <div class="search-bar" style="width: 100%; position: relative;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" placeholder="Search audit logs..." id="drawerSearchInput" autocomplete="off">
                </div>
            </div>
            
            <div class="drawer-body" id="drawerAuditList">
                <!-- Populated dynamically via JS -->
            </div>
        </div>
    </div>

    <!-- User Info Modal -->
    <div id="userInfoModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" id="closeModalBtn">&times;</button>
            <div class="modal-header">
                <h2 id="modalUserName">User Name</h2>
                <span id="modalUserRole" class="modal-badge">Role</span>
            </div>
            <div class="modal-body" id="modalUserDetails">
                <!-- Details injected via JS -->
            </div>
            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <button id="suspendUserBtn" class="provision-btn" style="background-color: #ef4444; margin: 0; padding: 10px 20px; font-size: 14px; width: auto; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: none;">Suspend User</button>
                <button id="unsuspendUserBtn" class="provision-btn" style="background-color: #22c55e; margin: 0; padding: 10px 20px; font-size: 14px; width: auto; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: none;">Unsuspend User</button>
            </div>
        </div>
    </div>

    <script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>
</body>

</html>