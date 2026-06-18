<?php
session_start();
include("./connect2db.php");

// AJAX endpoint to check email duplication
if (isset($_GET['check_email'])) {
    header('Content-Type: application/json');
    $email = mysqli_real_escape_string($conn, $_GET['check_email']);
    $q = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if ($q && mysqli_num_rows($q) > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Redirect to login if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Ensure initials column exists in teachers table (may be missing in older schema)
@mysqli_query($conn, "ALTER TABLE teachers ADD COLUMN IF NOT EXISTS initials VARCHAR(20) NULL");
// Back-fill initials with teacher_id where not set
@mysqli_query($conn, "UPDATE teachers SET initials = teacher_id WHERE initials IS NULL OR initials = ''");

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
    exit();
}

// --- Handle AJAX User Search ---
if (isset($_GET['search_users'])) {
    header('Content-Type: application/json');
    $query = mysqli_real_escape_string($conn, $_GET['search_users']);
    $role_filter = mysqli_real_escape_string($conn, $_GET['role_filter'] ?? 'all');
    
    $where_clauses = [];
    if (!empty($query)) {
        $where_clauses[] = "(u.name LIKE '%$query%' OR u.id LIKE '%$query%' OR IFNULL(t.initials, t.teacher_id) LIKE '%$query%')";
    }
    
    if ($role_filter === 'student') {
        $where_clauses[] = "u.role = 'student'";
    } elseif ($role_filter === 'teacher') {
        $where_clauses[] = "u.role = 'teacher'";
    } else {
        $where_clauses[] = "u.role IN ('student', 'teacher')";
    }
    
    $where_str = implode(' AND ', $where_clauses);
    
    $sql = "SELECT u.id, u.name, u.role, u.status, u.email, d.name as dept_name, t.position, IFNULL(t.initials, t.teacher_id) as initials, s.academic_year
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.department_id
            LEFT JOIN teachers t ON u.id = t.teacher_id
            LEFT JOIN students s ON u.id = s.student_id
            WHERE $where_str
            ORDER BY u.name ASC
            LIMIT 15";
            
    $res = mysqli_query($conn, $sql);
    $results = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $results[] = $row;
        }
    }
    echo json_encode($results);
    exit();
}

