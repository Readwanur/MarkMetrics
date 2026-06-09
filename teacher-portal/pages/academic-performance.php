<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../LoginPage/Login/login.php");
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
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                </a>
               <ul class="submenu show">
                    <li>
                        <a href="withdraw-request.php">Withdraw Requests</a>
                    </li>
                    <li>
                        <a href="grade-management.php">Grade Management</a>
                    </li>
                    <li>
                        <a href="academic-performance.php">Academic Performance</a>
                    </li>
                    <li>
                        <a href="marks-entry.php">Marks Entry</a>
                    </li>
                    <li>
                        <a href="student-history.php">Student History</a>
                    </li>
                </ul>
            </li>
        </ul>

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

        <div class="profile-box">
            <img src="../asset/avatar2.jpg" alt="Profile">
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($teacher_name); ?></h4>
                <p><?php echo htmlspecialchars($teacher_email); ?></p>
            </div>
        </div>
    </div>

    <div class="main-content">
        
        <div class="page-header">
            <h1>Academic Performance</h1>
            <p>Comprehensive academic trajectory for <span class="text-orange">Readwan Rumon</span> (ID:0112330784).<br>Curating three years of excellence.</p>
        </div>

        <div class="perf-stats-row">
            <div class="perf-stat-card">
                <h4>CURRENT GPA</h4>
                <h2>3.85 <span>+0.15</span></h2>
                <div style="height: 4px; background: var(--primary-orange); width: 80%; margin-top: 15px;"></div>
            </div>

            <div class="perf-stat-card">
                <h4>ATTENDANCE PERCENTAGE</h4>
                <h2 class="white">97.4<span>%</span></h2>
                <p style="font-size: 11px; color: var(--text-secondary); margin-bottom: 15px;">Only 2 excused absences this semester</p>
                <div style="display: flex; gap: 4px;">
                    <div style="height: 4px; background: var(--primary-orange); flex: 1;"></div>
                    <div style="height: 4px; background: var(--primary-orange); flex: 1;"></div>
                    <div style="height: 4px; background: var(--primary-orange); flex: 1;"></div>
                    <div style="height: 4px; background: var(--primary-orange); flex: 1;"></div>
                    <div style="height: 4px; background: var(--primary-orange); flex: 1;"></div>
                    <div style="height: 4px; background: #3a3a48; flex: 1;"></div>
                </div>
            </div>

            <div class="perf-stat-card">
                <h4>PENDING ASSIGNMENTS</h4>
                <h2 class="white" style="color: var(--color-blue);">02 <span>/ 14 Total</span></h2>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                    <span class="badge" style="background: rgba(255,59,59,0.2); color: var(--color-red);">DUE TOMORROW</span>
                    <span style="font-size: 11px; color: var(--text-primary);">Physics: Lab Report</span>
                </div>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-container">
                <h3>Performance Velocity</h3>
                <div style="height: 250px;">
                    <canvas id="velocityChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3>Subject Aptitude</h3>
                <div style="height: 250px;">
                    <canvas id="aptitudeChart"></canvas>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 20px; font-size: 11px;">
                    <div>
                        <div style="color: var(--text-secondary); margin-bottom: 5px;">Technical proficiency</div>
                        <div style="color: var(--text-secondary);">Creative Application</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: var(--color-blue); margin-bottom: 5px;">Superior</div>
                        <div style="color: var(--color-blue);">Advance</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Exam</th>
                        <th>CREDITS</th>
                        <th>WEIGHT</th>
                        <th>SCORE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 600;">Structure Programming Language</td>
                        <td style="color: var(--text-secondary);">Mid-term Exam</td>
                        <td>03</td>
                        <td>30%</td>
                        <td class="text-orange" style="font-weight: 700; font-size: 16px;">94/100</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Electric Circuit</td>
                        <td style="color: var(--text-secondary);">Bonding Project</td>
                        <td>03</td>
                        <td>15%</td>
                        <td class="text-orange" style="font-weight: 700; font-size: 16px;">88/100</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Fundamental Calculus</td>
                        <td style="color: var(--text-secondary);">Analysis Paper</td>
                        <td>03</td>
                        <td>20%</td>
                        <td class="text-orange" style="font-weight: 700; font-size: 16px;">91/100</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Web Programming</td>
                        <td style="color: var(--text-secondary);">Weekly Quiz</td>
                        <td>03</td>
                        <td>100%</td>
                        <td class="text-orange" style="font-weight: 700; font-size: 16px;">100/100</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <script src="../script.js"></script>
</body>
</html>