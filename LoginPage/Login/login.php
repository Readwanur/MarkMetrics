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
        // Check id AND role
        $sql = "SELECT * FROM users WHERE id = '{$id}' AND role = '{$role}';";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);

            // Verify password (supports both new hashed passwords and old legacy passwords)
            if (password_verify($password, $data['password_hash']) || $data['password_hash'] == $password) {
                $_SESSION['id'] = $data['id'];
                $_SESSION['role'] = $data['role'];
                $_SESSION['name'] = $data['name'];
                $_SESSION['email'] = $data['email'];
                $_SESSION['dept'] = $data['department_id'];

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
                    roleInput.value = roleMap[label] || '';

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