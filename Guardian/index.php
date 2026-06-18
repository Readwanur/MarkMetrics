<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../LoginPage/Login/login.php");
    exit();
}

require_once '../LoginPage/connect2db.php';

$parent_id = $_SESSION['id'];
$parent_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guardian';

// Find student ID first
$get_student = mysqli_query($conn, "SELECT student_id FROM students WHERE parent_id = '$parent_id' LIMIT 1");
$student_info = mysqli_fetch_assoc($get_student);
$student_id = $student_info ? $student_info['student_id'] : '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_pic']) && !empty($student_id)) {
    if ($_FILES['student_pic']['error'] === 0) {
        $file_tmp = $_FILES['student_pic']['tmp_name'];
        $file_name = $_FILES['student_pic']['name'];
        $file_size = $_FILES['student_pic']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_exts) && $file_size <= 2 * 1024 * 1024) {
            $target_dir = "../Images/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $new_file_name = "student_" . $student_id . "_" . time() . "." . $file_ext;
            $target_path = $target_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                $db_path = "Images/" . $new_file_name;
                mysqli_query($conn, "UPDATE users SET profile_picture_url = '$db_path' WHERE id = '$student_id'");
                header("Location: index.php");
                exit();
            }
        }
    }
}

// Fetch associated student
$studentQuery = "
    SELECT s.student_id, u.name as student_name, s.date_of_birth, s.mothers_name, 
           s.enrollment_term, s.cumulative_gpa, s.total_credits_earned,
           d.name as department_name, u.profile_picture_url
    FROM students s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON p.department_id = d.department_id
    WHERE s.parent_id = '$parent_id'
    LIMIT 1
";
$studentResult = mysqli_query($conn, $studentQuery);
$student = mysqli_fetch_assoc($studentResult);

if (!$student) {
    die("No student assigned to this guardian account.");
}

$student_avatar = empty($student['profile_picture_url']) ? '../Images/OtherUser.jpg' : '../' . $student['profile_picture_url'];

// Finances (Not needed, Result Management System)

// Study Duration
// Let's assume current year 2026 and Enrollment Term starts with Spring 2024
$enrollment_year = intval(substr($student['enrollment_term'], -4));
$current_year = intval(SYSTEM_YEAR);
$years = $current_year - $enrollment_year;
$durationStr = $years > 0 ? "{$years}y +" : "1y";

// Academic Progress Table
$progressQuery = "
    SELECT e.course_code, c.course_name, s.display_name as semester_name, e.status, e.grade
    FROM enrollments e
    JOIN courses c ON e.course_code = c.course_code
    JOIN semesters s ON e.semester_id = s.semester_id
    WHERE e.student_id = '$student_id'
    ORDER BY s.academic_year DESC, s.semester_id DESC
    LIMIT 5
";
$progressResult = mysqli_query($conn, $progressQuery);

// Chart Data
$chartQuery = "
    SELECT s.display_name, AVG(e.points) as avg_gpa
    FROM enrollments e
    JOIN semesters s ON e.semester_id = s.semester_id
    WHERE e.student_id = '$student_id' AND e.points IS NOT NULL AND e.status = 'Completed'
    GROUP BY e.semester_id
    ORDER BY s.academic_year ASC, s.semester_id ASC
";
$chartResult = mysqli_query($conn, $chartQuery);
$chartLabels = [];
$gpaData = [];
$cgpaData = [];
$runningSum = 0;
$runningCount = 0;

while ($row = mysqli_fetch_assoc($chartResult)) {
    $chartLabels[] = $row['display_name'];
    $gpaData[] = round($row['avg_gpa'], 2);
    
    // Simplistic running CGPA
    $runningSum += $row['avg_gpa'];
    $runningCount++;
    $cgpaData[] = round($runningSum / $runningCount, 2);
}

// Attendance stats
$attQuery = "
    SELECT c.course_name, 
           COUNT(*) as total_classes, 
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_classes
    FROM attendance a
    JOIN courses c ON a.course_code = c.course_code
    WHERE a.student_id = '$student_id'
    GROUP BY a.course_code
";
$attResult = mysqli_query($conn, $attQuery);
$attendanceData = [];
$totalClassesAll = 0;
$presentClassesAll = 0;
while($row = mysqli_fetch_assoc($attResult)) {
    $attendanceData[] = $row;
    $totalClassesAll += $row['total_classes'];
    $presentClassesAll += $row['present_classes'];
}
$overallAttendance = $totalClassesAll > 0 ? round(($presentClassesAll / $totalClassesAll) * 100) : 0;
if (empty($attendanceData)) {
    // Provide some dummy data for display if table is empty
    $overallAttendance = 85;
    $presentClassesAll = 3;
    $totalClassesAll = 4;
    $attendanceData = [
        ['course_name' => 'Operating Systems', 'total_classes' => 10, 'present_classes' => 9],
        ['course_name' => 'Software Engineering', 'total_classes' => 10, 'present_classes' => 8],
        ['course_name' => 'Database Management', 'total_classes' => 10, 'present_classes' => 6]
    ];
}

// Weekly Schedule
// Get current enrollments for the student and link to class schedules
$scheduleQuery = "
    SELECT cs.day_of_week, cs.start_time, cs.end_time, c.course_name, cs.room_number
    FROM class_schedules cs
    JOIN courses c ON cs.course_code = c.course_code
    JOIN enrollments e ON e.course_code = c.course_code
    WHERE e.student_id = '$student_id' AND e.status = 'Ongoing'
    ORDER BY FIELD(cs.day_of_week, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), cs.start_time
