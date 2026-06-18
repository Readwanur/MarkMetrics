<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}
include('../../LoginPage/connect2db.php');

$teacher_id = $_SESSION['id'];
ob_start();
include('noti-helper.php');
$noti_modal_html = ob_get_clean();
$user_q = mysqli_query($conn, "SELECT u.name, u.email, u.profile_picture_url, t.initials FROM users u LEFT JOIN teachers t ON u.id = t.teacher_id WHERE u.id = '$teacher_id'");
$teacher_data = mysqli_fetch_assoc($user_q);
$teacher_name = $teacher_data['name'] ?? $_SESSION['name'];
$teacher_email = $teacher_data['email'] ?? $_SESSION['email'];
$teacher_initials = $teacher_data['initials'] ?? '';
$avatar_db = $teacher_data['profile_picture_url'] ?? '';
$avatar_path = empty($avatar_db) ? '../asset/avatar2.jpg' : '../../' . $avatar_db;

// Course parameter parsing
$course_code = isset($_GET['course']) ? $_GET['course'] : 'CSE-4165';
// Normalize course code formatting (e.g. CSE4165 to CSE-4165)
if (strpos($course_code, '-') === false && strlen($course_code) > 3) {
    $course_code = substr($course_code, 0, 3) . '-' . substr($course_code, 3);
}

// Fetch course details
$course_q = mysqli_query($conn, "SELECT course_name, semester_id FROM courses WHERE course_code = '$course_code'");
$course_info = mysqli_fetch_assoc($course_q);
$course_name = $course_info ? $course_info['course_name'] : 'Advanced Algorithms';
$semester_id = $course_info ? $course_info['semester_id'] : getCurrentSemesterId($conn);

$success_msg = '';
$error_msg = '';

// POST Handler for saving marks
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_marks'])) {
    $midterms = $_POST['midterm'] ?? [];
    $finals = $_POST['final'] ?? [];
    $cts = $_POST['ct'] ?? [];
    $assignments = $_POST['assignment'] ?? [];
    $grades = $_POST['grade'] ?? [];
    
    $update_success = true;
    foreach ($midterms as $student_id => $mid_val) {
        // Skip if student is already dropped/withdrawn in DB
        $check_dropped_q = mysqli_query($conn, "SELECT status FROM enrollments WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id");
        $check_dropped = mysqli_fetch_assoc($check_dropped_q);
        if ($check_dropped && $check_dropped['status'] === 'Dropped') {
            continue;
        }

        $fin_val = $finals[$student_id] ?? '';
        $ct_val = $cts[$student_id] ?? '';
        $assign_val = $assignments[$student_id] ?? '';
        $grade_override = $grades[$student_id] ?? 'AUTO';
        
        $mid_db = ($mid_val === '' || !is_numeric($mid_val)) ? "NULL" : floatval($mid_val);
        $fin_db = ($fin_val === '' || !is_numeric($fin_val)) ? "NULL" : floatval($fin_val);
        $ct_db = ($ct_val === '' || !is_numeric($ct_val)) ? "NULL" : floatval($ct_val);
        $assign_db = ($assign_val === '' || !is_numeric($assign_val)) ? "NULL" : floatval($assign_val);
        
        // Range validation guards
        if ($mid_db !== "NULL" && ($mid_db < 0 || $mid_db > 30)) { $update_success = false; $error_msg = "Midterm score must be between 0 and 30."; break; }
        if ($fin_db !== "NULL" && ($fin_db < 0 || $fin_db > 40)) { $update_success = false; $error_msg = "Final score must be between 0 and 40."; break; }
        if ($ct_db !== "NULL" && ($ct_db < 0 || $ct_db > 20)) { $update_success = false; $error_msg = "CT score must be between 0 and 20."; break; }
        if ($assign_db !== "NULL" && ($assign_db < 0 || $assign_db > 10)) { $update_success = false; $error_msg = "Assignment score must be between 0 and 10."; break; }

        // Fetch current enrollment grade and status from DB
        $curr_enroll_q = mysqli_query($conn, "SELECT grade, points, status FROM enrollments WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id");
        $curr_enroll = mysqli_fetch_assoc($curr_enroll_q);
        $curr_db_grade = $curr_enroll['grade'] ?? '';
        $curr_db_points = $curr_enroll['points'] ?? 0.00;
        $curr_db_status = $curr_enroll['status'] ?? 'Ongoing';

        // Check if there is an approved grade correction request
        $gcr_check_q = mysqli_query($conn, "SELECT new_grade FROM grade_correction_requests WHERE student_id = '$student_id' AND course_code = '$course_code' AND status = 'Approved' LIMIT 1");
        $has_approved_gcr = ($gcr_check_q && mysqli_num_rows($gcr_check_q) > 0);

        if ($curr_db_grade === 'W') {
            $grade = "'W'";
            $points = "0.00";
            $status = "'Dropped'";
        } elseif ($curr_db_grade === 'I' || $curr_db_grade === 'INC') {
            $grade = "'I'";
            $points = "0.00";
            $status = "'Ongoing'";
        } elseif ($has_approved_gcr) {
            // Keep the updated/approved grade
            $grade = "'" . mysqli_real_escape_string($conn, $curr_db_grade) . "'";
            $points = "'" . mysqli_real_escape_string($conn, $curr_db_points) . "'";
            $status = "'Completed'";
        } else {
            // Compute grade & points normally
            if ($mid_db === "NULL" || $fin_db === "NULL" || $ct_db === "NULL" || $assign_db === "NULL") {
                $grade = "'Running'";
                $points = "0.00";
                $status = "'Ongoing'";
            } else {
                $total = $mid_db + $fin_db + $ct_db + $assign_db;
                
                // Dynamic database scale mapping
                $grade_look = mysqli_query($conn, "SELECT grade, points FROM grading_scale WHERE $total BETWEEN min_score AND max_score LIMIT 1");
                if ($grade_look && $gl_row = mysqli_fetch_assoc($grade_look)) {
                    $grade = "'" . mysqli_real_escape_string($conn, $gl_row['grade']) . "'";
                    $points = "'" . mysqli_real_escape_string($conn, $gl_row['points']) . "'";
                } else {
                    $grade = "'F'";
                    $points = "0.00";
                }
                
                $status = "'Completed'";
            }
        }
        
        $update_sql = "UPDATE enrollments 
                       SET midterm_score = $mid_db, final_score = $fin_db, ct_score = $ct_db, assignment_score = $assign_db, grade = $grade, points = $points, status = $status 
                       WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id";
        
        if (!mysqli_query($conn, $update_sql)) {
            $update_success = false;
            $error_msg = 'Error updating marks: ' . mysqli_error($conn);
            break;
        }
    }
    
    if ($update_success) {
        // Log action to audit_logs
        $teacher_id = $_SESSION['id'];
        $log_desc = "<strong>$teacher_name</strong> ($teacher_id) updated marks for Course: $course_code";
        mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$teacher_id', 'Mark Updated', '$log_desc')");
        
        $success_msg = 'Changes saved successfully!';
    }
}

