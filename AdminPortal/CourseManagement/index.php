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
            }
        }
        echo json_encode($course_info);
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
                        <div class="course-card">
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

    <script src="script.js"></script>
</body>

</html>