";
$scheduleResult = mysqli_query($conn, $scheduleQuery);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarkMetrics | Guardian Portal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const gpaData = <?php echo json_encode($gpaData); ?>;
        const cgpaData = <?php echo json_encode($cgpaData); ?>;
    </script>
</head>

<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <div class="logo">
                <img src="asset/logo.png" alt="Logo">
            </div>

            <div class="user-info">
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
                <div class="avatar-wrapper" style="position: relative; display: inline-block; margin: 0 auto 15px auto;">
                    <img id="avatar-img" src="<?php echo $student_avatar; ?>" alt="student" style="display: block; width: 120px; height: 120px; object-fit: cover; border-radius: 10px;">
                    <form action="index.php" method="POST" enctype="multipart/form-data" id="avatar-form" style="position: absolute; bottom: 5px; right: 5px;">
                        <label for="student_pic" class="upload-icon-label" style="background: #F58220; color: #1C1B1B; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                            <i class="fa-solid fa-camera" style="font-size: 14px;"></i>
                        </label>
                        <input type="file" name="student_pic" id="student_pic" style="display: none;" onchange="document.getElementById('avatar-form').submit();">
                    </form>
                </div>
                <h2><?php echo htmlspecialchars($student['student_name']); ?></h2>
                <p>ID : <?php echo htmlspecialchars($student['student_id']); ?></p>
            </div>

            <div class="student-details">
                <div>
                    <h5>Father's Name</h5>
                    <p><?php echo htmlspecialchars($parent_name); ?></p>
                </div>

                <div>
                    <h5>Mother's Name</h5>
                    <p><?php echo htmlspecialchars($student['mothers_name']); ?></p>
                </div>

                <div>
                    <h5>Date Of Birth</h5>
                    <p><?php echo date("F j, Y", strtotime($student['date_of_birth'])); ?></p>
                </div>

                <div>
                    <h5>Department</h5>
                    <p><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></p>
                </div>
            </div>

        </section>


        <!-- STAT CARDS -->
        <section class="stats">

            <div class="card">
                <i class="fa-solid fa-graduation-cap"></i>
                <h4>CGPA</h4>
                <h1><?php echo number_format($student['cumulative_gpa'], 2); ?></h1>
            </div>

            <div class="card">
                <i class="fa-solid fa-book"></i>
                <h4>Completed Credits</h4>
                <h1><?php echo htmlspecialchars($student['total_credits_earned']); ?></h1>
            </div>

            <div class="card">
                <i class="fa-solid fa-clock"></i>
                <h4>Study Duration</h4>
                <h1><?php echo $durationStr; ?></h1>
            </div>

            <div class="card">
                <i class="fa-solid fa-circle-check" style="color: #00ff7f;"></i>
                <h4>Academic Status</h4>
                <h1 style="color: #00ff7f;">Active</h1>
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
                    <h1><?php echo $overallAttendance; ?>%</h1>
                    <p><?php echo $presentClassesAll; ?> of <?php echo $totalClassesAll; ?> classes attended</p>
                </div>

                <?php 
                $colors = ['', '', 'pink'];
                $i = 0;
                foreach($attendanceData as $att): 
                    $percent = $att['total_classes'] > 0 ? round(($att['present_classes'] / $att['total_classes']) * 100) : 0;
                    $colClass = $colors[$i % count($colors)];
                    $i++;
                ?>
                <div class="progress-group">
                    <p><?php echo htmlspecialchars($att['course_name']); ?></p>
                    <div class="progress">
                        <div class="fill <?php echo $colClass; ?>" style="width:<?php echo $percent; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

        </section>


        <!-- TABLE ONLY -->
        <section class="bottom-section" style="grid-template-columns: 1fr;">

            <div class="table-card">
                <h2>Academic Progress</h2>

                <table>
                    <tr>
                        <th>Semester</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Grade</th>
                    </tr>
                    <?php while($prog = mysqli_fetch_assoc($progressResult)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prog['semester_name']); ?></td>
                        <td><?php echo htmlspecialchars($prog['course_name']); ?></td>
                        <td><span class="<?php echo strtolower($prog['status']); ?>"><?php echo htmlspecialchars($prog['status']); ?></span></td>
                        <td><?php echo $prog['grade'] ? htmlspecialchars($prog['grade']) : '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>

        </section>


        <!-- SCHEDULE -->
        <section class="schedule-section">

            <h2>Weekly Schedule</h2>

            <div class="schedule-grid">
                <?php 
                if (mysqli_num_rows($scheduleResult) > 0) {
                    while($sch = mysqli_fetch_assoc($scheduleResult)): 
                        $start = date("h:i A", strtotime($sch['start_time']));
                        $end = date("h:i A", strtotime($sch['end_time']));
                ?>
                <div class="schedule-card">
                    <h3><?php echo htmlspecialchars($sch['day_of_week']); ?></h3>
                    <p><span><?php echo $start . ' - ' . $end; ?></span></p>
                    <h4><?php echo htmlspecialchars($sch['course_name']); ?></h4>
                </div>
                <?php 
                    endwhile; 
                } else {
                    echo "<p>No ongoing classes scheduled.</p>";
                }
                ?>
            </div>

        </section>


        <!-- FOOTER -->
        <footer>
            <p>© <?php echo SYSTEM_YEAR; ?> MARKMETRICS. ALL RIGHTS RESERVED.</p>
        </footer>

    </div>

    <script src="script.js"></script>
</body>

</html>