// Search, filter, and sort parameters handling
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($conn, trim($_GET['sort'])) : '';
$filter_grade = isset($_GET['filter_grade']) ? mysqli_real_escape_string($conn, trim($_GET['filter_grade'])) : '';
$filter_marks = isset($_GET['filter_marks']) ? mysqli_real_escape_string($conn, trim($_GET['filter_marks'])) : '';
$filter_pending = isset($_GET['filter_pending']) ? intval($_GET['filter_pending']) : 0;

// 1. Build search and filter conditions
$filter_cond = "";
if (!empty($search)) {
    $filter_cond .= " AND (u.name LIKE '%$search%' OR u.id LIKE '%$search%')";
}
if (!empty($filter_grade)) {
    $filter_cond .= " AND e.grade = '$filter_grade'";
}
if (!empty($filter_marks)) {
    $parts = explode('-', $filter_marks);
    if (count($parts) === 2) {
        $min_m = floatval($parts[0]);
        $max_m = floatval($parts[1]);
        $filter_cond .= " AND e.total_score BETWEEN $min_m AND $max_m";
    }
}
if ($filter_pending === 1) {
    $filter_cond .= " AND (e.midterm_score IS NULL OR e.final_score IS NULL OR e.ct_score IS NULL OR e.assignment_score IS NULL)";
}

// 2. Build sorting order
$order_by = "u.id ASC"; // default
if ($sort === 'name_asc') {
    $order_by = "u.name ASC";
} elseif ($sort === 'name_desc') {
    $order_by = "u.name DESC";
} elseif ($sort === 'id_asc') {
    $order_by = "u.id ASC";
} elseif ($sort === 'id_desc') {
    $order_by = "u.id DESC";
}

// Fetch all students for search suggestions (both name and ID)
$all_students_query = "SELECT u.id, u.name 
                       FROM enrollments e 
                       JOIN users u ON e.student_id = u.id 
                       WHERE e.course_code = '$course_code' AND e.semester_id = $semester_id";
$all_students_result = mysqli_query($conn, $all_students_query);
$suggestions = [];
if ($all_students_result) {
    while ($row = mysqli_fetch_assoc($all_students_result)) {
        $suggestions[] = $row;
    }
}

