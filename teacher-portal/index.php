<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../LoginPage/Login/login.php");
    exit();
}
include('../LoginPage/connect2db.php');
$teacher_id = $_SESSION['id'];
$user_q = mysqli_query($conn, "SELECT u.name, u.email, u.profile_picture_url, t.initials FROM users u LEFT JOIN teachers t ON u.id = t.teacher_id WHERE u.id = '$teacher_id'");
$teacher_data = mysqli_fetch_assoc($user_q);
$teacher_name = $teacher_data['name'] ?? $_SESSION['name'];
$teacher_email = $teacher_data['email'] ?? $_SESSION['email'];
$teacher_initials = $teacher_data['initials'] ?? '';
$avatar_db = $teacher_data['profile_picture_url'] ?? '';
$avatar_path = empty($avatar_db) ? 'asset/avatar2.jpg' : '../' . $avatar_db;

// Fetch teacher's courses for the current semester
$current_semester_id = getCurrentSemesterId($conn);
$courses_query = "SELECT c.*, d.name AS dept_name, 
                  (SELECT COUNT(*) FROM enrollments e WHERE e.course_code = c.course_code AND e.semester_id = {$current_semester_id}) AS enrollment_count 
                  FROM courses c 
                  LEFT JOIN departments d ON c.department_id = d.department_id 
                  WHERE c.teacher_id = '{$teacher_id}' AND c.semester_id = {$current_semester_id} AND c.status = 'Active'";
$courses_result = mysqli_query($conn, $courses_query);


ob_start();
include('pages/noti-helper.php');
$noti_modal_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Teacher Portal</title>
    <link rel="stylesheet" href="style.css?v=1.9">
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo-container" style="justify-content: center; padding: 10px 0;">
            <img src="asset/logo.png" alt="MarkMetrics" style="height: 80px; max-width: 100%; object-fit: contain;">
        </div>

        <ul class="menu">
            <li class="active">
                <a href="./index.php" class="active">
                    <i class="fa-solid fa-border-all"></i> Dashboard
                </a>
            </li>

            <li class="dropdown">
                <a href="#">
                    <i class="fa-solid fa-rotate"></i> Academic Actions
                    <?php if (isset($total_pending_actions) && $total_pending_actions > 0): ?>
                        <span class="menu-badge"><?php echo $total_pending_actions; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="submenu">
                    <li>
                         <a href="./pages/withdraw-request.php">
                             Withdraw Requests
                             <?php if (isset($pending_wr_count) && $pending_wr_count > 0): ?>
                                 <span class="menu-badge"><?php echo $pending_wr_count; ?></span>
                             <?php endif; ?>
                         </a>
                     </li>
                     <li>
                         <a href="./pages/grade-management.php">
                             Grade Management
                             <?php if (isset($pending_gcr_count) && $pending_gcr_count > 0): ?>
                                 <span class="menu-badge"><?php echo $pending_gcr_count; ?></span>
                             <?php endif; ?>
                         </a>
                     </li>
                </ul>
            </li>

            <li class="dropdown">
                <a href="#">
                    <i class="fa-solid fa-eye"></i> View
                </a>
                <ul class="submenu">
                     <li>
                         <a href="./pages/academic-performance.php">Academic Performance</a>
                     </li>
                     <li>
                         <a href="./pages/student-history.php">Student History</a>
                     </li>
                </ul>
            </li>
        </ul>

        <a href="./pages/profile.php" class="profile-link-wrapper">
            <div class="profile-box">
                <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Profile">
                <div class="profile-info">
                    <h4><?php echo htmlspecialchars($teacher_name); ?><?php if (!empty($teacher_initials)) { echo ' (' . htmlspecialchars($teacher_initials) . ')'; } ?></h4>
                    <p><?php echo htmlspecialchars($teacher_email); ?></p>
                </div>
            </div>
        </a>

        <div class="logout-btn-container">
            <a href="logout.php" class="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Log Out</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1>My Courses</h1>
                <p>Manage and monitor current semester curriculum (<?php echo SYSTEM_TERM_DISPLAY; ?>).</p>
            </div>
            <!-- Notification Bell -->
            <div style="position: relative;">
                <button id="notiBellBtn" onclick="toggleNotiModal(event)" class="btn-dark" style="position: relative; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); cursor: pointer; background: var(--bg-card); transition: all 0.2s; outline: none;" onmouseover="this.style.borderColor='var(--primary-orange)'; this.style.transform='scale(1.05)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='scale(1)';">
                    <i class="fa-solid fa-bell" style="font-size: 18px; color: <?php echo $total_pending_actions > 0 ? 'var(--primary-orange)' : 'var(--text-secondary)'; ?>;"></i>
                    <?php if ($total_pending_actions > 0): ?>
                        <span style="position: absolute; top: -4px; right: -4px; background: var(--color-red); color: #fff; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg-dark); box-shadow: 0 0 8px rgba(255, 59, 59, 0.4);"><?php echo $total_pending_actions; ?></span>
                    <?php endif; ?>
                </button>
                <?php echo $noti_modal_html; ?>
            </div>
        </div>

        <div class="course-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php
            $colors = [
                'linear-gradient(135deg, var(--primary-orange), #ff4000)',
                'linear-gradient(135deg, var(--color-blue), #0056b3)',
                'linear-gradient(135deg, var(--color-green), #008f48)',
                'linear-gradient(135deg, #8b5cf6, #6d28d9)'
            ];
            $color_idx = 0;
            if ($courses_result && mysqli_num_rows($courses_result) > 0) {
                while ($course = mysqli_fetch_assoc($courses_result)) {
                    $grad = $colors[$color_idx % count($colors)];
                    $color_idx++;
                    $c_code = htmlspecialchars($course['course_code']);
                    $c_name = htmlspecialchars($course['course_name']);
                    $c_desc = htmlspecialchars($course['dept_name'] ?? 'Curriculum course');
                    $c_section = htmlspecialchars($course['section'] ?? 'A');
                    $enrollment_count = intval($course['enrollment_count'] ?? 0);
                    ?>
                    <a href="pages/marks-entry.php?course=<?php echo urlencode($course['course_code']); ?>&name=<?php echo urlencode($course['course_name']); ?>" style="text-decoration: none;">
                        <div class="stat-card" style="display: flex; flex-direction: column; gap: 15px; transition: transform 0.2s; cursor: pointer; height: 100%;">
                            <div style="background: <?php echo $grad; ?>; height: 100px; border-radius: 6px; width: 100%;"></div>
                            <div>
                                <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 5px;"><?php echo str_replace('-', ' ', $c_code) . ': ' . $c_name; ?></h3>
                                <p style="color: var(--text-secondary); font-size: 13px;"><?php echo $c_desc; ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; font-size: 12px; color: var(--text-secondary); border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 10px;">
                                    <span><i class="fa-solid fa-users" style="margin-right: 4px; color: var(--primary-orange);"></i> <strong><?php echo $enrollment_count; ?></strong> Enrolled</span>
                                    <span><i class="fa-solid fa-layer-group" style="margin-right: 4px; color: var(--color-blue);"></i> Sec: <strong><?php echo $c_section; ?></strong></span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php
                }
            } else {
                echo '<p style="color: var(--text-secondary);">No courses assigned yet.</p>';
            }
            ?>
        </div>

        <!-- PREVIOUS SEMESTERS COURSES -->
    </div>

    <script src="script.js"></script>
</body>
</html>