// --- Handle Audit Logs for Notification Bell ---
if (isset($_GET['fetch_audit_logs'])) {
    header('Content-Type: application/json');
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

// --- Handle Account Provisioning (POST) ---
$provision_success = false;
$provision_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provision'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['prov_name']);
    $new_id = mysqli_real_escape_string($conn, $_POST['prov_id']);
    $new_email = mysqli_real_escape_string($conn, $_POST['prov_email']);
    $new_role = mysqli_real_escape_string($conn, $_POST['prov_role']);
    $new_year = mysqli_real_escape_string($conn, $_POST['prov_year']);
    $dept_id = mysqli_real_escape_string($conn, $_POST['prov_dept']);

    // Check if ID is empty
    if (empty($new_id)) {
        $provision_error = "Initial/ID cannot be empty. Please ensure full name is entered.";
    } else {
        // Auto-resolve collisions for ID/Initial (e.g. same teacher name or clashing hashes)
        $original_id = $new_id;
        $collision_counter = 1;
        while (true) {
            $check_sql = "SELECT id FROM users WHERE id = '$new_id'";
            $check_result = mysqli_query($conn, $check_sql);
            if (mysqli_num_rows($check_result) === 0) {
                break; // Unique ID/Initial found!
            }
            if ($new_role === 'student') {
                $new_id = strval(intval($original_id) + $collision_counter);
            } else {
                $new_id = $original_id . $collision_counter;
            }
            $collision_counter++;
        }
        
        if (false) { // Skip duplicate error since we auto-resolve collisions now
            $provision_error = "A user with this ID/Initial already exists.";
        } else {
        // Set default temporary password to 123
        $random_password = '123';
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

        // Insert into users table
        $insert_sql = "INSERT INTO users (id, name, email, password_hash, role, department_id, status) 
                       VALUES ('$new_id', '$new_name', '$new_email', '$hashed_password', '$new_role', " . ($dept_id ? "'$dept_id'" : 'NULL') . ", 'Pending')";

        if (mysqli_query($conn, $insert_sql)) {
            // If student, also insert into students table and create parent
            if ($new_role === 'student') {
                $parent_name = mysqli_real_escape_string($conn, $_POST['prov_parent_name']);
                $parent_email = mysqli_real_escape_string($conn, $_POST['prov_parent_email']);

                // Generate unique parent ID
                do {
                    $parent_id = 'PAR-' . str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
                    $check_parent = mysqli_query($conn, "SELECT id FROM users WHERE id = '$parent_id'");
                } while (mysqli_num_rows($check_parent) > 0);

                // Insert Parent into users
                $parent_password = '123';
                $hashed_parent_pwd = password_hash($parent_password, PASSWORD_DEFAULT);
                $insert_parent_sql = "INSERT INTO users (id, name, email, password_hash, role, status) VALUES ('$parent_id', '$parent_name', '$parent_email', '$hashed_parent_pwd', 'parent', 'Active')";
                mysqli_query($conn, $insert_parent_sql);

                // Find program_id by department
                $prog_result = mysqli_query($conn, "SELECT program_id FROM programs WHERE department_id = $dept_id LIMIT 1");
                $prog_row = mysqli_fetch_assoc($prog_result);
                $prog_id = $prog_row ? $prog_row['program_id'] : 'NULL';

                $student_sql = "INSERT INTO students (student_id, program_id, academic_year, parent_id) VALUES ('$new_id', $prog_id, '$new_year', '$parent_id')";
                mysqli_query($conn, $student_sql);

                // Send email to parent
                $parent_subject = "Welcome to MarkMetrics - Guardian Account";
                $parent_body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <p>Hello <b>$parent_name</b>,</p>
                    <p>A guardian account has been successfully created for you. Here are your temporary login details:</p>
                    <p><b>User ID:</b> $parent_id<br>
                    <b>Password:</b> $parent_password</p>
                    <p style='color: red;'><b>WARNING:</b> Do not share your password with anyone.</p>
                    <p>Please reset your password immediately using the 'forgot password' option on the login page.</p><br>
                    <p>Regards,<br><b style='color: #f58220;'>MarkMetrics Team</b></p>
                </body>
                </html>";

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'markmetrics.otp@gmail.com';
                    $mail->Password = 'kgok zcix dsym lwhj';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    $mail->setFrom('markmetrics.otp@gmail.com', 'MarkMetrics Support');
                    $mail->addAddress($parent_email, $parent_name);

                    $mail->isHTML(true);
                    $mail->Subject = $parent_subject;
                    $mail->Body = $parent_body;

                    $mail->send();
                } catch (Exception $e) {
                    // Fail silently or log error
                }
            }
            // If teacher, insert into teachers table (and populate initials)
            if ($new_role === 'teacher') {
                $position = mysqli_real_escape_string($conn, $_POST['prov_position']);
                $teacher_sql = "INSERT INTO teachers (teacher_id, position, initials) VALUES ('$new_id', '$position', '$new_id')";
                mysqli_query($conn, $teacher_sql);
            }

            // Send welcome email
            $to = $new_email;
            $subject = "Welcome to MarkMetrics - Your Account Details";
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <p>Hello <b>$new_name</b>,</p>
                <p>Your account has been successfully created. Here are your temporary login details:</p>
                <p><b>User ID:</b> $new_id<br>
                <b>Password:</b> $random_password</p>
                <p style='color: red;'><b>WARNING:</b> Do not share your password with anyone.</p>
                <p>Please reset your password immediately using the 'forgot password' option on the login page.</p><br>
                <p>Regards,<br><b style='color: #f58220;'>MarkMetrics Team</b></p>
            </body>
            </html>";

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'markmetrics.otp@gmail.com';
                $mail->Password = 'kgok zcix dsym lwhj';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('markmetrics.otp@gmail.com', 'MarkMetrics Support');
                $mail->addAddress($to, $new_name);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;

                $mail->send();
            } catch (Exception $e) {
                // Fail silently or log error
            }

            $provision_success = true;

            // Log audit entry
            $admin_id = mysqli_real_escape_string($conn, $_SESSION['id']);
            $audit_desc = "Provisioned new " . ucfirst($new_role) . " account: <strong>$new_name</strong> ($new_id)";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$admin_id', 'user_provisioned', '$audit_desc')");
        } else {
            $provision_error = mysqli_error($conn);
        }
    }
}
}

// --- Fetch User Management Data ---

// Fetch Departments
$dept_result = mysqli_query($conn, "SELECT department_id, name FROM departments ORDER BY name ASC");
$departments = [];
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
    }
}

// Fetch Semesters for Academic Year (semester-wise)
$sem_result = mysqli_query($conn, "SELECT display_name FROM semesters ORDER BY academic_year DESC, semester_id DESC");
$semesters = [];
if ($sem_result) {
    while ($row = mysqli_fetch_assoc($sem_result)) {
        $semesters[] = $row['display_name'];
    }
}

