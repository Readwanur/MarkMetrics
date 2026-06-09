<?php
session_start();
include("./connect2db.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Redirect to login if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
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

    // Check if user ID already exists
    $check_sql = "SELECT id FROM users WHERE id = '$new_id'";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        $provision_error = "A user with this ID already exists.";
    } else {
        // Generate 8-digit random password
        $random_password = sprintf('%08d', mt_rand(0, 99999999));
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
                $parent_password = sprintf('%08d', mt_rand(0, 99999999));
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
            // If teacher, insert into teachers table
            if ($new_role === 'teacher') {
                $position = mysqli_real_escape_string($conn, $_POST['prov_position']);
                $teacher_sql = "INSERT INTO teachers (teacher_id, position) VALUES ('$new_id', '$position')";
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

// --- Fetch User Management Data ---

// Fetch Departments
$dept_result = mysqli_query($conn, "SELECT department_id, name FROM departments ORDER BY name ASC");
$departments = [];
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
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
                <div></div>

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
                <?php if ($provision_success): ?>
                    <div
                        style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 12px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 14px; font-weight: 600;">
                        ✓ Account provisioned successfully!
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
                                    <label for="idInput">ID</label>
                                    <input type="text" id="idInput" name="prov_id" placeholder="Enter user ID" required>
                                </div>
                            </div>

                            <div class="form-row full">
                                <div class="form-group">
                                    <label for="emailInput">Email Address</label>
                                    <input type="email" id="emailInput" name="prov_email"
                                        placeholder="user2330784@bscse.uiu.ac.bd">
                                </div>
                            </div>

                            <div class="form-row" id="parentFields" style="display: none;">
                                <div class="form-group">
                                    <label for="parentNameInput">Parent's Name</label>
                                    <input type="text" id="parentNameInput" name="prov_parent_name"
                                        placeholder="Enter parent's name">
                                </div>
                                <div class="form-group">
                                    <label for="parentEmailInput">Parent's Email</label>
                                    <input type="email" id="parentEmailInput" name="prov_parent_email"
                                        placeholder="parent@email.com">
                                </div>
                            </div>

                            <div class="form-row full" id="teacherFields" style="display: none;">
                                <div class="form-group">
                                    <label for="positionInput">Position</label>
                                    <input type="text" id="positionInput" name="prov_position"
                                        placeholder="e.g. Professor, Lecturer">
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
                                        <option value="2023-2024">2023-2024</option>
                                        <option value="2024-2025">2024-2025</option>
                                        <option value="2025-2026">2025-2026</option>
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
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>

</html>