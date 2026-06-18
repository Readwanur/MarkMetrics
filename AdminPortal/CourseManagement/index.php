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

// --- Course Registration POST Handler ---
$course_register_success = false;
$course_register_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_course'])) {
    $c_code = mysqli_real_escape_string($conn, trim($_POST['course_code']));
    $c_name = mysqli_real_escape_string($conn, trim($_POST['course_name']));
    $c_credits = floatval($_POST['credits']);
    $c_dept = intval($_POST['department_id']);
    $c_teacher = !empty($_POST['teacher_id']) ? "'" . mysqli_real_escape_string($conn, $_POST['teacher_id']) . "'" : "NULL";
    $c_sem = intval($_POST['semester_id']);

    // Check if course already exists
    $check_c = mysqli_query($conn, "SELECT course_code FROM courses WHERE course_code = '$c_code'");
    if ($check_c && mysqli_num_rows($check_c) > 0) {
        $course_register_error = "A course with this code already exists.";
    } else {
        $ins_c = "INSERT INTO courses (course_code, course_name, department_id, credits, teacher_id, semester_id) 
                  VALUES ('$c_code', '$c_name', $c_dept, $c_credits, $c_teacher, $c_sem)";
        if (mysqli_query($conn, $ins_c)) {
            $course_register_success = true;
            // Log audit
            $admin_id = mysqli_real_escape_string($conn, $_SESSION['id']);
            $audit_desc = "Registered new course: <strong>$c_name</strong> ($c_code)";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$admin_id', 'course_registered', '$audit_desc')");
            
            header("Location: index.php");
            exit();
        } else {
            $course_register_error = mysqli_error($conn);
        }
    }
}

// --- API Handlers ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'search') {
        $query = $_GET['q'] ?? '';
        $results = [];
        if (strlen($query) > 0) {
            $safe_query = mysqli_real_escape_string($conn, $query);
            $sql = "SELECT c.course_code, c.course_name 
                    FROM courses c
                    WHERE c.course_code LIKE '%$safe_query%' OR c.course_name LIKE '%$safe_query%' 
                    LIMIT 10";
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $results[] = $row;
                }
            }
        }
        echo json_encode($results);
        exit();
    }

    if ($_GET['action'] === 'course_info') {
        $code = $_GET['code'] ?? '';
        $course_info = [];
        if (!empty($code)) {
            $safe_code = mysqli_real_escape_string($conn, $code);
            $sql = "SELECT c.course_code, c.course_name, c.credits,
                           d.name as dept_name,
                           u.name as teacher_name,
                           s.display_name as semester
                    FROM courses c
                    JOIN departments d ON c.department_id = d.department_id
                    LEFT JOIN users u ON c.teacher_id = u.id
                    LEFT JOIN semesters s ON c.semester_id = s.semester_id
                    WHERE c.course_code = '$safe_code'";
            $res = mysqli_query($conn, $sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $course_info = $row;

                // Get enrolled student count
                $count_sql = "SELECT COUNT(*) as enrolled FROM enrollments WHERE course_code = '$safe_code'";
                $count_res = mysqli_query($conn, $count_sql);
                if ($count_res && $count_row = mysqli_fetch_assoc($count_res)) {
                    $course_info['enrolled_students'] = $count_row['enrolled'];
                }

                // Get enrolled student names and IDs
                $students_sql = "SELECT u.id, u.name 
                                 FROM enrollments e 
                                 JOIN users u ON e.student_id = u.id 
                                 WHERE e.course_code = '$safe_code'
                                 ORDER BY u.name ASC";
                $students_res = mysqli_query($conn, $students_sql);
                $enrolled_list = [];
                if ($students_res) {
                    while ($st_row = mysqli_fetch_assoc($students_res)) {
                        $enrolled_list[] = $st_row;
                    }
                }
                $course_info['enrolled_list'] = $enrolled_list;
            }
        }
        echo json_encode($course_info);
        exit();
    }

    if ($_GET['action'] === 'fetch_audit_logs') {
        $audit_q = "SELECT al.log_id, al.description, al.action_type, al.created_at,
                           u.name as user_name
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    ORDER BY al.created_at DESC LIMIT 10";
        $audit_r = mysqli_query($conn, $audit_q);
        $audit_data = [];
        if ($audit_r) {
            while ($row = mysqli_fetch_assoc($audit_r)) {
                $diff = time() - strtotime($row['created_at']);
                if ($diff < 60) $row['time_label'] = 'Just now';
                elseif ($diff < 3600) $row['time_label'] = floor($diff/60) . ' mins ago';
                elseif ($diff < 86400) $row['time_label'] = floor($diff/3600) . ' hours ago';
                else $row['time_label'] = floor($diff/86400) . ' days ago';
                $audit_data[] = $row;
            }
        }
        echo json_encode($audit_data);
        exit();
    }
}

// --- Fetch Course Management Data ---

// Active Students count
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
$active_students = mysqli_fetch_assoc($result)['total'];

// Completion Rate
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM enrollments");
$total_enrollments = mysqli_fetch_assoc($result)['total'];

