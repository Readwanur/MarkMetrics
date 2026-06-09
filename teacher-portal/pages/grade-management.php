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
                <a href="#" class="active">
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
            <h1>Grade Change Requests</h1>
        </div>

        <div class="stats-row">
            <div class="stat-card orange">
                <div>
                    <h4>PENDING</h4>
                    <h2>07</h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
            </div>

            <div class="stat-card red">
                <div>
                    <h4>DISMISSED</h4>
                    <h2>10</h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-xmark"></i></div>
            </div>

            <div class="stat-card green">
                <div>
                    <h4>APPROVED</h4>
                    <h2 style="color: var(--color-green);">15</h2>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-circle-check"></i></div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header-actions" style="border-bottom: none; align-items: center;">
                <h3 style="font-size: 16px;">Grade Management</h3>
                <div class="table-actions-right">
                    <button class="btn-dark" style="background: transparent; border: none; padding: 5px;"><i class="fa-solid fa-arrow-down-short-wide"></i></button>
                    <button class="btn-dark" style="background: transparent; border: none; padding: 5px;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>COURSE CODE</th>
                        <th>COURSE NAME</th>
                        <th>STUDENT ID</th>
                        <th>STUDENT NAME</th>
                        <th>CURRENT GRADE</th>
                        <th>DESIRED GRADE</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">CSE 221</td>
                        <td>Structure Programming<br>Language</td>
                        <td>21-44502-1</td>
                        <td>Julian V.<br>Sterling</td>
                        <td><span class="badge" style="background: rgba(0,210,106,0.1); border: 1px solid var(--color-green); color: var(--color-green);">A+ (4.00)</span></td>
                        <td>A+</td>
                        <td><span class="badge badge-green">VERIFIED</span></td>
                    </tr>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">EEE 226</td>
                        <td>Electric Circuit</td>
                        <td>21-44503-2</td>
                        <td>Readwan<br>Rumon</td>
                        <td><span class="badge" style="background: rgba(59,130,246,0.1); border: 1px solid var(--color-blue); color: var(--color-blue);">A- (3.67)</span></td>
                        <td>A</td>
                        <td><span class="badge badge-orange">REVIEW</span></td>
                    </tr>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">MATH 110</td>
                        <td>Fundamental Calculus</td>
                        <td>21-44504-3</td>
                        <td>Ashraful<br>Rafi</td>
                        <td><span class="badge" style="background: rgba(255,107,0,0.1); border: 1px solid var(--primary-orange); color: var(--primary-orange);">B+ (3.00)</span></td>
                        <td>A-</td>
                        <td><span class="badge badge-orange">REVIEW</span></td>
                    </tr>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">CSE 505</td>
                        <td>Web Programming</td>
                        <td>21-44505-4</td>
                        <td>Billah<br>Maruf</td>
                        <td><span class="badge" style="background: rgba(255,107,0,0.1); border: 1px solid var(--primary-orange); color: var(--primary-orange);">C+ (2.33)</span></td>
                        <td>B</td>
                        <td><span class="badge badge-red">AT RISK</span></td>
                    </tr>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">ENG 102</td>
                        <td>English Composition</td>
                        <td>21-44506-5</td>
                        <td>Khalad<br>Emon</td>
                        <td><span class="badge" style="background: rgba(0,210,106,0.1); border: 1px solid var(--color-green); color: var(--color-green);">A (3.75)</span></td>
                        <td>A</td>
                        <td><span class="badge badge-green" style="text-transform: lowercase;">verified</span></td>
                    </tr>
                    <tr>
                        <td class="text-orange" style="font-weight: 700;">SOC 201</td>
                        <td>Sociology & Tech</td>
                        <td>21-44507-6</td>
                        <td>Nerd<br>Romoan</td>
                        <td><span class="badge" style="background: #2a2a35; border: 1px solid #5a5a6a; color: var(--text-secondary);">N/A (0.00)</span></td>
                        <td>A-</td>
                        <td><span class="badge badge-dark">PENDING</span></td>
                    </tr>
                </tbody>
            </table>

            <div class="pagination">
                <div>Showing <span style="font-weight: 600; color: #fff;">6</span> of <span style="font-weight: 600; color: #fff;">24</span> records</div>
                <div class="page-controls">
                    <a href="#" style="color: var(--text-secondary); text-decoration: none;"><i class="fa-solid fa-chevron-left"></i></a>
                    <div class="page-numbers">
                        <a href="#" class="page-btn active">1</a>
                        <a href="#" class="page-btn">2</a>
                        <a href="#" class="page-btn">3</a>
                        <span style="padding: 0 5px;">...</span>
                        <a href="#" class="page-btn">4</a>
                    </div>
                    <a href="#" class="next-page">NEXT PAGE <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i></a>
                </div>
            </div>
        </div>

    </div>

    <script src="../script.js"></script>
</body>
</html>