// Total Students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='student'");
$total_capacity = mysqli_fetch_assoc($result)['total'];

// Active Now (Active status users)
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status='Active'");
$active_now = mysqli_fetch_assoc($result)['total'];

// Faculty Load (teachers with assigned courses / total teachers)
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers");
$total_teachers = mysqli_fetch_assoc($result)['total'];

$result = mysqli_query($conn, "SELECT COUNT(DISTINCT teacher_id) as assigned FROM courses WHERE teacher_id IS NOT NULL");
$assigned_teachers = mysqli_fetch_assoc($result)['assigned'];

$faculty_load = $total_teachers > 0 ? round(($assigned_teachers / $total_teachers) * 100) : 0;

// Recent Registrations (last 4 users created)
$recent_query = "
    SELECT u.id, u.name, u.role, u.status, u.created_at,
           d.name as dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    ORDER BY u.created_at DESC
    LIMIT 4
";
$recent_result = mysqli_query($conn, $recent_query);
$recent_users = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_users[] = $row;
}

// Admin info from session
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin User';
$admin_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'admin@markmetrics.edu';

// Avatar color by role
function getAvatarColor($role)
{
    $colors = ['student' => 'green', 'teacher' => 'blue', 'admin' => 'orange', 'parent' => 'purple'];
    return $colors[$role] ?? 'blue';
}
// Fetch All Users (students + teachers) for Directory
$all_users_query = "
    SELECT u.id, u.name, u.role, u.status, u.email,
           d.name as dept_name, t.position, IFNULL(t.initials, t.teacher_id) as initials, s.academic_year
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN teachers t ON u.id = t.teacher_id
    LEFT JOIN students s ON u.id = s.student_id
    WHERE u.role IN ('student', 'teacher')
    ORDER BY u.role ASC, u.name ASC