$result = mysqli_query($conn, "SELECT COUNT(*) as completed FROM enrollments WHERE status='Completed'");
$completed_enrollments = mysqli_fetch_assoc($result)['completed'];

$completion_rate = $total_enrollments > 0 ? round(($completed_enrollments / $total_enrollments) * 100, 1) : 0;

// Count departments with courses
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT department_id) as dept_count FROM courses");
$dept_count = mysqli_fetch_assoc($result)['dept_count'];

// Count active courses
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM courses");
$total_courses = mysqli_fetch_assoc($result)['total'];

// Fetch all courses with department and teacher info
$courses_query = "
    SELECT c.course_code, c.course_name, c.credits, c.semester_id,
           d.name as dept_name, d.department_id,
           u.name as teacher_name
    FROM courses c
    JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN users u ON c.teacher_id = u.id
    ORDER BY c.course_code
";
$courses_result = mysqli_query($conn, $courses_query);
$courses = [];
while ($row = mysqli_fetch_assoc($courses_result)) {
    $courses[] = $row;
}

// Fetch lists for the registration dropdowns
$teachers = [];
$t_res = mysqli_query($conn, "SELECT u.id, u.name, t.initials FROM users u JOIN teachers t ON u.id = t.teacher_id WHERE u.role = 'teacher' AND u.status = 'Active' ORDER BY u.name ASC");
if ($t_res) {
    while ($row = mysqli_fetch_assoc($t_res)) {
        $teachers[] = $row;
    }
}

$departments = [];
$d_res = mysqli_query($conn, "SELECT department_id, name FROM departments ORDER BY name ASC");
if ($d_res) {
    while ($row = mysqli_fetch_assoc($d_res)) {
        $departments[] = $row;
    }
}

$semesters = [];
$s_res = mysqli_query($conn, "SELECT semester_id, display_name FROM semesters ORDER BY academic_year DESC, semester_id DESC");
if ($s_res) {
    while ($row = mysqli_fetch_assoc($s_res)) {
        $semesters[] = $row;
    }
}

// Admin info from session
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin User';
$admin_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'admin@markmetrics.edu';

