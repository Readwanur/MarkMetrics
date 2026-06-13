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
$success_msg = '';
$error_msg = '';

// Handle POST profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $initials = mysqli_real_escape_string($conn, trim($_POST['initials']));
    $position = mysqli_real_escape_string($conn, trim($_POST['position']));
    $edu = mysqli_real_escape_string($conn, trim($_POST['education_background']));

    $update_success = true;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_success = false;
        $error_msg = 'Please enter a valid email address.';
    }

    if ($update_success) {
        // Begin transaction
        mysqli_begin_transaction($conn);

        // Update users table
        $user_sql = "UPDATE users SET name = '$name', email = '$email' WHERE id = '$teacher_id'";
        $user_up = mysqli_query($conn, $user_sql);

        // Update teachers table
        $teacher_sql = "UPDATE teachers SET initials = '$initials', position = '$position', education_background = '$edu' WHERE teacher_id = '$teacher_id'";
        $teacher_up = mysqli_query($conn, $teacher_sql);

        if ($user_up && $teacher_up) {
            // Handle profile picture upload if selected
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $file_tmp = $_FILES['profile_pic']['tmp_name'];
                $file_name = $_FILES['profile_pic']['name'];
                $file_size = $_FILES['profile_pic']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_ext, $allowed_exts)) {
                    $update_success = false;
                    $error_msg = 'Invalid file type. Allowed extensions: ' . implode(', ', $allowed_exts);
                } elseif ($file_size > 2 * 1024 * 1024) { // 2MB Limit
                    $update_success = false;
                    $error_msg = 'File size must be under 2MB.';
                } else {
                    $target_dir = "../../Images/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $new_file_name = "teacher_" . $teacher_id . "_" . time() . "." . $file_ext;
                    $target_path = $target_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $target_path)) {
                        $db_path = "Images/" . $new_file_name;
                        mysqli_query($conn, "UPDATE users SET profile_picture_url = '$db_path' WHERE id = '$teacher_id'");
                    } else {
                        $update_success = false;
                        $error_msg = 'Failed to save uploaded profile picture.';
                    }
                }
            }
        } else {
            $update_success = false;
            $error_msg = 'Database error: ' . mysqli_error($conn);
        }

        if ($update_success) {
            mysqli_commit($conn);
            $success_msg = 'Profile updated successfully!';
            
            // Sync Session Name & Email
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;

            // Log action to audit_logs
            $log_desc = "<strong>$name</strong> ($teacher_id) updated their profile details";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, description) VALUES ('$teacher_id', 'Profile Updated', '$log_desc')");
        } else {
            mysqli_rollback($conn);
        }
    }
}

// Fetch teacher details dynamically
$user_q = mysqli_query($conn, "SELECT u.name, u.email, u.profile_picture_url, t.initials, t.education_background, t.position 
                               FROM users u 
                               JOIN teachers t ON u.id = t.teacher_id 
                               WHERE u.id = '$teacher_id'");
$teacher_data = mysqli_fetch_assoc($user_q);

$teacher_name = $teacher_data['name'] ?? $_SESSION['name'];
$teacher_email = $teacher_data['email'] ?? $_SESSION['email'];
$teacher_initials = $teacher_data['initials'] ?? '';
$teacher_position = $teacher_data['position'] ?? '';
$teacher_edu = $teacher_data['education_background'] ?? '';
$avatar_db = $teacher_data['profile_picture_url'] ?? '';
$avatar_path = empty($avatar_db) ? '../asset/avatar2.jpg' : '../../' . $avatar_db;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Teacher Profile</title>
    <link rel="stylesheet" href="../style.css?v=1.8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
        }
        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            height: fit-content;
        }
        .profile-avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 20px auto;
        }
        .profile-avatar-container img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-orange);
        }
        .avatar-edit-label {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary-orange);
            color: #fff;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--bg-card);
            transition: background 0.2s;
        }
        .avatar-edit-label:hover {
            background: var(--primary-orange-hover);
        }
        .profile-form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-group label {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input, .form-group textarea {
            background: #11111a;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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
                </a>
               <ul class="submenu">
                    <li>
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
            <div>
                <h1>Teacher Profile</h1>
                <p>Manage your account settings, display details, educational background, and credentials.</p>
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

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="profile-container">
                <!-- Left side: Picture Card -->
                <div class="profile-card">
                    <div class="profile-avatar-container">
                        <img src="<?php echo htmlspecialchars($avatar_path); ?>" id="avatarPreview" alt="Avatar">
                        <label for="profilePicInput" class="avatar-edit-label">
                            <i class="fa-solid fa-camera"></i>
                        </label>
                        <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    </div>

                    <h2 style="margin-bottom: 6px; font-weight: 700;"><?php echo htmlspecialchars($teacher_name); ?></h2>
                    <p style="color: var(--primary-orange); font-size: 13px; font-weight: 600; margin-bottom: 25px; text-transform: uppercase;">
                        <?php echo htmlspecialchars($teacher_position ?: 'Faculty Member'); ?>
                    </p>
                    
                    <div style="border-top: 1px solid var(--border-color); padding-top: 20px; text-align: left; display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                        <div>
                            <span style="color: var(--text-secondary); font-weight: 600;">Teacher ID:</span>
                            <span style="float: right; color: #fff;"><?php echo htmlspecialchars($teacher_id); ?></span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary); font-weight: 600;">Initials:</span>
                            <span style="float: right; color: #fff; font-weight: bold;"><?php echo htmlspecialchars($teacher_initials ?: '--'); ?></span>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary); font-weight: 600;">Status:</span>
                            <span style="float: right; color: var(--color-green); font-weight: bold;">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Right side: Edit Forms -->
                <div class="profile-form-card">
                    <h3 style="margin-bottom: 25px; font-size: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Edit Profile Information</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nameInput">Full Name</label>
                            <input type="text" name="name" id="nameInput" value="<?php echo htmlspecialchars($teacher_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="initialsInput">Teacher Initial</label>
                            <input type="text" name="initials" id="initialsInput" value="<?php echo htmlspecialchars($teacher_initials); ?>" maxlength="10" placeholder="e.g. MB">
                        </div>
                        <div class="form-group">
                            <label for="emailInput">Email Address</label>
                            <input type="email" name="email" id="emailInput" value="<?php echo htmlspecialchars($teacher_email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="positionInput">Position / Academic Rank</label>
                            <input type="text" name="position" id="positionInput" value="<?php echo htmlspecialchars($teacher_position); ?>" placeholder="e.g. Professor">
                        </div>
                        <div class="form-group full-width">
                            <label for="eduInput">Educational Background</label>
                            <textarea name="education_background" id="eduInput" placeholder="Describe your educational history (e.g., BSc, MSc, PhD details)..."><?php echo htmlspecialchars($teacher_edu); ?></textarea>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" name="update_profile" class="btn-orange" style="padding: 12px 30px;"><i class="fa-solid fa-save"></i> Save Profile Details</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    <script src="../script.js"></script>
</body>
</html>