";
$all_users_result = mysqli_query($conn, $all_users_query);
$all_users = [];
if ($all_users_result) {
    while ($row = mysqli_fetch_assoc($all_users_result)) {
        $all_users[] = $row;
    }
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
        content="MarkMetrics User Management – Provision accounts and manage user registrations across the GradeSync ecosystem.">
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
                <a href="../CourseManagement/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    <span class="nav-text">Course Management</span>
                </a>
                <a href="../UserManagement/index.php" class="nav-item active">
                    <div class="active-indicator"></div>
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
                            <span style="font-size:11px; background:rgba(243,112,33,0.12); color:var(--accent-orange); padding:2px 8px; border-radius:20px; font-weight:600;" id="notiCount">0 Pending</span>
                        </div>
                        <div id="notiContent" class="noti-empty">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content-wrapper">
                <?php if ($provision_success): ?>
                    <div
                        style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 12px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 14px; font-weight: 600;">
                        ✓ Account provisioned successfully with Initial/ID: <strong><?php echo htmlspecialchars($new_id); ?></strong>
                    </div>
                <?php elseif ($provision_error): ?>
                    <div
                        style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 12px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 14px; font-weight: 600;">
                        ✗ Error: <?php echo htmlspecialchars($provision_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Total Capacity</span>
                        <div class="stat-value-row">
                            <span class="stat-value"
                                id="totalCapacity"><?php echo number_format($total_capacity); ?></span>
                            <span class="stat-badge orange">Students</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Active Now</span>
                        <div class="stat-value-row">
                            <span class="stat-value" id="activeNow"><?php echo number_format($active_now); ?></span>
                            <span class="live-indicator">
                                <span class="live-dot"></span>
                                LIVE
                            </span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Faculty Load</span>
                        <div class="stat-value-row">
                            <span class="stat-value" id="facultyLoad"><?php echo $faculty_load; ?>%</span>
                        </div>
                        <span class="stat-sub green">OPTIMAL</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Server Load</span>
                        <div class="stat-value-row">
                            <span class="stat-value" id="serverLoad">12ms</span>
                        </div>
                        <span class="stat-sub blue">LATENCY</span>
                    </div>
                </div>

                <!-- Management Grid -->
                <div class="management-grid">
                    <!-- Account Provisioning -->
                    <div class="provisioning-card">
                        <div class="provisioning-header">
                            <div>
                                <h2>Account Provisioning</h2>
                                <p>Add new members to the GradeSync ecosystem</p>
                            </div>
                            <div class="provisioning-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </div>
                        </div>

                        <form id="provisionForm" method="POST" action="index.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nameInput">Name</label>
                                    <input type="text" id="nameInput" name="prov_name" placeholder="Enter full name"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label for="idInput" id="idInputLabel">Initial/ID</label>
                                    <input type="text" id="idInput" name="prov_id" placeholder="Will generate automatically" readonly required style="background-color: rgba(255,255,255,0.05); cursor: not-allowed;">
                                </div>
                            </div>

                            <div class="form-row full">
                                <div class="form-group" style="position: relative;">
                                    <label for="emailInput">Email Address</label>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <input type="email" id="emailInput" name="prov_email"
                                            placeholder="user2330784@bscse.uiu.ac.bd" style="padding-right: 40px; width: 100%;">
                                        <span id="emailStatus" style="position: absolute; right: 12px; font-size: 16px; font-weight: bold; pointer-events: none; transition: all 0.2s;"></span>
                                    </div>
                                    <span id="emailFeedback" style="font-size: 11px; margin-top: 4px; display: none; font-weight: 500;"></span>
                                </div>
                            </div>

                            <div class="form-row" id="parentFields" style="display: none;">
                                <div class="form-group">
                                    <label for="parentNameInput">Parent's Name</label>
                                    <input type="text" id="parentNameInput" name="prov_parent_name"
                                        placeholder="Enter parent's name">
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label for="parentEmailInput">Parent's Email</label>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <input type="email" id="parentEmailInput" name="prov_parent_email"
                                            placeholder="parent@email.com" style="padding-right: 40px; width: 100%;">
                                        <span id="parentEmailStatus" style="position: absolute; right: 12px; font-size: 16px; font-weight: bold; pointer-events: none; transition: all 0.2s;"></span>
                                    </div>
                                    <span id="parentEmailFeedback" style="font-size: 11px; margin-top: 4px; display: none; font-weight: 500;"></span>
                                </div>
                            </div>

                            <div class="form-row full" id="teacherFields" style="display: none;">
                                <div class="form-group">
                                    <label for="positionInput">Position</label>
                                    <select id="positionInput" name="prov_position">
                                        <option value="Instructor">Instructor</option>
                                        <option value="Lecturer">Lecturer</option>
                                        <option value="Assistant Professor">Assistant Professor</option>
                                        <option value="Associate Professor">Associate Professor</option>
                                        <option value="Professor">Professor</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row triple">
                                <div class="form-group">
                                    <label for="roleSelect">Role</label>
                                    <select id="roleSelect" name="prov_role" required>
                                        <option value="" disabled selected>Select Role</option>
                                        <option value="student">Student</option>
                                        <option value="teacher">Teacher</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="yearSelect">Academic Year</label>
                                    <select id="yearSelect" name="prov_year">
                                        <?php foreach ($semesters as $sem): ?>
                                            <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="deptSelect">Department</label>
                                    <select id="deptSelect" name="prov_dept" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                                                <?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" name="provision" class="provision-btn" id="provisionBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="16"></line>
                                    <line x1="8" y1="12" x2="16" y2="12"></line>
                                </svg>
                                Provision Account
                            </button>
                        </form>
                    </div>

                    <!-- Recent Registrations -->
                    <div class="registrations-card">
                        <div class="registrations-header">
                            <h3>Recent Registrations</h3>
                            <a href="#" class="view-all-link">VIEW ALL</a>
                        </div>

                        <div class="registrations-list">
                            <?php foreach ($recent_users as $user):
                                $initial = strtoupper(substr($user['name'], 0, 1));
                                $time_diff = time() - strtotime($user['created_at']);
                                if ($time_diff < 3600) {
                                    $time_label = floor($time_diff / 60) . ' mins ago';
                                } elseif ($time_diff < 86400) {
                                    $time_label = floor($time_diff / 3600) . ' hours ago';
                                } else {
                                    $time_label = floor($time_diff / 86400) . ' days ago';
                                }
                                ?>
                                <div class="registration-item">
                                    <div class="registration-left">
                                        <div class="reg-avatar <?php echo getAvatarColor($user['role']); ?>">
                                            <?php echo $initial; ?></div>
                                        <div>
                                            <div class="reg-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="reg-role"><?php echo ucfirst($user['role']); ?> •
                                                <?php echo htmlspecialchars($user['dept_name'] ?? 'General'); ?></div>
                                        </div>
                                    </div>
                                    <div class="registration-right">
                                        <div class="reg-time"><?php echo $time_label; ?></div>
                                        <div
                                            class="reg-status <?php echo strtolower($user['status']) === 'active' ? 'active' : 'pending'; ?>">
                                            <?php echo htmlspecialchars($user['status']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ===== User Directory Section ===== -->
                <div class="user-directory-section">
                    <div class="user-directory-header">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="catalog-title-bar-dir"></div>
                            <div>
                                <h2 style="font-size:1.2rem; font-weight:700; color:var(--text-primary);">User Directory</h2>
                                <p style="font-size:13px; color:var(--text-muted); margin-top:3px;">
                                    All registered students &amp; teachers — <span id="dirCount"><?php echo count($all_users); ?></span> members
                                </p>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:12px; color:var(--text-muted); background:rgba(255,255,255,0.04); border:1px solid var(--border-color); padding:6px 14px; border-radius:20px; font-weight:500;">
                                <span id="dirFilterLabel">Showing All</span>
                            </span>
                        </div>
                    </div>

                    <div class="user-dir-table-wrap">
                        <table class="user-dir-table" id="userDirTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>ID / Initial</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="userDirBody">
                                <?php foreach ($all_users as $u):
                                    $initial = strtoupper(substr($u['name'], 0, 1));
                                    $roleColor = $u['role'] === 'teacher' ? '#60a5fa' : '#22c55e';
                                    $statusColor = $u['status'] === 'Active' ? '#22c55e' : ($u['status'] === 'Inactive' ? '#ef4444' : '#f37021');
                                    $statusBg = $u['status'] === 'Active' ? 'rgba(34,197,94,0.12)' : ($u['status'] === 'Inactive' ? 'rgba(239,68,68,0.12)' : 'rgba(243,112,33,0.12)');
                                    $displayId = $u['role'] === 'teacher' ? ($u['initials'] ?? $u['id']) : $u['id'];
                                    $userJson = htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="user-dir-row" data-user="<?php echo $userJson; ?>" data-name="<?php echo strtolower($u['name']); ?>" data-id="<?php echo strtolower($u['id']); ?>" data-initials="<?php echo strtolower($u['initials'] ?? ''); ?>" data-role="<?php echo $u['role']; ?>">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <div class="dir-avatar" style="background:<?php echo $u['role'] === 'teacher' ? '#1e40af' : '#14532d'; ?>; color:<?php echo $roleColor; ?>;">
                                                <?php echo $initial; ?>
                                            </div>
                                            <span style="font-weight:600; color:var(--text-primary); font-size:14px;"><?php echo htmlspecialchars($u['name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="font-family:monospace; color:var(--text-secondary); font-size:13px;"><?php echo htmlspecialchars($displayId); ?></td>
                                    <td>
                                        <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;
                                            color:<?php echo $roleColor; ?>;
                                            background:<?php echo $u['role'] === 'teacher' ? 'rgba(96,165,250,0.12)' : 'rgba(34,197,94,0.12)'; ?>;
                                            border:1px solid <?php echo $u['role'] === 'teacher' ? 'rgba(96,165,250,0.25)' : 'rgba(34,197,94,0.25)'; ?>;">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td style="color:var(--text-secondary); font-size:13px;"><?php echo htmlspecialchars($u['dept_name'] ?? '—'); ?></td>
                                    <td style="color:var(--text-muted); font-size:13px;"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700;
                                            color:<?php echo $statusColor; ?>;
                                            background:<?php echo $statusBg; ?>;">
                                            <?php echo htmlspecialchars($u['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="view-user-btn" title="View Details">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_users)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No users found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="dirNoResults" style="display:none; text-align:center; padding:50px 0; color:var(--text-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;margin-bottom:12px;opacity:0.4;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <p style="font-size:15px; font-weight:600;">No matching users found</p>
                        <p style="font-size:13px; margin-top:4px;">Try a different name, ID, or initial</p>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" id="closeDetailModalBtn">&times;</button>
            <div class="modal-header">
                <h2 id="detailUserName">User Name</h2>
                <span id="detailUserRole" class="modal-badge">Role</span>
            </div>
            <div class="modal-body" id="detailModalBody">
                <!-- Injected via Javascript -->
            </div>
            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <button id="suspendUserBtn" class="provision-btn" style="background-color: #ef4444; margin: 0; padding: 10px 20px; font-size: 14px; width: auto;">Suspend User</button>
                <button id="unsuspendUserBtn" class="provision-btn" style="background-color: #22c55e; margin: 0; padding: 10px 20px; font-size: 14px; width: auto; display: none;">Unsuspend User</button>
            </div>
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
        fetch('index.php?fetch_audit_logs=1')
            .then(res => res.json())
            .then(logs => {
                if (!logs || logs.length === 0) {
                    notiContent.innerHTML = '<div class="noti-empty">&#10003; No recent activity</div>';
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