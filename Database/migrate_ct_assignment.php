<?php
// Database/migrate_ct_assignment.php
header('Content-Type: text/plain');

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'MarkMetrics';

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Checking columns in enrollments table...\n";

$result = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'ct_score';");
if (mysqli_num_rows($result) == 0) {
    echo "Adding ct_score column...\n";
    if (mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN ct_score DECIMAL(5,2) DEFAULT NULL AFTER final_score;")) {
        echo "ct_score column added.\n";
    } else {
        echo "Error adding ct_score: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "ct_score column already exists.\n";
}

$result2 = mysqli_query($conn, "SHOW COLUMNS FROM enrollments LIKE 'assignment_score';");
if (mysqli_num_rows($result2) == 0) {
    echo "Adding assignment_score column...\n";
    if (mysqli_query($conn, "ALTER TABLE enrollments ADD COLUMN assignment_score DECIMAL(5,2) DEFAULT NULL AFTER ct_score;")) {
        echo "assignment_score column added.\n";
    } else {
        echo "Error adding assignment_score: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "assignment_score column already exists.\n";
}

// Re-generate total_score column to include ct_score and assignment_score
echo "Re-generating total_score column to include CT and Assignment...\n";
// Drop total_score if exists
mysqli_query($conn, "ALTER TABLE enrollments DROP COLUMN total_score;");

$rebuild_total_sql = "ALTER TABLE enrollments ADD COLUMN total_score DECIMAL(5,2) 
                      GENERATED ALWAYS AS (COALESCE(midterm_score, 0) + COALESCE(final_score, 0) + COALESCE(ct_score, 0) + COALESCE(assignment_score, 0)) STORED 
                      AFTER assignment_score;";

if (mysqli_query($conn, $rebuild_total_sql)) {
    echo "total_score column re-generated successfully.\n";
} else {
    echo "Error re-generating total_score: " . mysqli_error($conn) . "\n";
}

// Let's populate some random CT and assignment scores for existing seeded students so it's not all empty/zero
echo "Seeding initial CT (0-20) and Assignment (0-10) marks for active student enrollments...\n";
$update_marks_q = mysqli_query($conn, "SELECT student_id, course_code, semester_id, midterm_score, final_score FROM enrollments;");
$updated_count = 0;
if ($update_marks_q) {
    while ($row = mysqli_fetch_assoc($update_marks_q)) {
        if ($row['midterm_score'] !== null || $row['final_score'] !== null) {
            $ct = rand(10, 20);
            $assignment = rand(5, 10);
            $student_id = $row['student_id'];
            $course_code = $row['course_code'];
            $semester_id = $row['semester_id'];
            
            // Calculate new total and grade
            $midterm = floatval($row['midterm_score']);
            $final = floatval($row['final_score']);
            $total = $midterm + $final + $ct + $assignment;
            
            // Dynamic database scale mapping
            $grade_look = mysqli_query($conn, "SELECT grade, points FROM grading_scale WHERE $total BETWEEN min_score AND max_score LIMIT 1");
            if ($grade_look && $gl_row = mysqli_fetch_assoc($grade_look)) {
                $grade = "'" . mysqli_real_escape_string($conn, $gl_row['grade']) . "'";
                $points = "'" . mysqli_real_escape_string($conn, $gl_row['points']) . "'";
            } else {
                $grade = "'F'";
                $points = "0.00";
            }
            
            $update_sql = "UPDATE enrollments 
                           SET ct_score = $ct, assignment_score = $assignment, grade = $grade, points = $points
                           WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id";
            mysqli_query($conn, $update_sql);
            $updated_count++;
        }
    }
}
echo "Updated $updated_count records with CT and Assignment marks.\n";

mysqli_close($conn);
echo "Migration complete.\n";
?>
