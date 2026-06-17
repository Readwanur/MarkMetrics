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

// Fetch student suggestions
$all_students_q = mysqli_query($conn, "SELECT s.student_id, u.name FROM students s JOIN users u ON s.student_id = u.id");
$student_suggestions = [];
if ($all_students_q) {
    while ($row = mysqli_fetch_assoc($all_students_q)) {
        $student_suggestions[] = $row;
    }
}

// Retrieve student_id from query parameter (default empty if not provided)
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$student_info = null;

if (!empty($student_id)) {
    // Fetch student user info
    $student_q = mysqli_query($conn, "
        SELECT u.name, s.cumulative_gpa, s.total_credits_earned
        FROM users u
        JOIN students s ON u.id = s.student_id
        WHERE u.id = '$student_id'
    ");
    $student_info = mysqli_fetch_assoc($student_q);
}

if ($student_info) {
    $student_name = $student_info['name'];
    $cgpa = number_format($student_info['cumulative_gpa'], 2);
    $credits_earned = floatval($student_info['total_credits_earned']);
    
    // Determine percentile tier based on GPA
    if ($cgpa >= 3.90) {
        $percentile_tag = 'Top 1%';
    } elseif ($cgpa >= 3.80) {
        $percentile_tag = 'Top 2%';
    } elseif ($cgpa >= 3.50) {
        $percentile_tag = 'Top 10%';
    } else {
        $percentile_tag = 'Good Standing';
    }

    // Get latest semester with enrollments for this student
    $sem_q = mysqli_query($conn, "
        SELECT DISTINCT s.semester_id, s.display_name
        FROM enrollments e
        JOIN semesters s ON e.semester_id = s.semester_id
        WHERE e.student_id = '$student_id'
        ORDER BY s.semester_id DESC
        LIMIT 1
    ");
    $sem_info = mysqli_fetch_assoc($sem_q);
    $latest_semester_id = $sem_info ? $sem_info['semester_id'] : 1;
    $latest_semester_name = $sem_info ? $sem_info['display_name'] : 'Spring Semester 2024';

    // Fetch courses enrolled in the latest semester
    $courses_q = mysqli_query($conn, "
        SELECT e.course_code, c.course_name, c.credits, e.grade, e.points
        FROM enrollments e
        JOIN courses c ON e.course_code = c.course_code
        WHERE e.student_id = '$student_id' AND e.semester_id = $latest_semester_id
    ");
    $sem_credits = 0;
    $course_rows = [];
    if ($courses_q) {
        while ($row = mysqli_fetch_assoc($courses_q)) {
            $course_rows[] = $row;
            $sem_credits += floatval($row['credits']);
        }
    }

    // Fetch semester-by-semester summaries
    $summaries_q = mysqli_query($conn, "
        SELECT s.display_name, s.semester_id, AVG(e.points) AS sem_gpa
        FROM enrollments e
        JOIN semesters s ON e.semester_id = s.semester_id
        WHERE e.student_id = '$student_id' AND e.grade IS NOT NULL AND e.grade != '--' AND e.grade != 'INC'
        GROUP BY s.semester_id, s.display_name
        ORDER BY s.semester_id DESC
    ");
} else {
    $student_name = '';
    $cgpa = '0.00';
    $credits_earned = '0';
    $percentile_tag = 'N/A';
    $latest_semester_name = 'N/A';
    $course_rows = [];
    $sem_credits = 0;
    $summaries_q = false;
}

// Helper for Grade Badges
function getGradeBadge($grade) {
    switch ($grade) {
        case 'A+':
            return '<span class="badge badge-orange">A+</span>';
        case 'A':
            return '<span class="badge" style="background: rgba(0, 210, 106, 0.1); border: 1px solid var(--color-green); color: var(--color-green);">A</span>';
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
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1.5px solid rgba(255, 255, 255, 0.04);
            gap: 20px;
        }
        .cool-search-bar-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1.5px solid var(--border-color);
            border-radius: 30px;
            padding: 10px 24px;
            width: 320px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2), 0 4px 20px rgba(0,0,0,0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cool-search-bar-wrapper:focus-within {
            border-color: var(--primary-orange) !important;
            background: rgba(255, 107, 0, 0.04) !important;
            box-shadow: 0 0 15px rgba(245, 130, 32, 0.15), inset 0 2px 4px rgba(0,0,0,0.2) !important;
            width: 380px !important;
        }
        .cool-search-bar-wrapper:focus-within .search-icon {
            transform: scale(1.15) rotate(10deg);
        }
        .clear-search-icon:hover {
            color: var(--primary-orange) !important;
        }
        .autocomplete-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: rgba(32, 31, 31, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 15px 40px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.05);
            padding: 8px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
            animation: fadeInDropdown 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes fadeInDropdown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .autocomplete-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-primary);
        }
        .autocomplete-item:hover, .autocomplete-item.active {
            background: rgba(255, 107, 0, 0.12);
            color: #fff;
            padding-left: 20px;
        }
        .autocomplete-item .student-name {
            font-weight: 600;
            font-size: 13.5px;
        }
        .autocomplete-item .student-id {
            font-size: 11.5px;
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 8px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }
        .autocomplete-item:hover .student-id, .autocomplete-item.active .student-id {
            color: var(--primary-orange);
            background: rgba(245, 130, 32, 0.1);
            border-color: rgba(245, 130, 32, 0.2);
        }
        .no-suggestions {
            padding: 12px 16px;
            color: var(--text-secondary);
            font-size: 13px;
            text-align: center;
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

            <li class="dropdown">
                <a href="#">
                    <i class="fa-solid fa-rotate"></i> Academic Actions
                    <?php if (isset($total_pending_actions) && $total_pending_actions > 0): ?>
                        <span class="menu-badge"><?php echo $total_pending_actions; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="submenu">
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

            <li class="dropdown active">
                <a href="#" class="active">
                    <i class="fa-solid fa-eye"></i> View
                </a>
                <ul class="submenu show">
                    <li>
                        <a href="academic-performance.php">Academic Performance</a>
                    </li>
                    <li class="active">
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

    <div class="main-content" style="position: relative;">
        <!-- Top Navbar containing Back button and Cool Search Bar -->
        <div class="top-navbar">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="javascript:history.back()" class="btn-back" style="margin-bottom: 0;"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <div style="height: 18px; width: 1.5px; background: rgba(255, 255, 255, 0.1);"></div>
                <span style="color: var(--text-secondary); font-size: 13px; font-weight: 500;">
                    View / <span style="color: var(--primary-orange);">Student History</span>
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Much Cool Custom Autocomplete Search Bar -->
                <div class="search-container" style="position: relative; width: 320px; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); margin-right: 70px;">
                    <div class="cool-search-bar-wrapper">
                        <i class="fa-solid fa-magnifying-glass search-icon" style="color: var(--primary-orange); font-size: 15px; transition: transform 0.3s;"></i>
                        <input type="text" id="studentSearchInput" placeholder="Search Student by Name or ID..." style="background: transparent; border: none; color: #fff; outline: none; font-size: 14px; width: 100%; font-weight: 500;" autocomplete="off">
                        <i class="fa-solid fa-circle-xmark clear-search-icon" id="clearSearchBtn" style="color: var(--text-secondary); font-size: 14px; cursor: pointer; display: none; transition: color 0.2s;"></i>
                    </div>
                    <!-- Custom Autocomplete Dropdown -->
                    <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
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
        </div>

        <?php if ($student_info): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 20px;">
            <div class="page-header" style="max-width: 60%; margin-bottom: 0;">
                <h1>Student History</h1>
                <p>Comprehensive academic trajectory for <span class="text-orange"><?php echo htmlspecialchars($student_name); ?></span> (ID: <?php echo htmlspecialchars($student_id); ?>).<br>Curating three years of excellence.</p>
            </div>

            <!-- CGPA Card Widget -->
            <div style="text-align: center;">
                <div class="cgpa-card" style="position: static; box-shadow: none; right: auto; top: auto; width: auto; display: inline-block; padding: 15px 25px;">
                    <h4 style="font-size: 11px; margin-bottom: 5px;">CGPA</h4>
                    <h1 style="font-size: 32px; margin-bottom: 2px;"><?php echo htmlspecialchars($cgpa); ?></h1>
                    <p style="font-size: 11px;"><?php echo htmlspecialchars($percentile_tag); ?></p>
                </div>
                <div>
                    <a href="academic-performance.php?student_id=<?php echo urlencode($student_id); ?>" class="cgpa-view-btn" style="margin-top: 5px; font-size: 13px;">View Performance</a>
                </div>
            </div>
        </div>

        <div class="history-layout">
            
            <!-- Left side: Alerts -->
            <div class="academic-alerts">
                <div class="alert-header">
                    <h3>ACADEMIC ALERTS</h3>
                    <span class="badge badge-red">2 CRITICAL</span>
                </div>
                
                <div class="alert-item blue">
                    <h4>Thesis Proposal Overdue</h4>
                    <p>Submission window closes in 14 hours.<br>Final extension applied.</p>
                </div>

                <div class="alert-item orange">
                    <h4>Scholarship Renewal</h4>
                    <p>Maintain > 3.80 CGPA to retain Merit Tier 1. Audit pending.</p>
                </div>
            </div>

            <!-- Right side: Semester Table & Summaries -->
            <div>
                <div class="table-container">
                    <div class="table-header-actions" style="border-bottom: none; padding-bottom: 0;">
                        <div>
                            <h3 style="font-size: 16px; margin-bottom: 4px;"><?php echo htmlspecialchars($latest_semester_name); ?></h3>
                            <p style="color: var(--primary-orange); font-size: 11px; font-weight: 600;">ENROLLMENT . <?php echo htmlspecialchars($sem_credits); ?> CREDITS</p>
                        </div>
                        <div class="table-actions-right">
                            <button class="btn-dark"><i class="fa-solid fa-sliders"></i></button>
                            <button class="btn-dark"><i class="fa-solid fa-download"></i></button>
                        </div>
                    </div>

                    <table style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>COURSE CODE</th>
                                <th>COURSE NAME</th>
                                <th>CREDITS</th>
                                <th>GRADE</th>
                                <th>POINTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($course_rows)) {
                                foreach ($course_rows as $row) {
                                    $grade_val = $row['grade'] ?? 'Running';
                                    $points_val = ($row['points'] !== null) ? number_format($row['points'], 2) : '--';
                                    ?>
                                    <tr>
                                        <td class="text-orange" style="font-weight: 600;"><?php echo htmlspecialchars($row['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['credits']); ?></td>
                                        <td><?php echo getGradeBadge($grade_val); ?></td>
                                        <td><?php echo htmlspecialchars($points_val); ?></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 20px;">No course enrollments found for this term.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="semester-summaries">
                    <?php
                    if ($summaries_q && mysqli_num_rows($summaries_q) > 0) {
                        while ($summary = mysqli_fetch_assoc($summaries_q)) {
                            $gpa_val = number_format($summary['sem_gpa'], 2);
                            $tag = ($gpa_val >= 3.80) ? "Dean's List" : "Merit Pass";
                            $class = ($gpa_val >= 3.80) ? "blue" : "orange";
                            ?>
                            <div class="semester-card">
                                <h4><?php echo htmlspecialchars(strtoupper($summary['display_name'])); ?></h4>
                                <h2><?php echo $gpa_val; ?></h2>
                                <p class="<?php echo $class; ?>"><?php echo $tag; ?></p>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p style="color: var(--text-secondary); width: 100%; text-align: center;">No semester history available.</p>';
                    }
                    ?>
                </div>
            </div>

        </div>
        <?php else: ?>
        <!-- Elegant Search Empty State -->
        <div class="search-empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 420px; text-align: center; border: 1.5px dashed var(--border-color); border-radius: 16px; background: rgba(255, 255, 255, 0.01); padding: 40px; margin-top: 20px;">
            <div style="background: rgba(255, 107, 0, 0.06); border-radius: 50%; padding: 24px; margin-bottom: 22px; border: 1px solid rgba(255, 107, 0, 0.12); display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-magnifying-glass" style="color: var(--primary-orange); font-size: 38px; animation: pulseGlow 2s infinite ease-in-out;"></i>
            </div>
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 12px; color: #fff; letter-spacing: -0.3px;">Search Student History</h2>
            <p style="color: var(--text-secondary); max-width: 440px; font-size: 14.5px; line-height: 1.6; margin-bottom: 0;">Use the search bar in the top-right header to look up a student's name or ID. Their academic history, compiled registry, and semester trajectory will be displayed here.</p>
            <style>
                @keyframes pulseGlow {
                    0% { transform: scale(1); opacity: 0.85; }
                    50% { transform: scale(1.08); opacity: 1; filter: drop-shadow(0 0 10px rgba(245, 130, 32, 0.35)); }
                    100% { transform: scale(1); opacity: 0.85; }
                }
            </style>
        </div>
        <?php endif; ?>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const studentSuggestions = <?php echo json_encode($student_suggestions); ?>;
            const searchInput = document.getElementById('studentSearchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            const dropdown = document.getElementById('autocompleteDropdown');
            const searchContainer = document.querySelector('.search-container');
            let activeIndex = -1;

            function renderSuggestions(query) {
                dropdown.innerHTML = '';
                const filtered = studentSuggestions.filter(s => {
                    const nameMatch = s.name.toLowerCase().includes(query.toLowerCase());
                    const idMatch = s.student_id.toLowerCase().includes(query.toLowerCase());
                    return nameMatch || idMatch;
                });

                if (filtered.length === 0) {
                    dropdown.innerHTML = '<div class="no-suggestions"><i class="fa-solid fa-circle-info" style="margin-right: 6px;"></i>No students found</div>';
                } else {
                    filtered.forEach((student, index) => {
                        const item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.dataset.id = student.student_id;
                        item.innerHTML = `
                            <span class="student-name">${escapeHTML(student.name)}</span>
                            <span class="student-id">${escapeHTML(student.student_id)}</span>
                        `;
                        item.addEventListener('click', function() {
                            selectStudent(student.student_id);
                        });
                        dropdown.appendChild(item);
                    });
                }
                dropdown.style.display = 'block';
                searchContainer.style.width = '380px';
                activeIndex = -1;
            }

            function selectStudent(id) {
                window.location.href = 'student-history.php?student_id=' + encodeURIComponent(id);
            }

            function escapeHTML(str) {
                return str.replace(/[&<>'"]/g, 
                    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
                );
            }

            searchInput.addEventListener('input', function() {
                const val = this.value.trim();
                if (val) {
                    clearBtn.style.display = 'block';
                    renderSuggestions(val);
                } else {
                    clearBtn.style.display = 'none';
                    dropdown.style.display = 'none';
                    searchContainer.style.width = '320px';
                }
            });

            searchInput.addEventListener('focus', function() {
                const val = this.value.trim();
                if (val) {
                    renderSuggestions(val);
                }
            });

            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                dropdown.style.display = 'none';
                searchContainer.style.width = '320px';
                searchInput.focus();
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target) && !clearBtn.contains(e.target)) {
                    dropdown.style.display = 'none';
                    searchContainer.style.width = '320px';
                }
            });

            // Keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = dropdown.querySelectorAll('.autocomplete-item');
                if (dropdown.style.display === 'block' && items.length > 0) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (activeIndex < items.length - 1) {
                            if (activeIndex >= 0) items[activeIndex].classList.remove('active');
                            activeIndex++;
                            items[activeIndex].classList.add('active');
                            items[activeIndex].scrollIntoView({ block: 'nearest' });
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (activeIndex > 0) {
                            items[activeIndex].classList.remove('active');
                            activeIndex--;
                            items[activeIndex].classList.add('active');
                            items[activeIndex].scrollIntoView({ block: 'nearest' });
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeIndex >= 0 && items[activeIndex]) {
                            items[activeIndex].click();
                        } else if (items.length > 0) {
                            items[0].click(); // Default select first
                        }
                    }
                }
            });
        });
    </script>
    <script src="../script.js"></script>
</body>
</html>
