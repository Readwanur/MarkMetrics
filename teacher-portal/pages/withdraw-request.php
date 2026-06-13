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

// Action handling
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    $req_q = mysqli_query($conn, "SELECT wr.* FROM withdraw_requests wr JOIN courses c ON wr.course_code = c.course_code WHERE wr.request_id = $request_id AND c.teacher_id = '$teacher_id'");
    if ($req_data = mysqli_fetch_assoc($req_q)) {
        $student_id = $req_data['student_id'];
        $course_code = $req_data['course_code'];
        $semester_id = $req_data['semester_id'];
        
        if ($action === 'approve') {
            mysqli_query($conn, "UPDATE withdraw_requests SET status = 'Approved', resolved_at = NOW() WHERE request_id = $request_id");
            mysqli_query($conn, "UPDATE enrollments SET status = 'Dropped', grade = 'W', points = 0.00 WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id");
            
            // Log to audit logs
            $log_desc = "<strong>$teacher_name</strong> ($teacher_id) approved Withdraw Request for Student: <strong>$student_id</strong> in Course: $course_code";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$teacher_id', 'Bulk Action', '$log_desc')");
            
            $_SESSION['withdraw_success'] = "Withdraw request for student $student_id approved successfully!";
        } elseif ($action === 'reject') {
            mysqli_query($conn, "UPDATE withdraw_requests SET status = 'Rejected', resolved_at = NOW() WHERE request_id = $request_id");
            
            // Log to audit logs
            $log_desc = "<strong>$teacher_name</strong> ($teacher_id) rejected Withdraw Request for Student: <strong>$student_id</strong> in Course: $course_code";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$teacher_id', 'Bulk Action', '$log_desc')");
            
            $_SESSION['withdraw_success'] = "Withdraw request for student $student_id rejected successfully!";
        }
    }
    header("Location: withdraw-request.php");
    exit();
}

$withdraw_success = '';
if (isset($_SESSION['withdraw_success'])) {
    $withdraw_success = $_SESSION['withdraw_success'];
    unset($_SESSION['withdraw_success']);
}

// Count stats for this teacher's courses
$stats_pending_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM withdraw_requests wr JOIN courses c ON wr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND wr.status = 'Pending'");
$stats_pending = mysqli_fetch_assoc($stats_pending_q)['total'] ?? 0;

$stats_rejected_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM withdraw_requests wr JOIN courses c ON wr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND wr.status = 'Rejected'");
$stats_rejected = mysqli_fetch_assoc($stats_rejected_q)['total'] ?? 0;

$stats_approved_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM withdraw_requests wr JOIN courses c ON wr.course_code = c.course_code WHERE c.teacher_id = '$teacher_id' AND wr.status = 'Approved'");
$stats_approved = mysqli_fetch_assoc($stats_approved_q)['total'] ?? 0;

$total_withdraw_all = $stats_pending + $stats_rejected + $stats_approved;

