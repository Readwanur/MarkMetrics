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

// --- API Handlers ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'search') {
        $query = $_GET['q'] ?? '';
        $results = [];
        if (strlen($query) > 0) {
            $safe_query = mysqli_real_escape_string($conn, $query);
            $sql = "SELECT id, name, role FROM users 
                    WHERE id LIKE '%$safe_query%' OR name LIKE '%$safe_query%' 
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
        $audit_api_query = "
            SELECT al.*, u.name as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ";
        $audit_api_result = mysqli_query($conn, $audit_api_query);
        $audit_api_logs = [];
        if ($audit_api_result) {
            while ($row = mysqli_fetch_assoc($audit_api_result)) {
                // Calculate relative time on server side
                $time_diff = time() - strtotime($row['created_at']);
                if ($time_diff < 60) {
                    $row['time_label'] = 'Just now';
                } elseif ($time_diff < 3600) {
                    $row['time_label'] = floor($time_diff / 60) . ' mins ago';
                } elseif ($time_diff < 86400) {
                    $row['time_label'] = floor($time_diff / 3600) . ' hours ago';
                } else {
                    $row['time_label'] = floor($time_diff / 86400) . ' days ago';
                }
                $audit_api_logs[] = $row;
            }
        }
        echo json_encode($audit_api_logs);
        mysqli_close($conn);
        exit();
    }
}

// --- Fetch Dashboard Data ---

// Total Students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='student'");
$total_students = mysqli_fetch_assoc($result)['total'];

// Active Teachers
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='teacher' ");
$active_teachers = mysqli_fetch_assoc($result)['total'];

// Pending Results (Ongoing enrollments)
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM enrollments WHERE status='Ongoing'");
$pending_results = mysqli_fetch_assoc($result)['total'];

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
    LIMIT 4
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
    <link rel="stylesheet" href="style.css">
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
                <div class="search-bar" style="position: relative;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" placeholder="Search by name or ID..." id="searchInput" autocomplete="off">
                    <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
                </div>

                <div class="top-bar-center">
                    <span class="term-label">Academic Term</span>
                    <span>Today's Date <?php echo date('jS F Y'); ?></span>
                </div>

                <div class="top-bar-right">
                    <button class="notification-btn" id="notificationBtn">
                        <span class="notification-badge"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </button>
                    <div class="top-bar-brand">
                        MarkMetrics
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
                        <span class="stat-label">Total Students</span>
                        <span class="stat-value"><?php echo number_format($total_students); ?></span>
                        <span class="stat-sub green">+6.2% from last term</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Active Teachers</span>
                        <span class="stat-value"><?php echo number_format($active_teachers); ?></span>
                        <span class="stat-sub green">99% Authenticated</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pending Results</span>
                        <span class="stat-value"><?php echo number_format($pending_results); ?></span>
                        <span class="stat-sub orange">Due in 48th hour</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">System Health</span>
                        <span class="stat-value">99.9%</span>
                        <span class="stat-sub green">All services active</span>
                    </div>
                </div>

                <!-- Main Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Registry -->
                    <div class="registry-card">
                        <div class="registry-header">
                            <h3>Active Faculty & Student Registry</h3>
                        </div>

                        <table class="registry-table">
                            <thead>
                                <tr>
                                    <th>User Details</th>
                                    <th>Roles</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="registryBody">
                                <?php foreach ($registry_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm">
                                                    <img src="../../Images/OtherUser.jpg"
                                                        alt="<?php echo htmlspecialchars($user['name']); ?>">
                                                </div>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?>
                                                    </div>
                                                    <div class="user-dept">
                                                        <?php echo htmlspecialchars($user['dept_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span
                                                class="role-text"><?php echo htmlspecialchars($user['position'] ?? ucfirst($user['role'])); ?></span>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge <?php echo strtolower($user['status']) === 'active' ? 'active' : 'inactive'; ?>">
                                                <span class="status-badge-dot"></span>
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><button class="action-btn">⋮</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <a href="#" class="view-all-link">Total <?php echo number_format($total_users); ?> users</a>
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
                                $time_diff = time() - strtotime($log['created_at']);
                                if ($time_diff < 3600) {
                                    $time_label = floor($time_diff / 60) . ' mins ago';
                                } elseif ($time_diff < 86400) {
                                    $time_label = floor($time_diff / 3600) . ' hours ago';
                                } else {
                                    $time_label = floor($time_diff / 86400) . ' days ago';
                                }
                                ?>
                                <div class="audit-item">
                                    <div class="audit-time"><?php echo $time_label; ?></div>
                                    <p class="audit-text"><?php echo $log['description']; ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
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
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>