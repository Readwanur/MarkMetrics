<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Student Portal</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="logo.png" alt="MarkMetrics" class="main-logo">
            </div>

            <nav class="sidebar-nav">
                <a href="#" class="nav-item active">
                    <div class="active-indicator"></div>
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="2"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="2"></rect>
                    </svg>
                    <span class="nav-text">Overview</span>
                </a>
                <a href="../Page 2/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="12" width="4" height="8" rx="1"></rect>
                        <rect x="10" y="6" width="4" height="14" rx="1"></rect>
                        <rect x="16" y="10" width="4" height="10" rx="1"></rect>
                    </svg>
                    <span class="nav-text">Grade History</span>
                </a>
                <a href="../Page 3/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span class="nav-text">Grade Management</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="#" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span class="nav-text">Support</span>
                </a>
                <a href="logout.php" class="nav-item logout-btn" id="logoutBtn">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="nav-text">Log Out</span>
                </a>
            </div>
        </aside>


        <main class="main-content">
            <div class="content-wrapper">
                <header class="dashboard-header">
                    <div class="header-titles">
                        <h1>Academic MarkMetrics</h1>
                        <p>Welcome back, <span class="highlight" id="userName">Student</span>. Here is your current
                            academic velocity.</p>
                    </div>
                    <div class="header-actions">
                        <button class="icon-btn">
                            <span class="notification-dot"></span>
                            <i class="ph ph-bell"></i>
                        </button>
                        <button class="icon-btn">
                            <i class="ph ph-gear"></i>
                        </button>
                        <div class="user-avatar">
                            <img src="profile.png" alt="User">
                        </div>
                    </div>
                </header>


                <div class="top-cards-grid">
                    <div class="card gpa-card border-orange">
                        <div class="card-header">
                            <span>CUMULATIVE GPA</span>
                            <i class="ph ph-graduation-cap card-icon-large"></i>
                        </div>
                        <div class="card-body gpa-body">
                            <h2 class="large-value" id="cumulativeGpa">0.00</h2>
                            <span class="change-indicator" id="gpaChange">~0.00</span>
                        </div>
                        <div class="card-footer" id="gpaPercentile">
                            Top % of Department
                        </div>
                    </div>

                    <div class="card credits-card border-blue">
                        <div class="card-header">
                            <span>CREDITS EARNED</span>
                        </div>
                        <div class="card-body credits-body">
                            <h2 class="large-value">
                                <span id="creditsEarned">0</span>
                                <span class="value-muted">/138</span>
                            </h2>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="creditsProgressBar" style="width: 0%;"></div>
                        </div>
                        <div class="card-footer">
                            Progress: <span id="creditsProgressText">0%</span>
                        </div>
                    </div>


                    <div class="card semester-card border-brown">
                        <div class="card-header">
                            <span>CURRENT SEMESTER</span>
                        </div>
                        <div class="card-body semester-body">
                            <div class="semester-icon-box">
                                <i class="ph ph-book-open"></i>
                            </div>
                            <div class="semester-details">
                                <h2 class="medium-value" id="semesterName">Semester</h2>
                                <p class="muted-text" id="coursesEnrolled">0 Courses Enrolled</p>
                            </div>
                        </div>
                        <div class="card-footer italic-text">
                            Next exam: <span id="nextExam">None</span>
                        </div>
                    </div>
                </div>


                <div class="middle-section-grid">
                    <div class="card chart-card">
                        <div class="chart-header">
                            <h3>Performance Velocity</h3>
                            <div class="chart-legend">
                                <span class="legend-item"><span class="legend-dot orange"></span> Major GPA</span>
                                <span class="legend-item"><span class="legend-dot blue"></span> Overall</span>
                            </div>
                        </div>
                        <div class="chart-area">
                            <div class="chart-y-axis">
                                <span>4.0</span>
                                <span>3.0</span>
                                <span>2.0</span>
                                <span>1.0</span>
                                <span>0.0</span>
                            </div>
                            <div class="chart-bars" id="velocityBars">
                            </div>
                        </div>
                    </div>

                    <div class="card pending-exams-card">
                        <h3>Pending Exams</h3>
                        <div class="exams-list" id="examList">
                        </div>
                        <a href="https://www.uiu.ac.bd/academics/calendar/" class="view-all-link">View Academic Calendar
                            <i class="ph ph-arrow-right"></i></a>
                    </div>
                </div>

                <div class="bottom-section-grid">
                    <div class="card small-card history-card">
                        <div class="small-card-icon">
                            <i class="ph ph-clock-counter-clockwise"></i>
                        </div>
                        <div class="small-card-text">
                            <h4>Grade History</h4>
                            <p>Detailed archive of past terms</p>
                        </div>
                    </div>

                    <div class="card small-card insight-card">
                        <div class="insight-wrapper">
                            <span class="insight-label">INSIGHT</span>
                            <p class="insight-text">Maintaining current velocity will result in <span
                                    class="highlight-orange">Summa Cum Laude</span> honors.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>