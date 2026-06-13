<?php
include(__DIR__ . '/../LoginPage/connect2db.php');

echo "Starting database migration for grade correction requests...\n";

// 1. Drop the old foreign key constraint if it exists
$drop_fk_queries = [
    "ALTER TABLE grade_correction_requests DROP FOREIGN KEY grade_correction_requests_ibfk_3",
    "ALTER TABLE grade_correction_requests DROP FOREIGN KEY FK_requested_by_teacher"
];

foreach ($drop_fk_queries as $q) {
    try {
        if (mysqli_query($conn, $q)) {
            echo "Successfully dropped old foreign key using query: $q\n";
            break;
        }
    } catch (Exception $e) {
        // Continue to next query if this one fails
    }
}

// 2. Add new foreign key referencing users(id)
$alter_fk = "ALTER TABLE grade_correction_requests ADD CONSTRAINT fk_requested_by_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE";
try {
    if (mysqli_query($conn, $alter_fk)) {
        echo "Successfully added new foreign key constraint referencing users(id).\n";
    }
} catch (Exception $e) {
    echo "Notice: " . $e->getMessage() . " (Constraint might already exist)\n";
}

// 3. Clear existing requests to start fresh with dummy student requests
mysqli_query($conn, "TRUNCATE TABLE grade_correction_requests");
echo "Cleared existing requests.\n";

// 4. Insert dummy requests from students for courses
// We need to fetch active student enrollments to create realistic dummy requests
$enrollments_q = mysqli_query($conn, "
    SELECT e.student_id, e.course_code, e.grade, c.teacher_id
    FROM enrollments e
    JOIN courses c ON e.course_code = c.course_code
    WHERE e.status = 'Ongoing' OR e.status = 'Completed'
    LIMIT 10
");

if ($enrollments_q && mysqli_num_rows($enrollments_q) > 0) {
    $inserted = 0;
    $grades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+'];
    $justifications = [
        "I believe my final exam script was mismarked in Question 3. Can you please review?",
        "My class test average was calculated incorrectly. It should be 18 instead of 15.",
        "I submitted the final project on time but it seems it wasn't recorded in the system.",
        "Requesting a review of my assignment 2 grade as there was a mixup in submissions.",
        "Could you please check my attendance marks? I had 95% attendance."
    ];

    while ($row = mysqli_fetch_assoc($enrollments_q)) {
        $student_id = $row['student_id'];
        $course_code = $row['course_code'];
        $current_grade = $row['grade'] ?? 'B-';
        $teacher_id = $row['teacher_id'];

        if (empty($teacher_id)) continue;

        // Choose a grade higher than current grade
        $desired_grade = 'A';
        if ($current_grade === 'A' || $current_grade === 'A+') {
            $desired_grade = 'A+';
        }

        $justification = mysqli_real_escape_string($conn, $justifications[array_rand($justifications)]);
        $status = ($inserted % 3 === 0) ? 'Pending' : (($inserted % 3 === 1) ? 'Approved' : 'Rejected');
        
        $ins_q = "
            INSERT INTO grade_correction_requests (student_id, course_code, current_grade, new_grade, justification, requested_by, status)
            VALUES ('$student_id', '$course_code', '$current_grade', '$desired_grade', '$justification', '$student_id', '$status')
        ";
        if (mysqli_query($conn, $ins_q)) {
            $inserted++;
        }
    }
    echo "Inserted $inserted dummy student requests successfully.\n";
} else {
    echo "No enrollments found to generate dummy requests.\n";
}
?>