// Count total enrolled students with filters applied
$count_query = "SELECT COUNT(*) AS total 
                FROM enrollments e 
                JOIN users u ON e.student_id = u.id 
                WHERE e.course_code = '$course_code' AND e.semester_id = $semester_id $filter_cond";
$count_q = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_q);
$total_students = $count_data ? intval($count_data['total']) : 0;

// Overall total enrolled students for stat box
$overall_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM enrollments WHERE course_code = '$course_code' AND semester_id = $semester_id");
$overall_data = mysqli_fetch_assoc($overall_q);
$overall_total_students = $overall_data ? intval($overall_data['total']) : 0;

// Pagination calculations
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == 1;
$limit = $show_all ? max(1, $total_students) : 15;
$page = isset($_GET['page']) && !$show_all ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_students / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Fetch enrolled students for current page with sorting and filters
$students_query = "SELECT u.id, u.name, e.midterm_score, e.final_score, e.ct_score, e.assignment_score, e.total_score, e.grade, e.status
                   FROM enrollments e
                   JOIN users u ON e.student_id = u.id
                   WHERE e.course_code = '$course_code' AND e.semester_id = $semester_id $filter_cond
                   ORDER BY $order_by
                   LIMIT $limit OFFSET $offset";
$students_res = mysqli_query($conn, $students_query);

// Helper function to build page link with active params
$query_params = [];
$query_params['course'] = $course_code;
if (!empty($search)) $query_params['search'] = $search;
if (!empty($sort)) $query_params['sort'] = $sort;
if (!empty($filter_grade)) $query_params['filter_grade'] = $filter_grade;
if (!empty($filter_marks)) $query_params['filter_marks'] = $filter_marks;
if ($filter_pending === 1) $query_params['filter_pending'] = 1;

function buildPageUrl($p, $show_all_flag = null) {
    global $query_params, $show_all;
    $params = $query_params;
    if ($show_all_flag !== null) {
        if ($show_all_flag) $params['show_all'] = 1;
    } else {
        if ($show_all) $params['show_all'] = 1;
    }
    if ($p !== null && !$show_all_flag) {
        $params['page'] = $p;
    }
    return '?' . http_build_query($params);
}

// Count pending marks (overall course)
$pending_q = mysqli_query($conn, "SELECT COUNT(*) AS pending FROM enrollments WHERE course_code = '$course_code' AND semester_id = $semester_id AND (midterm_score IS NULL OR final_score IS NULL OR ct_score IS NULL OR assignment_score IS NULL)");
$pending_data = mysqli_fetch_assoc($pending_q);
$pending_marks = $pending_data ? $pending_data['pending'] : 0;

// Calculate Average CGPA
$cgpa_q = mysqli_query($conn, "SELECT AVG(s.cumulative_gpa) AS avg_cgpa FROM enrollments e JOIN students s ON e.student_id = s.student_id WHERE e.course_code = '$course_code' AND e.semester_id = $semester_id");
$cgpa_data = mysqli_fetch_assoc($cgpa_q);
$average_cgpa = $cgpa_data && $cgpa_data['avg_cgpa'] !== null ? number_format($cgpa_data['avg_cgpa'], 2) : '0.00';

// Helper for Grade Badges
function getGradeBadge($grade) {
    switch ($grade) {
        case 'A':
            return '<span class="badge" style="background: rgba(0, 210, 106, 0.1); color: var(--color-green); border: 1px solid var(--color-green);">A</span>';
        case 'A-':
            return '<span class="badge" style="background: rgba(0, 210, 106, 0.1); color: var(--color-green);">A-</span>';
        case 'B+':
            return '<span class="badge" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">B+</span>';
        case 'B':
            return '<span class="badge" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">B</span>';
        case 'B-':
            return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red);">B-</span>';
        case 'C+':
            return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue);">C+</span>';
        case 'C':
            return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue);">C</span>';
        case 'C-':
            return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue); opacity: 0.8;">C-</span>';
        case 'D+':
            return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red);">D+</span>';
        case 'D':
            return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red); opacity: 0.8;">D</span>';
        case 'F':
            return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red); border: 1px solid var(--color-red);">F</span>';
        case 'I':
            return '<span class="badge badge-dark" style="background: rgba(156, 163, 175, 0.1); color: #e5e7eb; border: 1px solid #4b5563;">I</span>';
        case 'W':
            return '<span class="badge badge-dark" style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid #dc2626;">W</span>';
        case 'Running':
            return '<span class="badge badge-dark" style="background: rgba(148, 163, 184, 0.1); color: #cbd5e1; border: 1px solid #475569;">Running</span>';
        case 'INC':
        default:
            return '<span class="badge badge-dark">INC</span>';
    }
}

