<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../LoginPage/Login/login.php");
    exit();
}
include('../../LoginPage/connect2db.php');

$teacher_id = $_SESSION['id'];
$user_q = mysqli_query($conn, "SELECT name, email FROM users WHERE id = '$teacher_id'");
$teacher_data = mysqli_fetch_assoc($user_q);
$teacher_name = $teacher_data['name'] ?? $_SESSION['name'];
$teacher_email = $teacher_data['email'] ?? $_SESSION['email'];

$course_code = isset($_GET['course']) ? $_GET['course'] : 'CSE-4165';

// Normalize course code formatting (e.g. CSE4165 to CSE-4165)
if (strpos($course_code, '-') === false && strlen($course_code) > 3) {
    $course_code = substr($course_code, 0, 3) . '-' . substr($course_code, 3);
}

$course_q = mysqli_query($conn, "SELECT c.course_name, s.semester_name, s.academic_year 
                                 FROM courses c 
                                 LEFT JOIN semesters s ON c.semester_id = s.semester_id 
                                 WHERE c.course_code = '$course_code'");
$course_info = mysqli_fetch_assoc($course_q);
$course_name = $course_info ? $course_info['course_name'] : 'Unknown Course';
$trimester = $course_info ? ($course_info['semester_name'] . ' ' . $course_info['academic_year']) : 'Unknown Term';

// Fetch Enrollments data
$enrollment_query = "SELECT e.midterm_score, e.final_score, e.ct_score, e.assignment_score, e.total_score, e.grade, s.cumulative_gpa
                     FROM enrollments e
                     JOIN students s ON e.student_id = s.student_id
                     WHERE e.course_code = '$course_code'";
$enrollments_res = mysqli_query($conn, $enrollment_query);

$total_students = 0;
$grade_counts = ['A' => 0, 'A-' => 0, 'B+' => 0, 'B' => 0, 'B-' => 0, 'C+' => 0, 'C' => 0, 'C-' => 0, 'D+' => 0, 'D' => 0, 'F' => 0];
$mark_dist = ['0-59' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];

$freq_data = [
    'Total Marks' => [
        ['range' => '90 - 100', 'count' => 0],
        ['range' => '80 - 89', 'count' => 0],
        ['range' => '70 - 79', 'count' => 0],
        ['range' => '60 - 69', 'count' => 0],
        ['range' => '0 - 59', 'count' => 0]
    ],
    'Midterm' => [
        ['range' => '25 - 30', 'count' => 0],
        ['range' => '20 - 24', 'count' => 0],
        ['range' => '15 - 19', 'count' => 0],
        ['range' => '0 - 14', 'count' => 0]
    ],
    'Final' => [
        ['range' => '35 - 40', 'count' => 0],
        ['range' => '30 - 34', 'count' => 0],
        ['range' => '20 - 29', 'count' => 0],
        ['range' => '0 - 19', 'count' => 0]
    ],
    'Class Tests' => [
        ['range' => '16 - 20', 'count' => 0],
        ['range' => '10 - 15', 'count' => 0],
        ['range' => '0 - 9', 'count' => 0]
    ]
];

$scatter_data = [];

if ($enrollments_res) {
    while ($row = mysqli_fetch_assoc($enrollments_res)) {
        $total_students++;
        $g = $row['grade'];
        if (array_key_exists($g, $grade_counts)) {
            $grade_counts[$g]++;
        }

        $total = $row['total_score'] !== null ? floatval($row['total_score']) : null;
        $mid = $row['midterm_score'] !== null ? floatval($row['midterm_score']) : null;
        $fin = $row['final_score'] !== null ? floatval($row['final_score']) : null;
        $ct = $row['ct_score'] !== null ? floatval($row['ct_score']) : null;
        $cgpa = $row['cumulative_gpa'] !== null ? floatval($row['cumulative_gpa']) : null;

        if ($total !== null && $cgpa !== null) {
            $scatter_data[] = ['x' => $total, 'y' => $cgpa];
        }

        // Mark Dist
        if ($total !== null) {
            if ($total >= 90) { $mark_dist['90-100']++; $freq_data['Total Marks'][0]['count']++; }
            elseif ($total >= 80) { $mark_dist['80-89']++; $freq_data['Total Marks'][1]['count']++; }
            elseif ($total >= 70) { $mark_dist['70-79']++; $freq_data['Total Marks'][2]['count']++; }
            elseif ($total >= 60) { $mark_dist['60-69']++; $freq_data['Total Marks'][3]['count']++; }
            else { $mark_dist['0-59']++; $freq_data['Total Marks'][4]['count']++; }
        }
        
        // Midterm Dist
        if ($mid !== null) {
            if ($mid >= 25) { $freq_data['Midterm'][0]['count']++; }
            elseif ($mid >= 20) { $freq_data['Midterm'][1]['count']++; }
            elseif ($mid >= 15) { $freq_data['Midterm'][2]['count']++; }
            else { $freq_data['Midterm'][3]['count']++; }
        }

        // Final Dist
        if ($fin !== null) {
            if ($fin >= 35) { $freq_data['Final'][0]['count']++; }
            elseif ($fin >= 30) { $freq_data['Final'][1]['count']++; }
            elseif ($fin >= 20) { $freq_data['Final'][2]['count']++; }
            else { $freq_data['Final'][3]['count']++; }
        }

        // CT Dist
        if ($ct !== null) {
            if ($ct >= 16) { $freq_data['Class Tests'][0]['count']++; }
            elseif ($ct >= 10) { $freq_data['Class Tests'][1]['count']++; }
            else { $freq_data['Class Tests'][2]['count']++; }
        }
    }
}

// Remove empty grades from pie chart for clean display
$pie_labels = [];
$pie_data = [];
foreach ($grade_counts as $grade => $count) {
    if ($count > 0) {
        $pie_labels[] = $grade;
        $pie_data[] = $count;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Analytics Report - MarksMetrics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #0f0f15;
            --panel-bg: #1a1a24;
            --text-primary: #ffffff;
            --text-secondary: #8f8f9d;
            --primary-orange: #ff6b00;
            --border-color: #2a2a35;
            --font-family: 'Inter', sans-serif;
            --uiu-blue: #003366;
            --uiu-orange: #f26522;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: var(--font-family);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }

        /* Image Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
            width: 40vw;
            max-width: 400px;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header Differentiated */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 40px;
            background: linear-gradient(to right, rgba(0, 51, 102, 0.4), rgba(242, 101, 34, 0.1));
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--uiu-orange);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .course-info h4 {
            color: var(--uiu-orange);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .course-info h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .course-info p {
            color: var(--text-secondary);
            font-size: 15px;
        }

        .instructor-info {
            text-align: right;
        }
        
        .instructor-info h3 {
            font-size: 18px;
            font-weight: 500;
        }

        .instructor-info p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Charts Section */
        .charts-row {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }

        .chart-container {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            flex: 1;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .chart-container h3 {
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 500;
        }

        .canvas-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Frequency Table */
        .table-section {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 500;
        }

        select {
            background-color: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 10px 16px;
            border-radius: 6px;
            font-family: var(--font-family);
            font-size: 14px;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'><path d='M1 1.5L5 4.5L9 1.5' stroke='%238f8f9d' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        select:focus {
            border-color: var(--primary-orange);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }

        td {
            font-size: 15px;
        }

        .student-count-bar {
            height: 6px;
            background-color: rgba(255, 107, 0, 0.2);
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
            width: 100%;
        }

        .student-count-fill {
            height: 100%;
            background-color: var(--primary-orange);
            border-radius: 3px;
            transition: width 0.5s ease-out;
        }
    </style>
</head>
<body>

    <!-- MarksMetrics Logo Watermark -->
    <img src="../asset/logo.png" alt="Watermark" class="watermark">

    <div class="container">
        
        <a href="marks-entry.php?course=<?php echo urlencode($course_code); ?>" style="display: inline-flex; align-items: center; color: var(--text-secondary); text-decoration: none; font-size: 14px; font-weight: 500; margin-bottom: 20px; transition: color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-secondary)'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Marks Entry
        </a>

        <header class="header">
            <div class="course-info">
                <h4>United International University</h4>
                <h1><?php echo htmlspecialchars($course_name); ?></h1>
                <p>Course: <?php echo htmlspecialchars($course_code); ?> | Term: <?php echo htmlspecialchars($trimester); ?> | Total Students: <?php echo $total_students; ?></p>
            </div>
            <div style="display: flex; gap: 30px; align-items: center;">
                <div class="instructor-info">
                    <h3><?php echo htmlspecialchars($teacher_name); ?></h3>
                    <p><?php echo htmlspecialchars($teacher_email); ?></p>
                </div>
            </div>
        </header>

        <div class="charts-row">
            <div class="chart-container" style="flex: 0.35;">
                <h3>Grade Distribution</h3>
                <div class="canvas-wrapper">
                    <canvas id="gradePieChart"></canvas>
                </div>
            </div>
            <div class="chart-container" style="flex: 0.65;">
                <h3>Mark Distribution</h3>
                <div class="canvas-wrapper">
                    <canvas id="markBarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-container" style="flex: 1;">
                <h3>Trend: CGPA vs Total Marks Obtained</h3>
                <div class="canvas-wrapper">
                    <canvas id="scatterChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-section">
            <div class="table-header">
                <h3>Frequency Table</h3>
                <select id="assessmentSelect">
                    <option value="Total Marks">Total Marks</option>
                    <option value="Midterm">Midterm</option>
                    <option value="Final">Final</option>
                    <option value="Class Tests">Class Tests</option>
                </select>
            </div>
            
            <table id="frequencyTable">
                <thead>
                    <tr>
                        <th>Mark Range</th>
                        <th>Student Count</th>
                        <th style="width: 40%;">Distribution</th>
                    </tr>
                </thead>
                <tbody id="frequencyBody">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const chartColors = {
            orange: '#ff6b00',
            orangeLight: 'rgba(255, 107, 0, 0.6)',
            blue: '#3b82f6',
            green: '#10b981',
            red: '#ef4444',
            purple: '#8b5cf6',
            pink: '#ec4899',
            yellow: '#f59e0b',
            cyan: '#06b6d4',
            darkBg: '#1a1a24',
            border: '#2a2a35',
            text: '#8f8f9d'
        };

        // Distinct Colors for Pie Chart
        const distinctColors = [
            chartColors.blue, chartColors.green, chartColors.orange, 
            chartColors.purple, chartColors.red, chartColors.pink, 
            chartColors.yellow, chartColors.cyan, '#64748b', '#000000'
        ];

        // Data from PHP
        const pieLabels = <?php echo json_encode($pie_labels); ?>;
        const pieDataRaw = <?php echo json_encode($pie_data); ?>;
        
        const markDistLabels = ['0-59', '60-69', '70-79', '80-89', '90-100'];
        const markDistDataRaw = [
            <?php echo $mark_dist['0-59']; ?>,
            <?php echo $mark_dist['60-69']; ?>,
            <?php echo $mark_dist['70-79']; ?>,
            <?php echo $mark_dist['80-89']; ?>,
            <?php echo $mark_dist['90-100']; ?>
        ];

        const scatterDataRaw = <?php echo json_encode($scatter_data); ?>;
        const frequencyData = <?php echo json_encode($freq_data); ?>;

        // Initialize Charts
        Chart.defaults.color = chartColors.text;
        Chart.defaults.font.family = "'Inter', sans-serif";

        // Pie Chart
        const pieCtx = document.getElementById('gradePieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieDataRaw,
                    backgroundColor: distinctColors.slice(0, pieLabels.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { padding: 20, usePointStyle: true }
                    }
                },
                cutout: '65%'
            }
        });

        // Bar Chart
        const barCtx = document.getElementById('markBarChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: markDistLabels,
                datasets: [{
                    label: 'Students',
                    data: markDistDataRaw,
                    backgroundColor: chartColors.orangeLight,
                    borderColor: chartColors.orange,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.border, drawBorder: false },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Scatter Plot
        const scatterCtx = document.getElementById('scatterChart').getContext('2d');
        new Chart(scatterCtx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Student',
                    data: scatterDataRaw,
                    backgroundColor: chartColors.blue,
                    borderColor: 'rgba(59, 130, 246, 0.8)',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: { display: true, text: 'Course Total Marks', color: '#fff' },
                        grid: { color: chartColors.border },
                        min: 0, max: 100
                    },
                    y: {
                        title: { display: true, text: 'Cumulative GPA', color: '#fff' },
                        grid: { color: chartColors.border },
                        min: 0.0, max: 4.0
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return `Marks: ${ctx.parsed.x}, CGPA: ${ctx.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });

        // Frequency Table Logic
        const assessmentSelect = document.getElementById('assessmentSelect');
        const frequencyBody = document.getElementById('frequencyBody');

        function renderTable(type) {
            const data = frequencyData[type];
            let maxCount = Math.max(...data.map(d => d.count));
            if (maxCount === 0) maxCount = 1;

            frequencyBody.innerHTML = '';
            
            data.forEach(item => {
                const percentage = (item.count / maxCount) * 100;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight: 500;">${item.range}</td>
                    <td>${item.count}</td>
                    <td>
                        <div class="student-count-bar">
                            <div class="student-count-fill" style="width: 0%"></div>
                        </div>
                    </td>
                `;
                frequencyBody.appendChild(tr);

                // Animate bar fill
                setTimeout(() => {
                    tr.querySelector('.student-count-fill').style.width = percentage + '%';
                }, 50);
            });
        }

        assessmentSelect.addEventListener('change', (e) => {
            renderTable(e.target.value);
        });

        // Initial render
        renderTable('Total Marks');

    </script>
</body>
</html>
