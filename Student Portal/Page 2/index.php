<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@700&display=swap" rel="stylesheet">
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
                <a href="../Page 1/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="2"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="2"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="2"></rect>
                    </svg>
                    <span class="nav-text">Overview</span>
                </a>
                <a href="#" class="nav-item active">
                    <div class="active-indicator"></div>
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="12" width="4" height="8" rx="1"></rect>
                        <rect x="10" y="6" width="4" height="14" rx="1"></rect>
                        <rect x="16" y="10" width="4" height="10" rx="1"></rect>
                    </svg>
                    <span class="nav-text">Grade History</span>
                </a>
                <a href="../Page 3/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span class="nav-text">Grade Management</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="#" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span class="nav-text">Support</span>
                </a>
                <a href="logout.php" class="nav-item logout-btn" id="logoutBtn">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="nav-text">Log Out</span>
                </a>
            </div>
        </aside>

     
        <main class="main-content">
            <header class="top-header">
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

            <div class="dashboard-content">

                <div class="primary-col">
                    <section class="profile-section">
                        <div class="profile-info">
                            <span class="section-label">ACADEMIC PROFILE</span>
                            <h1 id="profile-name">Loading...</h1>
                            <p id="profile-major" class="subtitle">Loading...</p>
                            <button id="download-transcript-btn" class="btn-download">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                Download Transcript
                            </button>
                        </div>
                        <div class="cgpa-card">
                            <span class="cgpa-label">CGPA</span>
                            <div class="cgpa-value" id="profile-cgpa">0.00</div>
                            <div class="cgpa-trend">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H7M17 7V17"></path></svg>
                                <span>+0.04 vs Last trimester</span>
                            </div>
                        </div>
                    </section>

                    <section class="stats-row">
                        <div class="stat-box">
                            <span class="stat-label">STUDENT STATUS</span>
                            <div class="stat-value">Active / <span id="profile-year">...</span></div>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label">ENROLLMENT</span>
                            <div class="stat-value"><span id="profile-term">...</span> — Present</div>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label">MAJOR GPA</span>
                            <div class="stat-value highlight" id="profile-major-gpa">0.00</div>
                        </div>
                    </section>

                    <section class="history-section">
                        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h2>Academic History</h2>
                        </div>

                        <div class="enrollments-container" id="enrollments-list">
                        </div>
                    </section>
                </div>

                
                <div class="secondary-col">
                    <div class="side-card progress-card">
                        <div class="card-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4dabf7" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                            <h3>Academic<br>Progress</h3>
                        </div>
                        
                        <div class="gpa-velocity">
                            <span class="mini-label">GPA VELOCITY</span>
                            <h4>Consistent<br>Growth</h4>
                            <span class="percentage">+4.2%</span>
                            
                            <div class="bar-chart">
                                <!-- Bars populated dynamically from enrollment data -->
                            </div>
                        </div>

                        <div class="mini-stats-grid">
                            <div class="mini-stat">
                                <span class="mini-label">TOTAL<br>CREDITS</span>
                                <h5><span id="credits-earned">0</span> / <br><span id="credits-total">0</span></h5>
                                <div class="progress-line">
                                    <div class="fill orange" style="width: 48%"></div>
                                </div>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-label">DEAN'S<br>LIST</span>
                                <h5><span id="deans-list">0</span><br>Terms</h5>
                                <div class="dots-line">
                                    <span class="dot filled"></span>
                                    <span class="dot filled"></span>
                                    <span class="dot filled"></span>
                                    <span class="dot filled"></span>
                                </div>
                            </div>
                        </div>

                        <div class="completion-bars">
                            <div class="comp-row">
                                <span class="comp-label">Major Completion</span>
                                <span class="comp-val" id="major-comp">0%</span>
                            </div>
                            <div class="comp-row">
                                <span class="comp-label">General Electives</span>
                                <span class="comp-val" id="gen-comp">0%</span>
                            </div>
                            <div class="comp-row">
                                <span class="comp-label">Residency Requirement</span>
                                <span class="comp-val highlight-blue">Met</span>
                            </div>
                        </div>
                    </div>

                    <div class="side-card insight-card">
                        <svg class="bg-star" width="100" height="100" viewBox="0 0 24 24" fill="#1e1e1e" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <span class="mini-label orange-text">MARKMETRICS INSIGHT</span>
                        <p>Maintaining your current <strong>4.0 GPA</strong> for the final semester will graduate you with <strong>Magna Cum Laude</strong> honors.</p>
                        <a href="#" class="pathway-link">VIEW GRADUATION PATHWAY <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="transcript-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Download Transcript</h2>
                <button id="close-modal-btn" class="icon-btn close-btn">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="modal-instruction">Select the semesters you want to include in your official transcript:</p>
                <div id="semester-checkboxes" class="checkbox-list">
                </div>
            </div>
            <div class="modal-footer">
                <button id="cancel-download-btn" class="btn-text">Cancel</button>
                <button id="confirm-download-btn" class="btn-primary">Generate PDF</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="script.js"></script>
</body>
</html>
