<?php
// Database/update_dashboard_courses.php
header('Content-Type: text/plain');

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'MarkMetrics';

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

// 1. Add section column to courses if it doesn't exist
$check_section = mysqli_query($conn, "SHOW COLUMNS FROM courses LIKE 'section'");
if (mysqli_num_rows($check_section) == 0) {
    $alter = mysqli_query($conn, "ALTER TABLE courses ADD COLUMN section VARCHAR(10) DEFAULT 'A'");
    if ($alter) {
        echo "Added section column to courses.\n";
    } else {
        echo "Error adding section column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "section column already exists.\n";
}

// 2. Assign 3 more courses to TCH-101
mysqli_query($conn, "UPDATE courses SET teacher_id = 'TCH-101' WHERE course_code IN ('CSE-2216', 'CSE-4401', 'MAT-1199')");
echo "Assigned additional courses (CSE-2216, CSE-4401, MAT-1199) to TCH-101.\n";

// 3. Set sections for TCH-101 courses
mysqli_query($conn, "UPDATE courses SET section = 'A' WHERE course_code = 'CSE-4165'");
mysqli_query($conn, "UPDATE courses SET section = 'B' WHERE course_code = 'CSE-3301'");
mysqli_query($conn, "UPDATE courses SET section = 'A' WHERE course_code = 'CSE-4411'");
mysqli_query($conn, "UPDATE courses SET section = 'C' WHERE course_code = 'CSE-4450'");
mysqli_query($conn, "UPDATE courses SET section = 'A' WHERE course_code = 'CSE-2216'");
mysqli_query($conn, "UPDATE courses SET section = 'B' WHERE course_code = 'CSE-4401'");
mysqli_query($conn, "UPDATE courses SET section = 'C' WHERE course_code = 'MAT-1199'");
echo "Set section codes for TCH-101 courses.\n";

// 4. Fetch all student IDs from DB
$students_q = mysqli_query($conn, "SELECT student_id FROM students");
$student_ids = [];
while ($row = mysqli_fetch_assoc($students_q)) {
    $student_ids[] = $row['student_id'];
}
$total_available_students = count($student_ids);
echo "Total available students in database: $total_available_students\n";

if ($total_available_students < 50) {
    echo "Warning: Only $total_available_students students found in DB. We recommend running seed_50_students.php.\n";
}

// 5. Re-seed enrollments for TCH-101 courses with dynamic counts between 28 and 44
$courses = ['CSE-4165', 'CSE-3301', 'CSE-4411', 'CSE-4450', 'CSE-2216', 'CSE-4401', 'MAT-1199'];
$seeded_count = 0;

foreach ($courses as $course_code) {
    // Delete existing enrollments for this course
    mysqli_query($conn, "DELETE FROM enrollments WHERE course_code = '$course_code'");
    
    // Determine target enrollment count between 28 and 44
    $target_count = rand(28, 44);
    
    // Pick random subset of student IDs to keep course lists distinct
    $shuffled_students = $student_ids;
    shuffle($shuffled_students);
    $selected_students = array_slice($shuffled_students, 0, min($target_count, $total_available_students));
    
    // Get semester_id of course
    $c_q = mysqli_query($conn, "SELECT semester_id FROM courses WHERE course_code = '$course_code'");
    $c_data = mysqli_fetch_assoc($c_q);
    $semester_id = $c_data ? $c_data['semester_id'] : 1;
    
    echo "Enrolling " . count($selected_students) . " random students in $course_code...\n";
    
    foreach ($selected_students as $student_id) {
        $is_pending = (rand(1, 10) <= 2); // 20% chance of pending/incomplete marks
        
        if ($is_pending) {
            $midterm = "NULL";
            $final = "NULL";
            $ct = "NULL";
            $assignment = "NULL";
            $grade = "'INC'";
            $points = "0.00";
            $status = "'Ongoing'";
        } else {
            $mid_score = rand(15, 30);
            $fin_score = rand(20, 40);
            $ct_score = rand(10, 20);
            $assignment_score = rand(5, 10);
            $total = $mid_score + $fin_score + $ct_score + $assignment_score;
            
            // Dynamic database scale mapping
            $grade_look = mysqli_query($conn, "SELECT grade, points FROM grading_scale WHERE $total BETWEEN min_score AND max_score LIMIT 1");
            if ($grade_look && $gl_row = mysqli_fetch_assoc($grade_look)) {
                $grade = "'" . mysqli_real_escape_string($conn, $gl_row['grade']) . "'";
                $points = "'" . mysqli_real_escape_string($conn, $gl_row['points']) . "'";
            } else {
                $grade = "'F'";
                $points = "0.00";
            }
            
            $midterm = $mid_score;
            $final = $fin_score;
            $ct = $ct_score;
            $assignment = $assignment_score;
            $status = "'Completed'";
        }
        
        $insert_enroll = "INSERT INTO enrollments (student_id, course_code, semester_id, midterm_score, final_score, ct_score, assignment_score, grade, points, status) VALUES 
                          ('$student_id', '$course_code', $semester_id, $midterm, $final, $ct, $assignment, $grade, $points, $status)";
        if (mysqli_query($conn, $insert_enroll)) {
            $seeded_count++;
        }
    }
}

echo "Finished seeding. Total enrollments inserted: $seeded_count\n";
mysqli_close($conn);
?>
