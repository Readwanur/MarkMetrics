<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Grade Management</title>
    <meta name="description" content="Manage your grades, submit correction requests, and withdraw from courses on the MarkMetrics Student Portal.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Confirmation Modal -->
    <div id="confirm-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="ph ph-warning-circle"></i>
            </div>
            <h3 id="modal-title">Confirm Action</h3>
            <p id="modal-message" class="modal-message">Are you sure?</p>
            <div id="modal-details" class="modal-details"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
                <button class="btn btn-primary" id="modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

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
                    <span class="muted">STUDENT PORTAL</span>
                    <span class="separator">›</span>
                    <span class="orange">GRADE MANAGEMENT</span>
                </div>
                <div class="header-main">
                    <div class="header-left">
                        <h1>Grade Management</h1>
                        <p class="muted">Submit grade correction requests and manage course withdrawals. All changes are logged for audit.</p>
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
                            <span class="pill-label">PENDING REQUESTS</span>
                            <span class="pill-value orange-text" id="pending-count">
                                <span class="skeleton-text">--</span>
                            </span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-pill">
                            <span class="pill-label">APPROVAL RATE</span>
                            <span class="pill-value blue-text" id="approval-rate">
                                <span class="skeleton-text">--</span>
                            </span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-pill">
                            <span class="pill-label">ONGOING COURSES</span>
                            <span class="pill-value green-text" id="ongoing-count">
                                <span class="skeleton-text">--</span>
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" data-tab="correction" id="tab-correction">
                    <i class="ph ph-note-pencil"></i>
                    Grade Correction
                </button>
                <button class="tab-btn" data-tab="withdrawal" id="tab-withdrawal">
                    <i class="ph ph-sign-out"></i>
                    Course Withdrawal
                </button>
            </div>

            <div class="content-grid">
                <div class="form-container">

                    <!-- ═══════ TAB 1: Grade Correction ═══════ -->
                    <div class="tab-panel active" id="panel-correction">
                        <div class="card form-card">
                            <!-- Student Info (auto-loaded) -->
                            <div class="student-info-bar" id="student-info-bar">
                                <div class="student-info-left">
                                    <div class="student-avatar-small">
                                        <i class="ph ph-student"></i>
                                    </div>
                                    <div class="student-meta">
                                        <span class="student-name" id="student-name">Loading...</span>
                                        <span class="student-id-display" id="student-id-display">---</span>
                                    </div>
                                </div>
                                <div class="student-info-right">
                                    <span class="badge-active">ACTIVE STUDENT</span>
                                </div>
                            </div>

                            <div class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF8A00" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                                <span>Grade Correction Request</span>
                            </div>

                            <div class="form-row">
                                <div class="input-group flex-2">
                                    <label>COURSE SELECTION</label>
                                    <select id="course-select">
                                        <option value="" disabled selected>Loading courses...</option>
                                    </select>
                                </div>
                                <div class="input-group flex-1">
                                    <label>CURRENT GRADE</label>
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
                                        <option value="C-">C-</option>
                                        <option value="D+">D+</option>
                                        <option value="D">D</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                            </div>

                            <div class="input-group">
                                <label>JUSTIFICATION (REASON FOR CHANGE)</label>
                                <textarea id="justification" placeholder="Detail the specific administrative error or grading correction requirements..."></textarea>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-ghost" id="save-draft">
                                    <i class="ph ph-floppy-disk"></i>
                                    Save Draft
                                </button>
                                <button class="btn btn-primary" id="submit-update">
                                    <i class="ph ph-paper-plane-tilt"></i>
                                    Submit Request
                                </button>
                            </div>

                            <div class="draft-notice hidden" id="draft-notice">
                                <i class="ph ph-info"></i>
                                <span>Draft restored from your last session. <a href="#" id="clear-draft">Clear</a></span>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════ TAB 2: Course Withdrawal ═══════ -->
                    <div class="tab-panel" id="panel-withdrawal">
                        <div class="card form-card">
                            <div class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fa5252" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                <span>Course Withdrawal</span>
                            </div>

                            <div class="withdrawal-warning">
                                <i class="ph ph-warning"></i>
                                <div>
                                    <strong>Important Notice</strong>
                                    <p>Withdrawing from a course will assign a grade of <strong>"W"</strong> on your transcript. This action is permanent and cannot be undone. Only <strong>ongoing</strong> courses are eligible for withdrawal.</p>
                                </div>
                            </div>

                            <div class="input-group">
                                <label>SELECT COURSE TO WITHDRAW</label>
                                <select id="withdrawal-course-select">
                                    <option value="" disabled selected>Loading ongoing courses...</option>
                                </select>
                            </div>

                            <div class="withdrawal-course-info hidden" id="withdrawal-course-info">
                                <div class="wci-row">
                                    <span class="wci-label">Course</span>
                                    <span class="wci-value" id="wci-name">--</span>
                                </div>
                                <div class="wci-row">
                                    <span class="wci-label">Credits</span>
                                    <span class="wci-value" id="wci-credits">--</span>
                                </div>
                                <div class="wci-row">
                                    <span class="wci-label">Semester</span>
                                    <span class="wci-value" id="wci-semester">--</span>
                                </div>
                                <div class="wci-row">
                                    <span class="wci-label">Current Status</span>
                                    <span class="wci-value badge-running-sm">Ongoing</span>
                                </div>
                            </div>

                            <div class="input-group">
                                <label>REASON FOR WITHDRAWAL (OPTIONAL)</label>
                                <textarea id="withdrawal-reason" placeholder="Provide a reason for withdrawing from this course..." rows="3"></textarea>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-danger" id="withdraw-btn" disabled>
                                    <i class="ph ph-sign-out"></i>
                                    Withdraw from Course
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════ Right Sidebar: Activity ═══════ -->
                <div class="activity-container">
                    <div class="card activity-card">
                        <div class="card-header">
                            <h3>Recent Activity</h3>
                        </div>
                        
                        <div class="activity-list" id="activity-list">
                            <!-- Loading skeleton -->
                            <div class="activity-skeleton">
                                <div class="skeleton-row"></div>
                                <div class="skeleton-row short"></div>
                                <div class="skeleton-row"></div>
                                <div class="skeleton-row short"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card protocol-card">
                        <div class="card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FF8A00" stroke-width="2"><path d="M9.663 17h4.674"></path><path d="M10 20h4"></path><path d="M12 2a7 7 0 0 1 7 7c0 2.38-1.235 4.474-3.1 5.7L15 17H9l-.9-2.3C6.235 13.474 5 11.38 5 9a7 7 0 0 1 7-7z"></path></svg>
                            <span>Quick Tip</span>
                        </div>
                        <p>Ensure all grade correction requests include a clear justification. Requests without proper reasoning are typically rejected within 48 hours by the registrar's office.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