// Time ago helper for logs
function get_time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    $minutes      = round($seconds / 60 );
    $hours        = round($seconds / 3600);
    $days         = round($seconds / 86400);
    
    if($seconds <= 60) {
        return "JUST NOW";
    } else if($minutes <= 60) {
        return ($minutes == 1) ? "1 MIN AGO" : "$minutes MINS AGO";
    } else if($hours <= 24) {
        return ($hours == 1) ? "1 HOUR AGO" : "$hours HOURS AGO";
    } else if($days <= 7) {
        return ($days == 1) ? "1 DAY AGO" : "$days DAYS AGO";
    } else {
        return date('d M Y', $time_ago);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Teacher Portal</title>
    <link rel="stylesheet" href="../style.css?v=1.9">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Hide HTML5 spin buttons */
        input.mark-input::-webkit-outer-spin-button,
        input.mark-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input.mark-input {
            -moz-appearance: textfield;
            background: #1a1a24;
            color: #fff;
            border: 1px solid #3a3a48;
            border-radius: 4px;
            padding: 6px 10px;
            width: 70px;
            text-align: center;
        }
        input.mark-input:focus {
            outline: none;
            border-color: var(--primary-orange);
        }
        input.mark-input.invalid-mark {
            border-color: #ff3b3b !important;
            background: rgba(255, 59, 59, 0.1) !important;
            color: #ff3b3b !important;
        }
        select.btn-dark {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 28px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'><path d='M1 1.5L5 4.5L9 1.5' stroke='%238f8f9d' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 10px 6px;
            cursor: pointer;
            outline: none;
        }
        select.btn-dark:focus {
            border-color: var(--primary-orange) !important;
            color: var(--text-primary) !important;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container" style="justify-content: center; padding: 10px 0;">
            <img src="../asset/logo.png" alt="MarkMetrics" style="height: 80px; max-width: 100%; object-fit: contain;">
        </div>

        <ul class="menu">
            <li>
                <a href="../index.php">
                    <i class="fa-solid fa-border-all"></i> Dashboard
                </a>
            </li>

            <li class="dropdown active">
                <a href="#">
                    <i class="fa-solid fa-rotate"></i> Academic Actions
                    <?php if (isset($total_pending_actions) && $total_pending_actions > 0): ?>
                        <span class="menu-badge"><?php echo $total_pending_actions; ?></span>
                    <?php endif; ?>
                </a>
               <ul class="submenu show">
                    <li>
                        <a href="withdraw-request.php">
                            Withdraw Requests
                            <?php if (isset($pending_wr_count) && $pending_wr_count > 0): ?>
                                <span class="menu-badge"><?php echo $pending_wr_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="grade-management.php">
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
                        <a href="academic-performance.php">Academic Performance</a>
                    </li>
                    <li>
                        <a href="student-history.php">Student History</a>
                    </li>
                </ul>
            </li>
        </ul>

        <a href="profile.php" class="profile-link-wrapper">
            <div class="profile-box">
                <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Profile">
                <div class="profile-info">
                    <h4><?php echo htmlspecialchars($teacher_name); ?><?php if (!empty($teacher_initials)) { echo ' (' . htmlspecialchars($teacher_initials) . ')'; } ?></h4>
                    <p><?php echo htmlspecialchars($teacher_email); ?></p>
                </div>
            </div>
        </a>

        <div class="logout-btn-container">
            <a href="../logout.php" class="logout-btn">
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="../index.php" class="btn-back" style="margin: 0;"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
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
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px;">
            <div class="page-header" style="margin-bottom: 0; max-width: 60%;">
                <h1>Marks Entry</h1>
                <p>Data-intensive academic evaluation for Course: <?php echo htmlspecialchars($course_name); ?> (<?php echo htmlspecialchars($course_code); ?>) [<?php echo SYSTEM_TERM_DISPLAY; ?>]. Last synced 4 minutes ago.</p>
            </div>

            <div class="marks-stats-row">
                <div class="marks-stat <?php echo ($filter_pending !== 1) ? 'orange' : 'blue'; ?>" style="cursor: pointer; user-select: none;" onclick="window.location.href='<?php 
                    $temp_params = $query_params; 
                    unset($temp_params['filter_pending']); 
                    echo '?' . http_build_query($temp_params); 
                ?>'">
                    <h4>TOTAL STUDENTS</h4>
                    <h2><?php echo str_pad($overall_total_students, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="marks-stat <?php echo ($filter_pending === 1) ? 'orange' : 'blue'; ?>" style="cursor: pointer; user-select: none;" onclick="window.location.href='<?php 
                    $temp_params = $query_params; 
                    $temp_params['filter_pending'] = 1; 
                    echo '?' . http_build_query($temp_params); 
                ?>'">
                    <h4>PENDING MARKS</h4>
                    <h2><?php echo str_pad($pending_marks, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="marks-stat red">
                    <h4>AVERAGE CGPA</h4>
                    <h2><?php echo htmlspecialchars($average_cgpa); ?></h2>
                </div>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div style="background: rgba(0, 210, 106, 0.1); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(255, 59, 59, 0.1); border: 1px solid var(--color-red); color: var(--color-red); padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="marksForm">
            <div class="layout-with-sidebar">
                
                <!-- Left side: Table -->
                <div class="table-container">
                    <div class="table-header-actions">
                        <div class="table-actions-left" style="display: flex; gap: 10px; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 8px; background: #13131a; border: 1px solid var(--border-color); border-radius: 6px; padding: 5px 12px; width: 220px;">
                                <i class="fa-solid fa-magnifying-glass" style="color: var(--text-secondary); font-size: 12px;"></i>
                                <input type="text" id="studentSearchInput" placeholder="Search name or ID..." list="studentSuggestions" value="<?php echo htmlspecialchars($search); ?>" style="background: transparent; border: none; color: #fff; outline: none; font-size: 13px; width: 100%;" onkeydown="if(event.key === 'Enter') { event.preventDefault(); doStudentSearch(); }">
                                <datalist id="studentSuggestions">
                                    <?php foreach ($suggestions as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['id']); ?></option>
                                        <option value="<?php echo htmlspecialchars($s['id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <?php if (!empty($search) || !empty($sort) || !empty($filter_grade) || !empty($filter_marks)): ?>
                                    <a href="?course=<?php echo urlencode($course_code); ?><?php echo $show_all ? '&show_all=1' : ''; ?>" style="color: var(--text-secondary); text-decoration: none; font-size: 13px;" title="Clear Search & Filters"><i class="fa-solid fa-xmark"></i></a>
                                <?php endif; ?>
                            </div>
                             <select id="filterGradeSelect" onchange="doStudentSearch()" class="btn-dark" style="outline: none; cursor: pointer; padding: 8px 12px;">
                                <option value="" style="background: var(--bg-card); color: #fff;">Grade (All)</option>
                                <option value="A" <?php echo $filter_grade === 'A' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade A</option>
                                <option value="A-" <?php echo $filter_grade === 'A-' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade A-</option>
                                <option value="B+" <?php echo $filter_grade === 'B+' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade B+</option>
                                <option value="B" <?php echo $filter_grade === 'B' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade B</option>
                                <option value="B-" <?php echo $filter_grade === 'B-' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade B-</option>
                                <option value="C+" <?php echo $filter_grade === 'C+' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade C+</option>
                                <option value="C" <?php echo $filter_grade === 'C' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade C</option>
                                <option value="C-" <?php echo $filter_grade === 'C-' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade C-</option>
                                <option value="D+" <?php echo $filter_grade === 'D+' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade D+</option>
                                <option value="D" <?php echo $filter_grade === 'D' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade D</option>
                                <option value="F" <?php echo $filter_grade === 'F' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade F</option>
                                <option value="I" <?php echo $filter_grade === 'I' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade I (Incomplete)</option>
                                <option value="W" <?php echo $filter_grade === 'W' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Grade W (Withdrawal)</option>
                            </select>
                            <select id="filterMarksSelect" onchange="doStudentSearch()" class="btn-dark" style="outline: none; cursor: pointer; padding: 8px 12px;">
                                <option value="" style="background: var(--bg-card); color: #fff;">Marks (All)</option>
                                <option value="90-100" <?php echo $filter_marks === '90-100' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">90 - 100</option>
                                <option value="80-89" <?php echo $filter_marks === '80-89' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">80 - 89</option>
                                <option value="70-79" <?php echo $filter_marks === '70-79' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">70 - 79</option>
                                <option value="60-69" <?php echo $filter_marks === '60-69' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">60 - 69</option>
                                <option value="50-59" <?php echo $filter_marks === '50-59' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">50 - 59</option>
                                <option value="0-49" <?php echo $filter_marks === '0-49' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Below 50</option>
                            </select>
                            <select id="sortSelect" onchange="doStudentSearch()" class="btn-dark" style="outline: none; cursor: pointer; padding: 8px 12px;">
                                <option value="" style="background: var(--bg-card); color: #fff;">Sort By</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Name: A to Z</option>
                                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">Name: Z to A</option>
                                <option value="id_asc" <?php echo $sort === 'id_asc' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">ID: Ascending</option>
                                <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?> style="background: var(--bg-card); color: #fff;">ID: Descending</option>
                            </select>
                        </div>
                        <div class="table-actions-right">
                            <button type="submit" name="save_marks" class="btn-orange"><i class="fa-solid fa-save"></i> Save Changes</button>
                            <button type="button" class="btn-dark" onclick="window.location.href='course-analytics-report.php?course=<?php echo urlencode($course_code); ?>'"><i class="fa-solid fa-file-pdf"></i> Generate Report Card</button>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>SL. NO.</th>
                                <th>STUDENT ID</th>
                                <th>STUDENT NAME</th>
                                <th>CT (20)</th>
                                <th>ASSIGNMENT (10)</th>
                                <th>MID (30)</th>
                                <th>FINAL (40)</th>
                                <th>TOTAL</th>
                                <th>GRADE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($students_res && mysqli_num_rows($students_res) > 0) {
                                $row_idx = 0;
                                while ($row = mysqli_fetch_assoc($students_res)) {
                                    $name_parts = explode(' ', $row['name']);
                                    $initials = (count($name_parts) >= 2) 
                                        ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1))
                                        : strtoupper(substr($row['name'], 0, 2));
                                    
                                    $mid_val = ($row['midterm_score'] === null) ? '' : floatval($row['midterm_score']);
                                    $fin_val = ($row['final_score'] === null) ? '' : floatval($row['final_score']);
                                    $ct_val = ($row['ct_score'] === null) ? '' : floatval($row['ct_score']);
                                    $assign_val = ($row['assignment_score'] === null) ? '' : floatval($row['assignment_score']);
                                    $total_val = ($row['total_score'] === null || ($row['midterm_score'] === null && $row['final_score'] === null && $row['ct_score'] === null && $row['assignment_score'] === null)) ? '--' : floatval($row['total_score']);
                                    
                                    $is_dropped = ($row['status'] === 'Dropped' || $row['grade'] === 'W');
                                    $disabled_attr = $is_dropped ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : '';
                                    
                                    $sl_no = $offset + $row_idx + 1;
                                    $row_idx++;

                                     $has_approved_gcr = false;
                                     $original_grade = '';
                                     $gcr_check_q = mysqli_query($conn, "SELECT current_grade FROM grade_correction_requests WHERE student_id = '" . $row['id'] . "' AND course_code = '$course_code' AND status = 'Approved' LIMIT 1");
                                     if ($gcr_check_q && $gcr_row = mysqli_fetch_assoc($gcr_check_q)) {
                                         $has_approved_gcr = true;
                                         $original_grade = $gcr_row['current_grade'];
                                     }
                                     $is_locked_grade = ($row['grade'] === 'W' || $row['grade'] === 'I' || $row['grade'] === 'INC' || $has_approved_gcr);
                                    ?>
                                    <tr id="row-<?php echo htmlspecialchars($row['id']); ?>" 
                                        data-lock-grade="<?php echo $is_locked_grade ? '1' : '0'; ?>"
                                        style="<?php echo $is_dropped ? 'opacity: 0.8; background-color: rgba(255,255,255,0.01);' : ''; ?>">
                                        <td style="color: var(--text-secondary);"><?php echo $sl_no; ?></td>
                                        <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td>
                                            <a href="student-history.php?student_id=<?php echo urlencode($row['id']); ?>" class="student-name student-col">
                                                <div class="student-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <input type="number" step="0.5" min="0" max="20" class="mark-input ct-input" 
                                                   name="ct[<?php echo htmlspecialchars($row['id']); ?>]" 
                                                   value="<?php echo htmlspecialchars($ct_val); ?>" placeholder="--"
                                                   data-student-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                   oninput="validateRange(this, 0, 20, '<?php echo htmlspecialchars($row['id']); ?>')" <?php echo $disabled_attr; ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.5" min="0" max="10" class="mark-input assignment-input" 
                                                   name="assignment[<?php echo htmlspecialchars($row['id']); ?>]" 
                                                   value="<?php echo htmlspecialchars($assign_val); ?>" placeholder="--"
                                                   data-student-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                   oninput="validateRange(this, 0, 10, '<?php echo htmlspecialchars($row['id']); ?>')" <?php echo $disabled_attr; ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.5" min="0" max="30" class="mark-input midterm-input" 
                                                   name="midterm[<?php echo htmlspecialchars($row['id']); ?>]" 
                                                   value="<?php echo htmlspecialchars($mid_val); ?>" placeholder="--"
                                                   data-student-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                   oninput="validateRange(this, 0, 30, '<?php echo htmlspecialchars($row['id']); ?>')" <?php echo $disabled_attr; ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.5" min="0" max="40" class="mark-input final-input" 
                                                   name="final[<?php echo htmlspecialchars($row['id']); ?>]" 
                                                   value="<?php echo htmlspecialchars($fin_val); ?>" placeholder="--"
                                                   data-student-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                   oninput="validateRange(this, 0, 40, '<?php echo htmlspecialchars($row['id']); ?>')" <?php echo $disabled_attr; ?>>
                                        </td>
                                        <td class="total-cell" style="font-weight: 600; color: #fff;"><?php echo $total_val; ?></td>
                                         <td>
                                             <div style="display: flex; align-items: center; gap: 8px;">
                                                 <span id="badge-<?php echo htmlspecialchars($row['id']); ?>">
                                                     <?php echo getGradeBadge($row['grade']); ?>
                                                 </span>
                                             </div>
                                         </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 20px;">No students enrolled in this course.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <div class="pagination" style="margin-top: 20px;">
                        <div>
                            Showing <span style="font-weight: 600; color: #fff;"><?php echo min($offset + 1, $total_students); ?>-<?php echo min($offset + $limit, $total_students); ?></span> of <span style="font-weight: 600; color: #fff;"><?php echo $total_students; ?></span> records
                            <?php if ($show_all): ?>
                                | <a href="<?php echo buildPageUrl(1, false); ?>" class="text-orange" style="text-decoration: none; margin-left: 10px; font-weight: 500;">Show Paginated</a>
                            <?php else: ?>
                                | <a href="<?php echo buildPageUrl(null, true); ?>" class="text-orange" style="text-decoration: none; margin-left: 10px; font-weight: 500;">Show All Students</a>
                            <?php endif; ?>
                        </div>
                        <div class="page-controls">
                            <?php if (!$show_all && $page > 1): ?>
                                <a href="<?php echo buildPageUrl($page - 1); ?>" style="color: var(--text-secondary); text-decoration: none;"><i class="fa-solid fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php if (!$show_all): ?>
                            <div class="page-numbers">
                                <?php
                                for ($p = 1; $p <= $total_pages; $p++) {
                                    $active_class = ($p == $page) ? 'active' : '';
                                    echo "<a href='" . buildPageUrl($p) . "' class='page-btn $active_class'>$p</a>";
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!$show_all && $page < $total_pages): ?>
                                <a href="<?php echo buildPageUrl($page + 1); ?>" class="next-page">NEXT PAGE <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right side: Change Log -->
                <div class="change-log-panel">
                    <h3>Change Log</h3>
                    
                    <?php
                    $logs_q = mysqli_query($conn, "SELECT * FROM audit_logs WHERE action_type IN ('Mark Updated', 'user_login', 'New Grade Entry', 'System Configuration', 'Bulk Action') ORDER BY created_at DESC LIMIT 5");
                    if ($logs_q && mysqli_num_rows($logs_q) > 0) {
                        while ($log = mysqli_fetch_assoc($logs_q)) {
                            ?>
                            <div class="log-item">
                                <h4><?php echo htmlspecialchars($log['action_type']); ?></h4>
                                <p><?php echo $log['description']; ?></p>
                                <span><?php echo get_time_ago($log['created_at']); ?></span>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p style="color: var(--text-secondary);">No recent activity logged.</p>';
                    }
                    ?>

                    <button type="button" class="btn-dark" style="width: 100%; justify-content: center; margin-top: 10px;">VIEW ALL ACTIVITY</button>
                </div>

            </div>
        </form>

    </div>

    <script>
        function getJsGradeBadge(grade) {
            switch (grade) {
                case 'A':
                    return '<span class="badge" style="background: rgba(0, 210, 106, 0.1); color: var(--color-green); border: 1px solid var(--color-green);">A</span>';
                case 'A-':
                    return '<span class="badge" style="background: rgba(0, 210, 106, 0.1); color: var(--color-green);">A-</span>';
                case 'B+':
                    return '<span class="badge" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">B+</span>';
                case 'B':
                    return '<span class="badge" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">B</span>';
                case 'B-':
                    return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red);">B-</span>';
                case 'C+':
                    return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue);">C+</span>';
                case 'C':
                    return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue);">C</span>';
                case 'C-':
                    return '<span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue); opacity: 0.8;">C-</span>';
                case 'D+':
                    return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red);">D+</span>';
                case 'D':
                    return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red); opacity: 0.8;">D</span>';
                case 'F':
                    return '<span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red); border: 1px solid var(--color-red);">F</span>';
                case 'I':
                    return '<span class="badge badge-dark" style="background: rgba(156, 163, 175, 0.1); color: #e5e7eb; border: 1px solid #4b5563;">I</span>';
                case 'W':
                    return '<span class="badge badge-dark" style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid #dc2626;">W</span>';
                case 'Running':
                    return '<span class="badge badge-dark" style="background: rgba(148, 163, 184, 0.1); color: #cbd5e1; border: 1px solid #475569;">Running</span>';
                case 'INC':
                default:
                    return '<span class="badge badge-dark">INC</span>';
            }
        }

        function calculateRow(studentId) {
            const row = document.getElementById('row-' + studentId);
            if (!row) return;

            const isLocked = row.getAttribute('data-lock-grade') === '1';

            const ctInput = row.querySelector('.ct-input');
            const assignInput = row.querySelector('.assignment-input');
            const midInput = row.querySelector('.midterm-input');
            const finInput = row.querySelector('.final-input');
            const totalCell = row.querySelector('.total-cell');
            const badgeSpan = document.getElementById('badge-' + studentId);

            const ctVal = ctInput.value === '' ? null : parseFloat(ctInput.value);
            const assignVal = assignInput.value === '' ? null : parseFloat(assignInput.value);
            const midVal = midInput.value === '' ? null : parseFloat(midInput.value);
            const finVal = finInput.value === '' ? null : parseFloat(finInput.value);

            // Calculate Total
            let total = null;
            if (ctVal !== null || assignVal !== null || midVal !== null || finVal !== null) {
                total = (ctVal || 0) + (assignVal || 0) + (midVal || 0) + (finVal || 0);
            }
            
            if (totalCell) {
                totalCell.textContent = total === null ? '--' : total.toFixed(1).replace(/\.0$/, '');
            }

            if (isLocked) {
                return;
            }

            // Determine Auto Grade
            let autoGrade = 'Running';
            if (ctVal !== null && assignVal !== null && midVal !== null && finVal !== null &&
                !ctInput.classList.contains('invalid-mark') &&
                !assignInput.classList.contains('invalid-mark') &&
                !midInput.classList.contains('invalid-mark') &&
                !finInput.classList.contains('invalid-mark')) {
                
                let sum = ctVal + assignVal + midVal + finVal;
                if (sum >= 90) autoGrade = 'A';
                else if (sum >= 86) autoGrade = 'A-';
                else if (sum >= 82) autoGrade = 'B+';
                else if (sum >= 78) autoGrade = 'B';
                else if (sum >= 74) autoGrade = 'B-';
                else if (sum >= 70) autoGrade = 'C+';
                else if (sum >= 66) autoGrade = 'C';
                else if (sum >= 62) autoGrade = 'C-';
                else if (sum >= 58) autoGrade = 'D+';
                else if (sum >= 55) autoGrade = 'D';
                else autoGrade = 'F';
            }

            if (badgeSpan) {
                badgeSpan.innerHTML = getJsGradeBadge(autoGrade);
            }
        }

        function validateRange(input, min, max, studentId) {
            if (input.value === '') {
                input.classList.remove('invalid-mark');
            } else {
                let val = parseFloat(input.value);
                if (isNaN(val) || val < min || val > max) {
                    input.classList.add('invalid-mark');
                } else {
                    input.classList.remove('invalid-mark');
                }
            }
            calculateRow(studentId);
        }

        document.getElementById('marksForm').addEventListener('submit', function(e) {
            let invalidInputs = this.querySelectorAll('.invalid-mark');
            if (invalidInputs.length > 0) {
                e.preventDefault();
                alert('Please correct the out-of-range marks (highlighted in red) before saving.');
            }
        });

        // Search trigger function
        function doStudentSearch() {
            let searchVal = document.getElementById('studentSearchInput').value.trim();
            let gradeVal = document.getElementById('filterGradeSelect').value;
            let marksVal = document.getElementById('filterMarksSelect').value;
            let sortVal = document.getElementById('sortSelect').value;
            
            let url = "?course=<?php echo urlencode($course_code); ?>";
            if (searchVal !== "") {
                url += "&search=" + encodeURIComponent(searchVal);
            }
            if (gradeVal !== "") {
                url += "&filter_grade=" + encodeURIComponent(gradeVal);
            }
            if (marksVal !== "") {
                url += "&filter_marks=" + encodeURIComponent(marksVal);
            }
            if (sortVal !== "") {
                url += "&sort=" + encodeURIComponent(sortVal);
            }
            <?php if ($show_all): ?>
                url += "&show_all=1";
            <?php endif; ?>
            window.location.href = url;
        }

        // Run initial range validation on all inputs on load
        window.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.mark-input').forEach(input => {
                let min = parseFloat(input.getAttribute('min'));
                let max = parseFloat(input.getAttribute('max'));
                let studentId = input.getAttribute('data-student-id');
                validateRange(input, min, max, studentId);
            });
        });
    </script>
    <script src="../script.js"></script>
</body>
</html>