// Fetch teacher's courses
$courses_q = mysqli_query($conn, "SELECT * FROM courses WHERE teacher_id = '$teacher_id'");
$courses_list = [];
if ($courses_q) {
    while ($course = mysqli_fetch_assoc($courses_q)) {
        $c_code = $course['course_code'];
        // Fetch withdraw requests for this course
        $reqs_q = mysqli_query($conn, "
            SELECT wr.request_id, wr.student_id, wr.status, u.name, e.midterm_score 
            FROM withdraw_requests wr
            JOIN users u ON wr.student_id = u.id
            LEFT JOIN enrollments e ON wr.student_id = e.student_id AND wr.course_code = e.course_code AND wr.semester_id = e.semester_id
            WHERE wr.course_code = '$c_code'
            ORDER BY wr.created_at DESC
        ");
        $reqs = [];
        if ($reqs_q) {
            while ($req = mysqli_fetch_assoc($reqs_q)) {
                $reqs[] = $req;
            }
        }
        $course['requests'] = $reqs;
        $courses_list[] = $course;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Teacher Portal</title>
    <link rel="stylesheet" href="../style.css?v=1.8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="#" class="active">
                    <i class="fa-solid fa-rotate"></i> Academic Actions
                </a>
                <ul class="submenu show">
                    <li class="active">
                        <a href="withdraw-request.php">Withdraw Requests</a>
                    </li>
                    <li>
                        <a href="grade-management.php">Grade Management</a>
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
            <h1>Withdraw Management</h1>
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

        <?php if (!empty($withdraw_success)): ?>
            <div style="background: rgba(0, 210, 106, 0.1); border: 1px solid var(--color-green); color: var(--color-green); padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($withdraw_success); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card lightblue active-filter" data-filter="All">
                <div>
                    <h4>ALL REQUESTS</h4>
                    <h2><?php echo str_pad($total_withdraw_all, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-list"></i></div>
            </div>

            <div class="stat-card orange" data-filter="Pending">
                <div>
                    <h4>PENDING REQUESTS</h4>
                    <h2><?php echo str_pad($stats_pending, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-clipboard"></i></div>
            </div>

            <div class="stat-card red" data-filter="Rejected">
                <div>
                    <h4>DISMISSED</h4>
                    <h2><?php echo str_pad($stats_rejected, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-xmark"></i></div>
            </div>

            <div class="stat-card green" data-filter="Approved">
                <div>
                    <h4>APPROVED</h4>
                    <h2 style="color: var(--color-green);"><?php echo str_pad($stats_approved, 2, '0', STR_PAD_LEFT); ?></h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-check"></i></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
            <h3 style="font-size: 18px;">Semester Course Load</h3>
            <p style="color: var(--text-secondary); font-size: 12px;">Active Academic Curriculum</p>
        </div>

        <?php if (empty($courses_list)): ?>
            <p style="color: var(--text-secondary);">No assigned courses found.</p>
        <?php else: ?>
            <?php foreach ($courses_list as $index => $course): 
                $num_reqs = count($course['requests']);
            ?>
                <!-- Accordion Item -->
                <div class="accordion-item <?php echo ($index === 0) ? 'active' : ''; ?>">
                    <div class="accordion-header" style="border-bottom: 1px solid var(--border-color);">
                        <div class="accordion-header-left">
                            <div class="course-icon"><i class="fa-solid fa-code"></i></div>
                            <div class="accordion-title">
                                <h3><?php echo htmlspecialchars($course['course_code']) . ': ' . htmlspecialchars($course['course_name']); ?></h3>
                                <p>Credits: <?php echo htmlspecialchars($course['credits']); ?></p>
                            </div>
                        </div>
                        <div class="accordion-stats" style="display: flex; align-items: center; gap: 20px;">
                            <div style="text-align: right;">
                                <div style="font-size: 16px;"><?php echo $num_reqs; ?> Students</div>
                            </div>
                            <i class="fa-solid <?php echo ($index === 0) ? 'fa-chevron-up' : 'fa-chevron-down'; ?>"></i>
                        </div>
                    </div>
                    
                    <div class="accordion-content" style="<?php echo ($index === 0) ? 'display: block;' : 'display: none;'; ?> background: #1a1a20;">
                        <table style="background: transparent;">
                            <thead>
                                <tr style="background: #24242d;">
                                    <th>Student ID</th>
                                    <th>STUDENT DETAILS</th>
                                    <th>Mid Marks</th>
                                    <th>STATUS</th>
                                    <th style="text-align: right;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($num_reqs === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                            No withdraw requests found for this course.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($course['requests'] as $req): 
                                        $student_id_val = htmlspecialchars($req['student_id']);
                                        $name_val = htmlspecialchars($req['name']);
                                        $mid_val = ($req['midterm_score'] === null) ? '--' : floatval($req['midterm_score']);
                                        $status_val = $req['status'];
                                        
                                        // Get initials
                                        $words = explode(' ', $name_val);
                                        $initials = '';
                                        foreach ($words as $w) {
                                            $initials .= strtoupper(substr($w, 0, 1));
                                        }
                                        $initials = substr($initials, 0, 2);
                                        
                                        // Badge styling
                                        if ($status_val === 'Pending') {
                                            $badge = '<span class="badge" style="background: rgba(255,107,0,0.2); color: var(--primary-orange);">PENDING</span>';
                                        } elseif ($status_val === 'Approved') {
                                            $badge = '<span class="badge" style="background: rgba(0,210,106,0.2); color: var(--color-green);">APPROVED</span>';
                                        } else {
                                            $badge = '<span class="badge" style="background: rgba(255,59,59,0.2); color: var(--color-red);">REJECTED</span>';
                                        }
                                    ?>
                                        <tr class="request-row" data-status="<?php echo $status_val; ?>">
                                            <td style="font-weight: 700;"><a href="student-history.php?student_id=<?php echo urlencode($req['student_id']); ?>" style="color: var(--text-primary); text-decoration: none;" onmouseover="this.style.textDecoration='underline'; this.style.color='var(--primary-orange)';" onmouseout="this.style.textDecoration='none'; this.style.color='var(--text-primary)';"><?php echo $student_id_val; ?></a></td>
                                            <td>
                                                <div class="student-col">
                                                    <div class="student-avatar" style="background: #333;"><?php echo $initials; ?></div>
                                                    <a href="student-history.php?student_id=<?php echo urlencode($req['student_id']); ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;" onmouseover="this.style.textDecoration='underline'; this.style.color='var(--primary-orange)';" onmouseout="this.style.textDecoration='none'; this.style.color='var(--text-primary)';"><?php echo $name_val; ?></a>
                                                </div>
                                            </td>
                                            <td style="font-weight: 700;"><?php echo $mid_val; ?></td>
                                            <td><?php echo $badge; ?></td>
                                            <td style="text-align: right; color: var(--text-secondary); font-size: 16px;">
                                                <?php if ($status_val === 'Pending'): ?>
                                                    <a href="?action=reject&id=<?php echo $req['request_id']; ?>" style="color: var(--color-red); text-decoration: none; margin-right: 15px;" title="Reject Request">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </a>
                                                    <a href="?action=approve&id=<?php echo $req['request_id']; ?>" style="color: var(--color-green); text-decoration: none;" title="Approve Request">
                                                        <i class="fa-solid fa-check-double"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 12px; color: var(--text-secondary);">Resolved</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="empty-filter-row" style="display: none;">
                                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                            No requests found matching this filter.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <script src="../script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            const accordions = document.querySelectorAll('.accordion-item');

            // Store original student count text for headers
            accordions.forEach(accordion => {
                const countSpan = accordion.querySelector('.accordion-stats div div');
                if (countSpan) {
                    accordion.dataset.originalCountText = countSpan.textContent;
                }
            });

            statCards.forEach(card => {
                card.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    const isAlreadyActive = this.classList.contains('active-filter');

                    // Deactivate all filters first
                    statCards.forEach(c => c.classList.remove('active-filter'));

                    let activeFilter = 'All';
                    if (!isAlreadyActive) {
                        this.classList.add('active-filter');
                        activeFilter = filter;
                    } else {
                        const allCard = document.querySelector('.stat-card[data-filter="All"]');
                        if (allCard) allCard.classList.add('active-filter');
                    }

                    // Loop through each course accordion to filter rows
                    accordions.forEach(accordion => {
                        const rows = accordion.querySelectorAll('.request-row');
                        const emptyRow = accordion.querySelector('.empty-filter-row');
                        const countSpan = accordion.querySelector('.accordion-stats div div');
                        let visibleCount = 0;

                        rows.forEach(row => {
                            const status = row.getAttribute('data-status');
                            if (activeFilter === 'All' || status === activeFilter) {
                                row.style.display = '';
                                visibleCount++;
                            } else {
                                row.style.display = 'none';
                            }
                        });

                        // Handle empty state for this table
                        if (rows.length > 0) {
                            if (visibleCount === 0) {
                                if (emptyRow) {
                                    emptyRow.style.display = '';
                                    emptyRow.querySelector('td').textContent = `No ${activeFilter.toLowerCase()} requests found for this course.`;
                                }
                            } else {
                                if (emptyRow) emptyRow.style.display = 'none';
                            }
                        }

                        // Update text counter in accordion header
                        if (countSpan) {
                            if (activeFilter !== 'All') {
                                countSpan.textContent = `${visibleCount} Student${visibleCount === 1 ? '' : 's'}`;
                            } else {
                                countSpan.textContent = accordion.dataset.originalCountText || `${rows.length} Students`;
                            }
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>