<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../LoginPage/Login/login.php");
    exit();
}
$parent_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guardian';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Guardian Portal</title>

    <!-- CSS -->
    <link rel="stylesheet" href="style.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div class="container">

        <!-- HEADER -->
        <header>
            <div class="logo">
                <img src="asset/logo.png" alt="Logo">
            </div>

            <div class="user-info">
                <img src="asset/avatar.png" alt="profile">
                <div>
                    <h4><?php echo htmlspecialchars($parent_name); ?></h4>
                    <p>Guardian Access</p>
                </div>
                <a href="logout.php" class="logout-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Log Out</span>
                </a>
            </div>
        </header>


        <!-- PROFILE SECTION -->
        <section class="profile-section">

            <div class="profile-card">
                <img src="asset/canvas.png" alt="student">
                <h2>Bhondu Mahi</h2>
                <p>ID : 0112331077</p>
            </div>

            <div class="student-details">
                <div>
                    <h5>Father's Name</h5>
                    <p><?php echo htmlspecialchars($parent_name); ?></p>
                </div>

                <div>
                    <h5>Mother's Name</h5>
                    <p>Shake Hasina</p>
                </div>

                <div>
                    <h5>Date Of Birth</h5>
                    <p>September 14, 2004</p>
                </div>

                <div>
                    <h5>Department</h5>
                    <p>Computer Science & Engineering</p>
                </div>
            </div>

        </section>


        <!-- STAT CARDS -->
        <section class="stats">

            <div class="card">
                <i class="fa-solid fa-graduation-cap"></i>
                <h4>CGPA</h4>
                <h1>3.82</h1>
            </div>

            <div class="card">
                <i class="fa-solid fa-book"></i>
                <h4>Completed Credits</h4>
                <h1>67</h1>
            </div>

            <div class="card">
                <i class="fa-solid fa-clock"></i>
                <h4>Study Duration</h4>
                <h1>2y 8m</h1>
            </div>

            <div class="card due-card">
                <i class="fa-solid fa-wallet"></i>
                <h4>Current Due</h4>
                <h1>1,240৳</h1>
            </div>

        </section>


        <!-- CHARTS -->
        <section class="charts-section">

            <div class="chart-card">
                <h2>Result Summary</h2>
                <canvas id="resultChart"></canvas>
            </div>

            <div class="attendance-card">
                <h2>Attendance Today</h2>

                <div class="attendance-box">
                    <h1>85%</h1>
                    <p>3 of 4 classes attended</p>
                </div>

                <div class="progress-group">
                    <p>Operating Systems</p>
                    <div class="progress">
                        <div class="fill" style="width:92%"></div>
                    </div>
                </div>

                <div class="progress-group">
                    <p>Software Engineering</p>
                    <div class="progress">
                        <div class="fill" style="width:78%"></div>
                    </div>
                </div>

                <div class="progress-group">
                    <p>Database Management</p>
                    <div class="progress">
                        <div class="fill pink" style="width:64%"></div>
                    </div>
                </div>

            </div>

        </section>


        <!-- TABLE + FINANCE -->
        <section class="bottom-section">

            <div class="table-card">
                <h2>Academic Progress</h2>

                <table>
                    <tr>
                        <th>Semester</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Grade</th>
                    </tr>

                    <tr>
                        <td>Spring 2026</td>
                        <td>Artificial Intelligence</td>
                        <td><span class="running">Running</span></td>
                        <td>-</td>
                    </tr>

                    <tr>
                        <td>Spring 2026</td>
                        <td>Network Security</td>
                        <td><span class="running">Running</span></td>
                        <td>-</td>
                    </tr>

                    <tr>
                        <td>Fall 2025</td>
                        <td>Algorithms & Complexity</td>
                        <td><span class="completed">Completed</span></td>
                        <td>A</td>
                    </tr>

                    <tr>
                        <td>Fall 2025</td>
                        <td>Web Architecture</td>
                        <td><span class="completed">Completed</span></td>
                        <td>B+</td>
                    </tr>
                </table>
            </div>


            <div class="finance-card">
                <h2>Financial Overview</h2>

                <div class="finance-grid">
                    <div>
                        <h5>Total Billed</h5>
                        <h3>12,450৳</h3>
                    </div>

                    <div>
                        <h5>Paid Amount</h5>
                        <h3 class="green">10,210৳</h3>
                    </div>

                    <div>
                        <h5>Waived</h5>
                        <h3>1,000৳</h3>
                    </div>

                    <div>
                        <h5>Balance</h5>
                        <h3 class="orange">1,240৳</h3>
                    </div>
                </div>

                <a href="https://ucam.uiu.ac.bd/Security/LogIn.aspx" class="payment-btn">MAKE A PAYMENT</a>

            </div>

        </section>


        <!-- SCHEDULE -->
        <section class="schedule-section">

            <h2>Weekly Schedule</h2>

            <div class="schedule-grid">

                <div class="schedule-card">
                    <h3>Wednesday</h3>
                    <p><span>09:00 - 10:30 AM</span></p>
                    <h4>Operating Systems</h4>
                </div>

                <div class="schedule-card">
                    <h3>Tuesday</h3>
                    <p><span>01:00 - 02:30 PM</span></p>
                    <h4>Software Engineering</h4>
                </div>

                <div class="schedule-card">
                    <h3>Saturday</h3>
                    <p><span>10:00 - 01:00 PM</span></p>
                    <h4>Project Workshop</h4>
                </div>

            </div>

        </section>


        <!-- FOOTER -->
        <footer>
            <p>© 2026 MARKMETRICS. ALL RIGHTS RESERVED.</p>
        </footer>

    </div>

    <script src="script.js"></script>
</body>

</html>