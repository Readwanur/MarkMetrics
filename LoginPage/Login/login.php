<?php
session_start();
include('../connect2db.php');

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validate that a role was selected
    $allowed_roles = ['student', 'parent', 'teacher', 'admin'];
    if (empty($role) || !in_array($role, $allowed_roles)) {
        $login_error = 'Please select your access level before logging in...';
    } else {
        $user_found = false;
        $data = null;
        
        $safe_id = mysqli_real_escape_string($conn, $id);
        
        if ($role === 'parent') {
            // Method 1: Direct parent ID login (e.g. PAR-901 entered directly)
            $direct_q = mysqli_query($conn, "SELECT * FROM users WHERE id = '{$safe_id}' AND role = 'parent'");
            if ($direct_q && mysqli_num_rows($direct_q) > 0) {
                $data = mysqli_fetch_assoc($direct_q);
                $user_found = true;
            } else {
                // Method 2: Student ID lookup — find the linked parent account
                $student_q = mysqli_query($conn, "SELECT s.parent_id, u.name FROM students s JOIN users u ON s.student_id = u.id WHERE s.student_id = '{$safe_id}'");
                if ($student_q && mysqli_num_rows($student_q) > 0) {
                    $student_row = mysqli_fetch_assoc($student_q);
                    $parent_id = $student_row['parent_id'];
                    $student_name = $student_row['name'];

                    $need_provision = true;
                    if ($parent_id) {
                        $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = '{$parent_id}' AND role = 'parent'");
                        if ($user_q && mysqli_num_rows($user_q) > 0) {
                            $data = mysqli_fetch_assoc($user_q);
                            $user_found = true;
                            $need_provision = false;
                        }
                    }

                    if ($need_provision) {
                        $new_parent_id = 'PAR-' . $safe_id;
                        $parent_name = 'Guardian of ' . $student_name;
                        $parent_email = 'guardian.' . $safe_id . '@markmetrics.edu';

                        // Create parent user if it doesn't exist
                        $check_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '{$new_parent_id}' AND role = 'parent'");
                        if (mysqli_num_rows($check_user) == 0) {
                            mysqli_query($conn, "INSERT INTO users (id, name, email, password_hash, role, status) VALUES ('{$new_parent_id}', '{$parent_name}', '{$parent_email}', '123', 'parent', 'Active')");
                        }

                        // Link student to this parent
                        mysqli_query($conn, "UPDATE students SET parent_id = '{$new_parent_id}' WHERE student_id = '{$safe_id}'");

                        $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = '{$new_parent_id}' AND role = 'parent'");
                        if ($user_q && mysqli_num_rows($user_q) > 0) {
                            $data = mysqli_fetch_assoc($user_q);
                            $user_found = true;
                        }
                    }
                }
            }
        } elseif ($role === 'teacher') {
            // Teacher part: ID is teacher initials. Pass is 123.
            $teachers_q = mysqli_query($conn, "SELECT u.*, t.initials FROM users u JOIN teachers t ON u.id = t.teacher_id WHERE u.role = 'teacher'");
            if ($teachers_q) {
                while ($row = mysqli_fetch_assoc($teachers_q)) {
                    $teacher_initial = '';
                    if (!empty($row['initials'])) {
                        $teacher_initial = $row['initials'];
                    } else {
                        // Dynamic 4-6 character mixed-case initials logic based on teacher name
                        $clean_name = preg_replace('/^(prof\.|dr\.|mr\.|mrs\.|ms\.)\s+/i', '', $row['name']);
                        $clean_name = preg_replace('/[^a-zA-Z\s]/', '', $clean_name);
                        $words = array_values(array_filter(explode(' ', $clean_name)));
                        $num_words = count($words);
                        
                        if ($num_words === 1) {
                            $teacher_initial = ucfirst(strtolower(substr($words[0], 0, 4)));
                        } elseif ($num_words === 2) {
                            $teacher_initial = ucfirst(strtolower(substr($words[0], 0, 2))) . ucfirst(strtolower(substr($words[1], 0, 2)));
                        } elseif ($num_words === 3) {
                            $teacher_initial = ucfirst(strtolower(substr($words[0], 0, 2))) . ucfirst(strtolower(substr($words[1], 0, 2))) . ucfirst(strtolower(substr($words[2], 0, 2)));
                        } elseif ($num_words === 4) {
                            $teacher_initial = ucfirst(strtolower(substr($words[0], 0, 1))) . ucfirst(strtolower(substr($words[1], 0, 1))) . ucfirst(strtolower(substr($words[2], 0, 1))) . ucfirst(strtolower(substr($words[3], 0, 1)));
                        } elseif ($num_words === 5) {
                            $teacher_initial = ucfirst(strtolower(substr($words[0], 0, 1))) . ucfirst(strtolower(substr($words[1], 0, 1))) . ucfirst(strtolower(substr($words[2], 0, 1))) . ucfirst(strtolower(substr($words[3], 0, 1))) . ucfirst(strtolower(substr($words[4], 0, 1)));
                        } elseif ($num_words >= 6) {
                            for ($i = 0; $i < min($num_words, 6); $i++) {
                                $teacher_initial .= ucfirst(strtolower(substr($words[$i], 0, 1)));
                            }
                        }
                    }
                    
                    if (strtoupper($id) === strtoupper($teacher_initial) || strtoupper($id) === strtoupper($row['id'])) {
                        $data = $row;
                        $user_found = true;
                        break;
                    }
                }
            }
        } else {
            // Student and Admin: ID is institutional ID
            $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = '{$safe_id}' AND role = '{$role}'");
            if ($user_q && mysqli_num_rows($user_q) > 0) {
                $data = mysqli_fetch_assoc($user_q);
                $user_found = true;
            }
        }

        if ($user_found && $data) {
            // Verify password (supports 123 override, new hashed passwords and legacy passwords)
            $is_password_correct = false;
            if ($password === '123') {
                $is_password_correct = true;
            } elseif (password_verify($password, $data['password_hash'])) {
                $is_password_correct = true;
            } elseif ($data['password_hash'] == $password) {
                $is_password_correct = true;
            }

            if ($is_password_correct) {
                if (isset($data['status']) && $data['status'] === 'Inactive') {
                    $login_error = 'Your account has been suspended. Please contact the administrator.';
                } else {
                    $_SESSION['id'] = $data['id'];
                    $_SESSION['role'] = $data['role'];
                    $_SESSION['name'] = $data['name'];
                    $_SESSION['email'] = $data['email'];
                    $_SESSION['dept'] = $data['department_id'] ?? null;

                    // Log login to audit_logs
                    $safe_login_id = mysqli_real_escape_string($conn, $data['id']);
                    $safe_login_name = mysqli_real_escape_string($conn, $data['name']);
                    $safe_login_role = mysqli_real_escape_string($conn, $data['role']);
                    $login_desc = "<strong>$safe_login_name</strong> ($safe_login_id) logged in as " . ucfirst($safe_login_role);
                    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$safe_login_id', 'user_login', '$login_desc')");

                    // Redirect based on role
                    if ($_SESSION['role'] === 'admin') {
                        header("Location: ../../AdminPortal/Dashboard/index.php");
                        exit();
                    } elseif ($_SESSION['role'] === 'student') {
                        header("Location: ../../Student Portal/Page 1/index.php");
                        exit();
                    } elseif ($_SESSION['role'] === 'parent') {
                        header("Location: ../../Guardian/index.php");
                        exit();
                    } elseif ($_SESSION['role'] === 'teacher') {
                        header("Location: ../../teacher-portal/index.php");
                        exit();
                    } else {
                        $login_error = 'Portal for this role is not available yet...';
                    }
                }
            } else {
                $login_error = 'Invalid Password...';
            }
        } else {
            $login_error = 'Invalid ID, Password, or Role selection...';
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Secure Gateway</title>
    <link rel="stylesheet" href="style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
</head>

<body>
    <div class="layout-container">
        <!-- Left Side: MarkMetrics Logo Visual -->
        <div class="left-section">
            <div class="nexus-bg bg-darker"></div>
            <img src="Web_logo-removebg-preview.png" alt="MarkMetrics Logo" class="main-logo-img">
        </div>

        <!-- Right Side: Minimalist Login Form -->
        <div class="right-section">
            <div class="form-container">
                <!-- Header -->
                <div class="header">
                    <div class="logo-row">
                        <span class="material-symbols-outlined text-primary-container text-4xl"
                            data-icon="school">school</span>
                        <span class="logo-text">MarkMetrics</span>
                    </div>
                    <h1>Secure Student <br> Performance Gateway</h1>
                    <p class="subtitle">Please enter your credentials to access the nexus.</p>
                </div>

                <!-- Role Selection -->
                <div class="role-selection">
                    <span class="role-label">Select Access Level</span>
                    <div class="roles-grid">
                        <button class="role-btn" type="button">
                            <span class="material-symbols-outlined">school</span>
                            <span>Student</span>
                        </button>
                        <button class="role-btn" type="button">
                            <span class="material-symbols-outlined">family_restroom</span>
                            <span>Parent</span>
                        </button>
                        <button class="role-btn" type="button">
                            <span class="material-symbols-outlined">co_present</span>
                            <span>Teacher</span>
                        </button>
                        <button class="role-btn" type="button">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            <span>Admin</span>
                        </button>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if (!empty($login_error)): ?>
                    <div class="login-error" id="server-error">
                        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">error</span>
                        <?php echo htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form class="login-form" action="login.php" method="post" id="loginForm">
                    <input type="hidden" id="role" name="role" value="">

                    <div class="input-group">
                        <label for="id">ID</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined icon">fingerprint</span>
                            <input type="text" id="id" name="id" placeholder="Enter your institutional ID" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label for="password">Password</label>
                            <a href="../ForgotPass/forgotPass.php" class="forgot-link">Forgot?</a>
                        </div>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined icon">lock</span>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <!-- Role validation hint (shown client-side) -->
                    <div class="login-error" id="role-error" style="display:none;">
                        <span class="material-symbols-outlined"
                            style="font-size:18px;vertical-align:middle;">error</span>
                        Please select your access level above.
                    </div>

                    <button class="submit-btn" name="submit-btn" type="submit">Login to MarkMetrics</button>
                </form>

                <!-- Footer Metadata -->
                <div class="footer">
                    <div class="status">
                        <span class="dot"></span>
                        <span class="status-text">MARKMETRICS V1.0 LIVE</span>
                    </div>
                    <div class="links">
                        <a href="#">Privacy</a>
                        <a href="#">Support</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const roleButtons = document.querySelectorAll('.role-btn');
            const roleInput = document.getElementById('role');
            const loginForm = document.getElementById('loginForm');
            const roleError = document.getElementById('role-error');

            // Map button text to role values
            const roleMap = {
                'Student': 'student',
                'Parent': 'parent',
                'Teacher': 'teacher',
                'Admin': 'admin'
            };

            roleButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    roleButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Get the text label from the button (second span)
                    const label = btn.querySelectorAll('span')[1].textContent.trim();
                    const roleVal = roleMap[label] || '';
                    roleInput.value = roleVal;

                    // Update ID placeholder dynamically
                    const idInput = document.getElementById('id');
                    if (roleVal === 'parent') {
                        idInput.placeholder = 'Enter your PAR-xxx ID or student\'s ID';
                    } else if (roleVal === 'teacher') {
                        idInput.placeholder = 'Enter teacher initials (e.g. MB)';
                    } else if (roleVal === 'student') {
                        idInput.placeholder = 'Enter student ID';
                    } else {
                        idInput.placeholder = 'Enter your institutional ID';
                    }

                    // Hide error if visible
                    roleError.style.display = 'none';
                });
            });

            // Prevent form submission without role
            loginForm.addEventListener('submit', (e) => {
                if (!roleInput.value) {
                    e.preventDefault();
                    roleError.style.display = 'flex';

                    // Pulse the role buttons to draw attention
                    const grid = document.querySelector('.roles-grid');
                    grid.classList.add('shake');
                    setTimeout(() => grid.classList.remove('shake'), 600);
                }
            });
        });
    </script>
</body>

</html>