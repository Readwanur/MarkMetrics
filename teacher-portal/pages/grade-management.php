<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}
include('../../LoginPage/connect2db.php');
$teacher_id = $_SESSION['id'];

// Mark pending grade correction requests as read
mysqli_query($conn, "
    UPDATE grade_correction_requests gcr 
    JOIN courses c ON gcr.course_code = c.course_code 
    SET gcr.is_read = 1 
    WHERE c.teacher_id = '$teacher_id' AND gcr.status = 'Pending'
");

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

// Submit new request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $student_id_post = mysqli_real_escape_string($conn, $_POST['student_id']);
    $course_code_post = mysqli_real_escape_string($conn, $_POST['course_code']);
    $current_grade_post = mysqli_real_escape_string($conn, $_POST['current_grade']);
    $desired_grade_post = mysqli_real_escape_string($conn, $_POST['desired_grade']);
    $justification_post = mysqli_real_escape_string($conn, $_POST['justification']);

    $desired_points = 0.00;
    $current_points = 0.00;
    
    $p1 = mysqli_query($conn, "SELECT points FROM grading_scale WHERE grade = '$desired_grade_post'");
    if ($r1 = mysqli_fetch_assoc($p1)) $desired_points = floatval($r1['points']);
    
    $p2 = mysqli_query($conn, "SELECT points FROM grading_scale WHERE grade = '$current_grade_post'");
    if ($r2 = mysqli_fetch_assoc($p2)) $current_points = floatval($r2['points']);
    
    if ($desired_points >= $current_points) {
        $check = mysqli_query($conn, "SELECT * FROM grade_correction_requests WHERE student_id = '$student_id_post' AND course_code = '$course_code_post' AND status = 'Pending'");
        if ($check && mysqli_num_rows($check) > 0) {
            $_SESSION['grade_error'] = "A pending grade correction request already exists for this student in this course.";
        } else {
            $ins = mysqli_query($conn, "
                INSERT INTO grade_correction_requests (student_id, course_code, current_grade, new_grade, justification, requested_by, status)
                VALUES ('$student_id_post', '$course_code_post', '$current_grade_post', '$desired_grade_post', '$justification_post', '$teacher_id', 'Pending')
            ");
            if ($ins) {
                $_SESSION['grade_success'] = "Grade correction request submitted successfully.";
            } else {
                $_SESSION['grade_error'] = "Failed to submit request. Please try again.";
            }
        }
    } else {
        $_SESSION['grade_error'] = "Desired grade cannot be lower than the current grade.";
    }
    header("Location: grade-management.php");
    exit();
}

// Edit existing request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request'])) {
    $req_id_post = intval($_POST['request_id']);
    $action_type = $_POST['action_type'] ?? 'approve';
    $justification_post = mysqli_real_escape_string($conn, $_POST['justification']);
    $current_grade_post = mysqli_real_escape_string($conn, $_POST['current_grade']);

    if ($action_type === 'dismiss') {
        // Dismiss (Reject) request
        $upd = mysqli_query($conn, "
            UPDATE grade_correction_requests 
            SET status = 'Rejected', resolved_at = NOW(), resolved_by = '$teacher_id', justification = '$justification_post'
            WHERE request_id = $req_id_post AND status = 'Pending'
        ");
        if ($upd) {
            $_SESSION['grade_success'] = "Grade correction request dismissed.";
        } else {
            $_SESSION['grade_error'] = "Failed to dismiss request.";
        }
    } else {
        // Approve request
        $desired_grade_post = mysqli_real_escape_string($conn, $_POST['desired_grade']);
        
        $desired_points = 0.00;
        $current_points = 0.00;
        
        $p1 = mysqli_query($conn, "SELECT points FROM grading_scale WHERE grade = '$desired_grade_post'");
        if ($r1 = mysqli_fetch_assoc($p1)) $desired_points = floatval($r1['points']);
        
        $p2 = mysqli_query($conn, "SELECT points FROM grading_scale WHERE grade = '$current_grade_post'");
        if ($r2 = mysqli_fetch_assoc($p2)) $current_points = floatval($r2['points']);

        if ($desired_points > $current_points) {
            $upd = mysqli_query($conn, "
                UPDATE grade_correction_requests 
                SET new_grade = '$desired_grade_post', status = 'Approved', resolved_at = NOW(), resolved_by = '$teacher_id', justification = '$justification_post'
                WHERE request_id = $req_id_post AND status = 'Pending'
            ");
            if ($upd) {
                $req_info_q = mysqli_query($conn, "SELECT student_id, course_code FROM grade_correction_requests WHERE request_id = $req_id_post");
                if ($req_info = mysqli_fetch_assoc($req_info_q)) {
                    $student_id_val = $req_info['student_id'];
                    $course_code_val = $req_info['course_code'];
                    
                    $upd_enroll = mysqli_query($conn, "
                        UPDATE enrollments 
                        SET grade = '$desired_grade_post', points = $desired_points 
                        WHERE student_id = '$student_id_val' AND course_code = '$course_code_val'
                    ");
                    if ($upd_enroll) {
                        $_SESSION['grade_success'] = "Grade correction request approved and grade updated successfully.";
                    } else {
                        $_SESSION['grade_success'] = "Grade request approved, but failed to update enrollment record.";
                    }
                }
            } else {
                $_SESSION['grade_error'] = "Failed to approve request.";
            }
        } else {
            $_SESSION['grade_error'] = "Desired grade must be higher than the current grade.";
        }
    }
    header("Location: grade-management.php");
    exit();
}

// Fetch stats for courses taught by this teacher
$stats_pending_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM grade_correction_requests gcr JOIN courses c ON gcr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND gcr.status = 'Pending' AND gcr.current_grade != 'INC' AND gcr.current_grade != 'W'");
$stats_pending = mysqli_fetch_assoc($stats_pending_q)['total'] ?? 0;

$stats_rejected_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM grade_correction_requests gcr JOIN courses c ON gcr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND gcr.status = 'Rejected' AND gcr.current_grade != 'INC' AND gcr.current_grade != 'W'");
$stats_rejected = mysqli_fetch_assoc($stats_rejected_q)['total'] ?? 0;

$stats_approved_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM grade_correction_requests gcr JOIN courses c ON gcr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND gcr.status = 'Approved' AND gcr.current_grade != 'INC' AND gcr.current_grade != 'W'");
$stats_approved = mysqli_fetch_assoc($stats_approved_q)['total'] ?? 0;

// Fetch teacher's courses
$courses_q = mysqli_query($conn, "SELECT course_code, course_name FROM courses WHERE teacher_id = '$teacher_id' AND status = 'Active'");
$my_courses = [];
if ($courses_q) {
    while ($row = mysqli_fetch_assoc($courses_q)) {
        $my_courses[] = $row;
    }
}

// Fetch enrolled students
$enrollments_q = mysqli_query($conn, "
    SELECT e.student_id, u.name AS student_name, e.course_code, e.grade, gs.points
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_code = c.course_code
    LEFT JOIN grading_scale gs ON e.grade = gs.grade
    WHERE c.teacher_id = '$teacher_id' AND e.status = 'Ongoing'
");
$my_students = [];
if ($enrollments_q) {
    while ($row = mysqli_fetch_assoc($enrollments_q)) {
        $my_students[] = $row;
    }
}

// Fetch grading scale
$scale_q = mysqli_query($conn, "SELECT grade, points FROM grading_scale ORDER BY points DESC");
$grading_scale = [];
if ($scale_q) {
    while ($row = mysqli_fetch_assoc($scale_q)) {
        $grading_scale[] = $row;
    }
}

// Filter options for pagination and list
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'new';
$limit = isset($_GET['limit']) ? $_GET['limit'] : '5';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$where_clauses = ["c.teacher_id = '$teacher_id'", "gcr.current_grade != 'INC'", "gcr.current_grade != 'W'"];
if (!empty($status_filter) && $status_filter !== 'All') {
    $safe_status = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "gcr.status = '$safe_status'";
}
$where_sql = implode(" AND ", $where_clauses);

// Count total all (unfiltered by status)
$count_all_q = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM grade_correction_requests gcr 
    JOIN courses c ON gcr.course_code = c.course_code
    WHERE c.teacher_id = '$teacher_id' AND gcr.current_grade != 'INC' AND gcr.current_grade != 'W'
");
$total_records_all = mysqli_fetch_assoc($count_all_q)['total'] ?? 0;

// Count total (filtered by status)
$count_q = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM grade_correction_requests gcr 
    JOIN courses c ON gcr.course_code = c.course_code
    WHERE $where_sql
");
$total_records = mysqli_fetch_assoc($count_q)['total'] ?? 0;

$limit_sql = "";
if ($limit !== 'all') {
    $limit_val = intval($limit);
    $offset = ($page - 1) * $limit_val;
    $limit_sql = "LIMIT $offset, $limit_val";
} else {
    $limit_val = $total_records;
    $page = 1;
}

$sort_order = ($sort === 'old') ? 'ASC' : 'DESC';

$requests_q = mysqli_query($conn, "
    SELECT gcr.request_id, gcr.student_id, gcr.course_code, gcr.current_grade, gcr.new_grade, gcr.status, gcr.created_at, gcr.justification,
           u.name AS student_name, c.course_name
    FROM grade_correction_requests gcr
    JOIN users u ON gcr.student_id = u.id
    JOIN courses c ON gcr.course_code = c.course_code
    WHERE $where_sql
    ORDER BY gcr.created_at $sort_order
    $limit_sql
");

$requests_list = [];
if ($requests_q) {
    while ($row = mysqli_fetch_assoc($requests_q)) {
        $requests_list[] = $row;
    }
}

// Calculate pagination counting boundaries
if ($limit === 'all') {
    $start_record = $total_records > 0 ? 1 : 0;
    $end_record = $total_records;
} else {
    $limit_val = intval($limit);
    $start_record = $total_records > 0 ? ($page - 1) * $limit_val + 1 : 0;
    $end_record = min($total_records, $start_record + count($requests_list) - 1);
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
                <a href="#" class="active">
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
                    <li class="active">
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
        
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Grade Change Requests</h1>
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

        <div class="stats-row">
            <div class="stat-card lightblue <?php if (empty($status_filter) || $status_filter === 'All') echo 'active-filter'; ?>" onclick="window.location.href='?status=All&sort=<?php echo $sort; ?>&limit=<?php echo $limit; ?>'">
                <div>
                    <h4>ALL</h4>
                    <h2><?php echo str_pad($total_records_all, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-list"></i></div>
            </div>

            <div class="stat-card orange <?php if ($status_filter === 'Pending') echo 'active-filter'; ?>" onclick="window.location.href='?status=Pending&sort=<?php echo $sort; ?>&limit=<?php echo $limit; ?>'">
                <div>
                    <h4>PENDING</h4>
                    <h2><?php echo str_pad($stats_pending, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
            </div>

            <div class="stat-card red <?php if ($status_filter === 'Rejected') echo 'active-filter'; ?>" onclick="window.location.href='?status=Rejected&sort=<?php echo $sort; ?>&limit=<?php echo $limit; ?>'">
                <div>
                    <h4>DISMISSED</h4>
                    <h2><?php echo str_pad($stats_rejected, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-xmark"></i></div>
            </div>

            <div class="stat-card green <?php if ($status_filter === 'Approved') echo 'active-filter'; ?>" onclick="window.location.href='?status=Approved&sort=<?php echo $sort; ?>&limit=<?php echo $limit; ?>'">
                <div>
                    <h4>APPROVED</h4>
                    <h2 style="color: var(--color-green);"><?php echo str_pad($stats_approved, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-check"></i></div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header-actions" style="border-bottom: none; align-items: center;">
                <h3 style="font-size: 16px;">Grade Management</h3>
                <div class="table-actions-right" style="align-items: center; gap: 15px;">
                    <a href="?sort=<?php echo ($sort === 'new') ? 'old' : 'new'; ?>&status=<?php echo $status_filter; ?>&limit=<?php echo $limit; ?>&page=<?php echo $page; ?>" class="btn-dark" style="background: transparent; border: none; padding: 5px; text-decoration: none;" title="Toggle Sort (Newest/Oldest)"><i class="fa-solid fa-arrow-down-short-wide" style="color: <?php echo ($sort === 'old') ? 'var(--primary-orange)' : 'var(--text-secondary)'; ?>;"></i></a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>COURSE CODE</th>
                        <th>COURSE NAME</th>
                        <th>STUDENT ID</th>
                        <th>STUDENT NAME</th>
                        <th>CURRENT GRADE</th>
                        <th>DESIRED GRADE</th>
                        <th>DATE & TIME</th>
                        <th>STATUS</th>
                        <th style="text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests_list)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No grade correction requests found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests_list as $req): 
                            $status_val = $req['status'];
                            if ($status_val === 'Pending') {
                                $badge = '<span class="badge badge-dark">PENDING</span>';
                            } elseif ($status_val === 'Approved') {
                                $badge = '<span class="badge badge-green">APPROVED</span>';
                            } else {
                                $badge = '<span class="badge badge-red">DISMISSED</span>';
                            }
                            
                            $curr_grade = $req['current_grade'] ?? 'F';
                            $pts_q = mysqli_query($conn, "SELECT points FROM grading_scale WHERE grade = '$curr_grade'");
                            $curr_pts = 0.00;
                            if ($pts_q && $pts_row = mysqli_fetch_assoc($pts_q)) {
                                $curr_pts = floatval($pts_row['points']);
                            }
                        ?>
                            <tr class="request-row" data-status="<?php echo $status_val; ?>">
                                <td class="text-orange" style="font-weight: 700;"><?php echo htmlspecialchars($req['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($req['course_name']); ?></td>
                                <td><a href="student-history.php?student_id=<?php echo urlencode($req['student_id']); ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 500;" onmouseover="this.style.textDecoration='underline'; this.style.color='var(--primary-orange)';" onmouseout="this.style.textDecoration='none'; this.style.color='var(--text-primary)';"><?php echo htmlspecialchars($req['student_id']); ?></a></td>
                                <td><a href="student-history.php?student_id=<?php echo urlencode($req['student_id']); ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 500;" onmouseover="this.style.textDecoration='underline'; this.style.color='var(--primary-orange)';" onmouseout="this.style.textDecoration='none'; this.style.color='var(--text-primary)';"><?php echo htmlspecialchars($req['student_name']); ?></a></td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.05); color: #fff;"><?php echo htmlspecialchars($req['current_grade']); ?></span></td>
                                <td><span class="badge" style="background: rgba(245,130,32,0.1); color: var(--primary-orange);"><?php echo htmlspecialchars($req['new_grade']); ?></span></td>
                                <td><span style="color: var(--text-secondary); font-size: 13px;"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></span></td>
                                <td><?php echo $badge; ?></td>
                                <td style="text-align: right;">
                                    <?php if ($status_val === 'Pending'): ?>
                                        <button class="btn-orange" style="padding: 4px 10px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer;" onclick="openEditModal('<?php echo $req['request_id']; ?>', '<?php echo htmlspecialchars(addslashes($req['student_name'])); ?>', '<?php echo htmlspecialchars($req['student_id']); ?>', '<?php echo htmlspecialchars($req['current_grade']); ?>', '<?php echo $curr_pts; ?>', '<?php echo htmlspecialchars($req['new_grade']); ?>', '<?php echo htmlspecialchars(addslashes($req['justification'] ?? '')); ?>')">Change</button>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--text-secondary);">Resolved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="empty-filter-row" style="display: <?php echo empty($requests_list) ? '' : 'none'; ?>;">
                        <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                            No <?php echo !empty($status_filter) && $status_filter !== 'All' ? htmlspecialchars(strtolower($status_filter)) : ''; ?> requests found matching this filter.
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php
            $num_pages = ceil($total_records / (is_numeric($limit) ? intval($limit) : 5));
            if ($num_pages < 1) $num_pages = 1;
            ?>
            <div class="pagination">
                <div>Showing <span style="font-weight: 600; color: #fff;"><?php echo ($start_record == $end_record) ? $start_record : "$start_record-$end_record"; ?></span> of <span style="font-weight: 600; color: #fff;"><?php echo $total_records; ?></span> records</div>
                <div class="page-controls" style="display: flex; align-items: center; gap: 10px;">
                    <a href="?page=<?php echo max(1, $page - 1); ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sort; ?>&status=<?php echo urlencode($status_filter); ?>" style="color: var(--text-secondary); text-decoration: none; <?php if($page <= 1) echo 'pointer-events: none; opacity: 0.5;'; ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    <div class="page-numbers">
                        <?php for ($i = 1; $i <= $num_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sort; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn <?php if ($i === $page) echo 'active'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <a href="?page=<?php echo min($num_pages, $page + 1); ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sort; ?>&status=<?php echo urlencode($status_filter); ?>" class="next-page" style="<?php if($page >= $num_pages) echo 'pointer-events: none; opacity: 0.5;'; ?>">NEXT PAGE <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i></a>
                    
                    <div style="height: 18px; width: 1.5px; background: rgba(255, 255, 255, 0.1); margin: 0 5px;"></div>
                    <?php if ($limit === 'all'): ?>
                        <a href="?page=1&limit=5&sort=<?php echo $sort; ?>&status=<?php echo urlencode($status_filter); ?>" class="btn-orange" style="padding: 6px 12px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center; border-radius: 4px; border: none; cursor: pointer; color: #fff; font-weight: 600;">Paginate (5)</a>
                    <?php else: ?>
                        <a href="?page=1&limit=all&sort=<?php echo $sort; ?>&status=<?php echo urlencode($status_filter); ?>" class="btn-orange" style="padding: 6px 12px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center; border-radius: 4px; border: none; cursor: pointer; color: #fff; font-weight: 600;">Show All</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Styles for interactive stat cards -->
    <style>
        .stat-card {
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent !important;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        .stat-card.lightblue {
            border-left: 3px solid #00d2ff;
        }
        .stat-card.lightblue h2 {
            color: #00d2ff;
        }
        .stat-card.lightblue .stat-icon {
            background: rgba(0, 210, 255, 0.1);
            color: #00d2ff;
        }
        .stat-card.active-filter.lightblue {
            border-color: #00d2ff !important;
            background: rgba(0, 210, 255, 0.05) !important;
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.15) !important;
        }
        .stat-card.active-filter.orange {
            border-color: var(--primary-orange) !important;
            background: rgba(255, 107, 0, 0.05) !important;
            box-shadow: 0 0 15px rgba(255, 107, 0, 0.15) !important;
        }
        .stat-card.active-filter.red {
            border-color: var(--color-red) !important;
            background: rgba(255, 59, 59, 0.05) !important;
            box-shadow: 0 0 15px rgba(255, 59, 59, 0.15) !important;
        }
        .stat-card.active-filter.green {
            border-color: var(--color-green) !important;
            background: rgba(0, 210, 106, 0.05) !important;
            box-shadow: 0 0 15px rgba(0, 210, 106, 0.15) !important;
        }
    </style>

    <!-- New Request Modal -->
    <div id="newRequestModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 30px; width: 480px; box-shadow: 0 15px 40px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #fff;">New Grade Change Request</h3>
                <i class="fa-solid fa-xmark" onclick="closeNewRequestModal()" style="cursor: pointer; color: var(--text-secondary); font-size: 18px;"></i>
            </div>
            
            <form method="POST">
                <input type="hidden" name="submit_request" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">COURSE</label>
                    <select name="course_code" id="modalCourseSelect" required style="width: 100%; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none;">
                        <option value="">Select Course...</option>
                        <?php foreach ($my_courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']) . ': ' . htmlspecialchars($c['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">STUDENT</label>
                    <select name="student_id" id="modalStudentSelect" required disabled style="width: 100%; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none;">
                        <option value="">Select Student...</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">CURRENT GRADE</label>
                        <input type="text" id="modalCurrentGradeDisplay" name="current_grade" readonly style="width: 100%; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: var(--text-secondary); outline: none; font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">DESIRED GRADE</label>
                        <select name="desired_grade" id="modalDesiredGradeSelect" required disabled style="width: 100%; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none;">
                            <option value="">Select Grade...</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">JUSTIFICATION</label>
                    <textarea name="justification" required placeholder="State the reason for grade correction..." style="width: 100%; height: 80px; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none; resize: none; font-size: 13px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeNewRequestModal()" class="btn-dark" style="padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn-orange" style="padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: 600;">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Request Modal -->
    <div id="editRequestModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 30px; width: 480px; box-shadow: 0 15px 40px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #fff;">Edit Grade Request</h3>
                <i class="fa-solid fa-xmark" onclick="closeEditRequestModal()" style="cursor: pointer; color: var(--text-secondary); font-size: 18px;"></i>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_request" value="1">
                <input type="hidden" name="request_id" id="editRequestId">
                <input type="hidden" name="current_grade" id="editCurrentGradeHidden">
                <input type="hidden" name="action_type" id="editActionType" value="approve">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">STUDENT</label>
                    <input type="text" id="editStudentDisplay" readonly style="width: 100%; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: var(--text-secondary); outline: none;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">CURRENT GRADE</label>
                        <input type="text" id="editCurrentGradeDisplay" readonly style="width: 100%; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: var(--text-secondary); outline: none; font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">DESIRED GRADE</label>
                        <select name="desired_grade" id="editDesiredGradeSelect" required style="width: 100%; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none;">
                            <option value="">Select Grade...</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 600;">JUSTIFICATION</label>
                    <textarea name="justification" id="editJustification" required placeholder="State the reason for grade correction..." style="width: 100%; height: 80px; background: #111; border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: #fff; outline: none; resize: none; font-size: 13px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeEditRequestModal()" class="btn-dark" style="padding: 10px 20px;">Cancel</button>
                    <button type="button" onclick="submitAsDismiss()" class="btn-red" style="padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: 600; background: var(--color-red);">Dismiss Request</button>
                    <button type="submit" class="btn-orange" style="padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: 600;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        const myStudents = <?php echo json_encode($my_students); ?>;
        const gradingScale = <?php echo json_encode($grading_scale); ?>;

        // Modal triggers
        function openNewRequestModal() {
            document.getElementById('newRequestModal').style.display = 'flex';
        }
        function closeNewRequestModal() {
            document.getElementById('newRequestModal').style.display = 'none';
        }
        function openEditModal(requestId, studentName, studentId, currentGrade, currentPoints, desiredGrade, justification) {
            document.getElementById('editRequestId').value = requestId;
            document.getElementById('editCurrentGradeHidden').value = currentGrade;
            document.getElementById('editStudentDisplay').value = `${studentName} (${studentId})`;
            document.getElementById('editCurrentGradeDisplay').value = currentGrade;
            document.getElementById('editJustification').value = justification;
            document.getElementById('editActionType').value = 'approve';
            document.getElementById('editDesiredGradeSelect').required = true;

            const editDesiredSelect = document.getElementById('editDesiredGradeSelect');
            editDesiredSelect.innerHTML = '<option value="">Select Grade...</option>';

            gradingScale.forEach(g => {
                const gradePoints = parseFloat(g.points);
                if (gradePoints > parseFloat(currentPoints)) {
                    const opt = document.createElement('option');
                    opt.value = g.grade;
                    opt.textContent = `${g.grade} (${parseFloat(g.points).toFixed(2)})`;
                    if (g.grade === desiredGrade) {
                        opt.selected = true;
                    }
                    editDesiredSelect.appendChild(opt);
                }
            });

            document.getElementById('editRequestModal').style.display = 'flex';
        }
        function closeEditRequestModal() {
            document.getElementById('editRequestModal').style.display = 'none';
        }
        function submitAsDismiss() {
            document.getElementById('editActionType').value = 'dismiss';
            document.getElementById('editDesiredGradeSelect').required = false;
            document.getElementById('editForm').submit();
        }

        // Modal Select Logic
        const courseSelect = document.getElementById('modalCourseSelect');
        const studentSelect = document.getElementById('modalStudentSelect');
        const currentGradeDisplay = document.getElementById('modalCurrentGradeDisplay');
        const desiredGradeSelect = document.getElementById('modalDesiredGradeSelect');

        courseSelect.addEventListener('change', function() {
            studentSelect.innerHTML = '<option value="">Select Student...</option>';
            studentSelect.disabled = true;
            currentGradeDisplay.value = '';
            desiredGradeSelect.innerHTML = '<option value="">Select Grade...</option>';
            desiredGradeSelect.disabled = true;

            const courseCode = this.value;
            if (!courseCode) return;

            const filteredStudents = myStudents.filter(s => s.course_code === courseCode);
            
            filteredStudents.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.student_id;
                opt.textContent = `${s.student_name} (${s.student_id})`;
                opt.dataset.grade = s.grade || 'N/A';
                opt.dataset.points = s.points || 0.00;
                studentSelect.appendChild(opt);
            });

            if (filteredStudents.length > 0) {
                studentSelect.disabled = false;
            }
        });

        studentSelect.addEventListener('change', function() {
            currentGradeDisplay.value = '';
            desiredGradeSelect.innerHTML = '<option value="">Select Grade...</option>';
            desiredGradeSelect.disabled = true;

            const selectedOpt = this.options[this.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) return;

            const currentGrade = selectedOpt.dataset.grade;
            const currentPoints = parseFloat(selectedOpt.dataset.points || 0);

            currentGradeDisplay.value = currentGrade;

            gradingScale.forEach(g => {
                const gradePoints = parseFloat(g.points);
                if (gradePoints >= currentPoints) {
                    const opt = document.createElement('option');
                    opt.value = g.grade;
                    opt.textContent = `${g.grade} (${parseFloat(g.points).toFixed(2)})`;
                    desiredGradeSelect.appendChild(opt);
                }
            });

            desiredGradeSelect.disabled = false;
        });

        // Status Card Filtering
        document.addEventListener('DOMContentLoaded', function() {
            // No-op: handled server-side now.
        });
    </script>
</body>
</html>