// Badge color based on department
function getBadgeClass($dept_id)
{
    $classes = ['cs', 'eee', 'bba', 'cs', 'eee'];
    return $classes[($dept_id - 1) % count($classes)];
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Admin Portal</title>
    <meta name="description"
        content="MarkMetrics Course Management – Review and manage active academic programmes across departments.">
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
                <a href="../Dashboard/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="2"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="2"></rect>
                    </svg>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="../CourseManagement/index.php" class="nav-item active">
                    <div class="active-indicator"></div>
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
                    <input type="text" placeholder="Search by course code or course name..." id="searchInput"
                        autocomplete="off">
                    <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
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
                            <span style="font-size:11px; background:rgba(243,112,33,0.12); color:var(--accent-orange); padding:2px 8px; border-radius:20px; font-weight:600;" id="notiCount">0 Pending</span>
                        </div>
                        <div id="notiContent" class="noti-empty">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content-wrapper">
                <?php if ($course_register_success): ?>
                    <div style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 12px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 14px; font-weight: 600;">
                        ✓ Course registered successfully!
                    </div>
                <?php elseif ($course_register_error): ?>
                    <div style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 12px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 14px; font-weight: 600;">
                        ✗ Error: <?php echo htmlspecialchars($course_register_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-titles">
                        <h1>Courses Management</h1>
                        <p>Reviewing @<?php echo $total_courses; ?> active academic programmes across
                            <?php echo $dept_count; ?> departments.</p>
                    </div>
                    <div class="system-status">
                        <span class="system-status-label">System Status</span>
                        <span class="system-status-value">
                            <span class="status-dot"></span>
                            Operational
                        </span>
                    </div>
                </div>

                <!-- Course Stats -->
                <div class="course-stats-grid">
                    <div class="course-stat-card">
                        <span class="course-stat-label">Active Students</span>
                        <div class="course-stat-value">
                            <span class="big" id="activeStudents"><?php echo number_format($active_students); ?></span>
                            <span class="small">+4.2%</span>
                        </div>
                    </div>
                    <div class="course-stat-card">
                        <span class="course-stat-label">Completion Rate</span>
                        <div class="course-stat-value">
                            <span class="big" id="completionRate"><?php echo $completion_rate; ?>%</span>
                        </div>
                    </div>
                    <div class="course-stat-card insight-stat-card">
                        <span class="course-stat-label">MarkMetrics Insights</span>
                        <p class="insight-text">Computer Science enrollment is up by 24% this term.</p>
                    </div>
                </div>

                <!-- Active Catalog -->
                <div class="catalog-header">
                    <div class="catalog-title">
                        <div class="catalog-title-bar"></div>
                        <h2>Active Catalog</h2>
                    </div>
                    <div class="view-toggle">
                        <a class="active" id="gridViewBtn">Grid View</a>
                        <a id="listViewBtn">List View</a>
                    </div>
                </div>

                <!-- Course Cards -->
                <div class="courses-grid" id="coursesGrid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card" data-course-code="<?php echo htmlspecialchars($course['course_code']); ?>">
                            <span
                                class="course-code-badge <?php echo getBadgeClass($course['department_id']); ?>"><?php echo htmlspecialchars($course['course_code']); ?></span>
                            <h3 class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <p class="course-dept"><?php echo htmlspecialchars($course['dept_name']); ?></p>
                            <div class="course-instructor">
                                <div class="instructor-avatar">
                                    <img src="../../Images/OtherUser.jpg"
                                        alt="<?php echo htmlspecialchars($course['teacher_name'] ?? 'TBA'); ?>">
                                </div>
                                <span
                                    class="instructor-name"><?php echo htmlspecialchars($course['teacher_name'] ?? 'TBA'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Register New Program -->
                    <div class="register-card" id="registerProgramCard">
                        <div class="register-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </div>
                        <span class="register-title">Register new Program</span>
                        <span class="register-desc">Start a new curriculum draft.</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Course Info Modal -->
    <div id="userInfoModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" id="closeModalBtn">&times;</button>
            <div class="modal-header">
                <h2 id="modalUserName">Course Name</h2>
                <span id="modalUserRole" class="modal-badge">Code</span>
            </div>
            <div class="modal-body" id="modalUserDetails">
                <!-- Details injected via JS -->
            </div>
        </div>
    </div>

    <!-- Register Course Modal -->
    <div id="registerCourseModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" id="closeRegisterModalBtn">&times;</button>
            <div class="modal-header">
                <h2>Register New Course</h2>
                <span class="modal-badge">ADMIN</span>
            </div>
            <form id="registerCourseForm" method="POST" action="index.php" style="margin-top: 20px; display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseCodeInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Course Code</label>
                    <input type="text" id="courseCodeInput" name="course_code" placeholder="e.g. CSE-4401" required style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px;">
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseNameInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Course Name</label>
                    <input type="text" id="courseNameInput" name="course_name" placeholder="e.g. Database Systems" required style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px;">
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseCreditsInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Credits</label>
                    <select id="courseCreditsInput" name="credits" required style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;">
                        <option value="1.0">1.0</option>
                        <option value="1.5">1.5</option>
                        <option value="3.0" selected>3.0</option>
                        <option value="4.0">4.0</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseDeptInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Department</label>
                    <select id="courseDeptInput" name="department_id" required style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;">
                        <option value="" disabled selected>Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department_id']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseTeacherInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Assign Instructor</label>
                    <select id="courseTeacherInput" name="teacher_id" style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;">
                        <option value="">Select Instructor (TBA)</option>
                        <?php foreach ($teachers as $tchr): ?>
                            <option value="<?php echo htmlspecialchars($tchr['id']); ?>"><?php echo htmlspecialchars($tchr['name']); ?> (<?php echo htmlspecialchars($tchr['initials'] ?? ''); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label for="courseSemesterInput" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Semester</label>
                    <select id="courseSemesterInput" name="semester_id" required style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; color: #fff; outline: none; font-size: 14px; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;">
                        <option value="" disabled selected>Select Semester</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo htmlspecialchars($sem['semester_id']); ?>"><?php echo htmlspecialchars($sem['display_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="register_course" class="new-report-btn" style="margin: 10px 0 0 0; width: 100%; justify-content: center; padding: 14px;">Register Course</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    function toggleAdminNoti(event) {
        if (event) event.stopPropagation();
        const dropdown = document.getElementById('adminNotiDropdown');
        if (!dropdown) return;
        const isVisible = dropdown.style.display !== 'none';
        if (isVisible) { dropdown.style.display = 'none'; return; }
        dropdown.style.display = 'block';
        const notiContent = document.getElementById('notiContent');
        const notiCount = document.getElementById('notiCount');
        fetch('index.php?action=fetch_audit_logs')
            .then(res => res.json())
            .then(logs => {
                if (!logs || logs.length === 0) {
                    notiContent.innerHTML = '<div class="noti-empty">✓ No recent activity</div>';
                    if (notiCount) notiCount.textContent = '0 events';
                    return;
                }
                if (notiCount) notiCount.textContent = logs.length + ' Recent';
                let html = '';
                logs.slice(0, 6).forEach(log => {
                    html += `<div style="display:flex;align-items:flex-start;gap:10px;padding:10px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);margin-bottom:6px;"><div style="width:8px;height:8px;border-radius:50%;background:var(--accent-orange);flex-shrink:0;margin-top:5px;"></div><div><div style="font-size:12px;color:#e4e4e7;line-height:1.4;">${log.description || 'System event'}</div><div style="font-size:10px;color:#71717a;margin-top:3px;">${log.time_label || ''}</div></div></div>`;
                });
                notiContent.innerHTML = html;
            })
            .catch(() => { notiContent.innerHTML = '<div class="noti-empty">Failed to load</div>'; });
    }
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('adminNotiDropdown');
        const bellBtn = document.getElementById('notificationBtn');
        if (dropdown && dropdown.style.display === 'block') {
            if (!dropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
                dropdown.style.display = 'none';
            }
        }
    });
    </script>
</body>

</html>