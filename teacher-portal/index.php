<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../LoginPage/Login/login.php");
    exit();
}
$teacher_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
$teacher_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Teacher Portal</title>
    <link rel="stylesheet" href="style.css">
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
                </a>
                <ul class="submenu">
                    <li>
                        <a href="./pages/withdraw-request.php">Withdraw Requests</a>
                    </li>
                    <li>
                        <a href="./pages/grade-management.php">Grade Management</a>
                    </li>
                    <li>
                        <a href="./pages/academic-performance.php">Academic Performance</a>
                    </li>
                    <li>
                        <a href="./pages/marks-entry.php">Marks Entry</a>
                    </li>
                    <li>
                        <a href="./pages/student-history.php">Student History</a>
                    </li>
                </ul>
            </li>
        </ul>

        <div class="logout-btn-container">
            <a href="../teacher-portal/logout.php" class="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Log Out</span>
            </a>
        </div>

        <div class="profile-box">
            <img src="asset/avatar2.jpg" alt="Profile">
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($teacher_name); ?></h4>
                <p><?php echo htmlspecialchars($teacher_email); ?></p>
            </div>
        </div>
        
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>My Courses</h1>
            <p>Manage and monitor current semester curriculum.</p>
        </div>

        <div class="course-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            
            <a href="pages/marks-entry.html?course=CSE4165&name=Advanced+Algorithms" style="text-decoration: none;">
                <div class="stat-card" style="display: flex; flex-direction: column; gap: 15px; transition: transform 0.2s; cursor: pointer; height: 100%;">
                    <div style="background: linear-gradient(135deg, var(--primary-orange), #ff4000); height: 100px; border-radius: 6px; width: 100%;"></div>
                    <div>
                        <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 5px;">CSE 4165: Advanced Algorithms</h3>
                        <p style="color: var(--text-secondary); font-size: 13px;">Algorithm design and complexity analysis.</p>
                    </div>
                </div>
            </a>

            <a href="pages/marks-entry.html?course=DS4102&name=Advanced+Machine+Learning" style="text-decoration: none;">
                <div class="stat-card" style="display: flex; flex-direction: column; gap: 15px; transition: transform 0.2s; cursor: pointer; height: 100%;">
                    <div style="background: linear-gradient(135deg, var(--color-blue), #0056b3); height: 100px; border-radius: 6px; width: 100%;"></div>
                    <div>
                        <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 5px;">DS 4102: Advanced Machine Learning</h3>
                        <p style="color: var(--text-secondary); font-size: 13px;">Neural networks and predictive systems.</p>
                    </div>
                </div>
            </a>

            <a href="pages/marks-entry.html?course=SWE2201&name=Software+Engineering" style="text-decoration: none;">
                <div class="stat-card" style="display: flex; flex-direction: column; gap: 15px; transition: transform 0.2s; cursor: pointer; height: 100%;">
                    <div style="background: linear-gradient(135deg, var(--color-green), #008f48); height: 100px; border-radius: 6px; width: 100%;"></div>
                    <div>
                        <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 5px;">SWE 2201: Software Engineering</h3>
                        <p style="color: var(--text-secondary); font-size: 13px;">Agile methodologies and SDLC concepts.</p>
                    </div>
                </div>
            </a>

        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>