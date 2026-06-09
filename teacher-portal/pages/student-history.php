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

    <div class="main-content" style="position: relative;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div class="page-header" style="max-width: 60%; margin-bottom: 0;">
                <h1>Student History</h1>
                <p>Comprehensive academic trajectory for <span class="text-orange">Readwan Rumon</span> (ID: 0112330784).<br>Curating three years of excellence.</p>
            </div>

            <div style="text-align: center;">
                <div class="cgpa-card" style="position: static; box-shadow: none; right: auto; top: auto; width: auto; display: inline-block;">
                    <h4>CGPA</h4>
                    <h1>3.85</h1>
                    <p>Top 2%</p>
                </div>
                <div>
                    <a href="academic-performance.php" class="cgpa-view-btn">View</a>
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
                            <h3 style="font-size: 16px; margin-bottom: 4px;">Spring Semester 2024</h3>
                            <p style="color: var(--primary-orange); font-size: 11px; font-weight: 600;">Current Enrollment . 12 CREDITS</p>
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
                            <tr>
                                <td class="text-orange" style="font-weight: 600;">CSE 2216</td>
                                <td>Structure Programming Language</td>
                                <td>3.0</td>
                                <td><span class="badge badge-orange" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">A+</span></td>
                                <td>4.00</td>
                            </tr>
                            <tr>
                                <td class="text-orange" style="font-weight: 600;">EEE 2265</td>
                                <td>Electric Circuit</td>
                                <td>3.0</td>
                                <td><span class="badge" style="background: rgba(255,59,59,0.1); color: var(--color-red);">A-</span></td>
                                <td>3.67</td>
                            </tr>
                            <tr>
                                <td class="text-orange" style="font-weight: 600;">MATH 1199</td>
                                <td>Fundamental Calculus</td>
                                <td>3.0</td>
                                <td><span class="badge" style="background: rgba(255,107,0,0.1); color: var(--primary-orange);">B+</span></td>
                                <td>3.00</td>
                            </tr>
                            <tr>
                                <td class="text-orange" style="font-weight: 600;">CSE 5055</td>
                                <td>Web Programming</td>
                                <td>3.0</td>
                                <td><span class="badge" style="background: rgba(59,130,246,0.1); color: var(--color-blue);">C+</span></td>
                                <td>2.33</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="semester-summaries">
                    <div class="semester-card">
                        <h4>FALL 2026</h4>
                        <h2>3.92</h2>
                        <p class="blue">Dean's List</p>
                    </div>
                    <div class="semester-card">
                        <h4>SPRING 2026</h4>
                        <h2>3.80</h2>
                        <p class="orange">Merit Pass</p>
                    </div>
                    <div class="semester-card">
                        <h4>SPRING 2026</h4>
                        <h2>3.88</h2>
                        <p class="blue">Dean's List</p>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script src="../script.js"></script>
</body>
</html>