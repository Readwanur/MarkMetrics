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
                <a href="../Page 2/index.php" class="nav-item">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="12" width="4" height="8" rx="1"></rect>
                        <rect x="10" y="6" width="4" height="14" rx="1"></rect>
                        <rect x="16" y="10" width="4" height="10" rx="1"></rect>
                    </svg>
                    <span class="nav-text">Grade History</span>
                </a>
                <a href="index.php" class="nav-item active">
                    <div class="active-indicator"></div>
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
                <a href="logout.php" class="nav-item">
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
                <div class="breadcrumb">
                    <span class="muted">REGISTRY</span>
                    <span class="separator">›</span>
                    <span class="orange">GRADE CORRECTION</span>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1>Initiate Registry Change</h1>
                        <p class="muted">Secure protocol for academic record adjustments. All changes are logged for internal audit and compliance.</p>
                    </div>
                    <div class="header-right">
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
                    </div>
                </div>

                <div class="header-stats-row">
                    <div class="stats-card">
                        <div class="stat-pill">
                            <span class="pill-label">PENDING TASKS</span>
                            <span class="pill-value orange-text" id="pending-count">12</span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-pill">
                            <span class="pill-label">APPROVAL RATE</span>
                            <span class="pill-value blue-text" id="approval-rate">94%</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-grid">
                <div class="form-container">
                    <div class="card form-card">
                        <div class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF8A00" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                            <span>Student Selection & Course Details</span>
                        </div>

                        <div class="input-group">
                            <label>LOCATE STUDENT PROFILE</label>
                            <div class="search-wrapper">
                                <input type="text" id="student-search" placeholder="Search by name, ID, or enrollment...">
                                <div class="kbd-shortcuts">
                                    <kbd>CMD</kbd> <kbd>K</kbd>
                                </div>
                                <div id="search-results" class="search-dropdown hidden"></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-group flex-2">
                                <label>COURSE SELECTION</label>
                                <select id="course-select">
                                    <option value="" disabled selected>Select a course</option>
                                </select>
                            </div>
                            <div class="input-group flex-1">
                                <label>CURRENT</label>
                                <div class="value-display" id="current-grade">--</div>
                            </div>
                            <div class="input-group flex-1">
                                <label>NEW GRADE</label>
                                <select id="new-grade">
                                    <option value="A+">A+</option>
                                    <option value="A">A</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B">B</option>
                                    <option value="B-">B-</option>
                                    <option value="C+">C+</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                    <option value="F">F</option>
                                </select>
                            </div>
                        </div>

                        <div class="input-group">
                            <label>JUSTIFICATION (REASON FOR CHANGE)</label>
                            <textarea id="justification" placeholder="Detail the specific administrative error or grading correction requirements..."></textarea>
                        </div>

                        <div class="input-group">
                            <label>DIGITAL EVIDENCE (OPTIONAL)</label>
                            <div class="upload-zone" id="upload-zone">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                <p>Drop PDF evidence here or <span>browse files</span></p>
                                <small>Maximum file size: 10MB. Accepted formats: PDF, JPG, PNG.</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-secondary" id="save-draft">Save Draft</button>
                            <button class="btn btn-primary" id="submit-update">Submit Registry Update</button>
                        </div>
                    </div>
                </div>

                <div class="activity-container">
                    <div class="card activity-card">
                        <div class="card-header">
                            <h3>Registry Activity</h3>
                            <button class="icon-btn-small">•••</button>
                        </div>
                        
                        <div class="activity-list" id="activity-list">
                    
                        </div>

                        <button class="btn-link">VIEW FULL AUDIT HISTORY</button>
                    </div>

                    <div class="card protocol-card">
                        <div class="card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FF8A00" stroke-width="2"><path d="M9.663 17h4.674"></path><path d="M10 20h4"></path><path d="M12 2a7 7 0 0 1 7 7c0 2.38-1.235 4.474-3.1 5.7L15 17H9l-.9-2.3C6.235 13.474 5 11.38 5 9a7 7 0 0 1 7-7z"></path></svg>
                            <span>Registry Protocol Tip</span>
                        </div>
                        <p>Ensure all "Grade Incomplete" conversions are backed by a physical exam scan. Requests missing evidence are flagged by the Dean's office for 48-hour manual review.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
