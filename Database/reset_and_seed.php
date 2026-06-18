<?php
// Database/reset_and_seed.php
header('Content-Type: text/plain');

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'markmetrics';

echo "=== Starting database reset and seeding ===\n";

// 1. Drop and recreate database
$conn = mysqli_connect($db_host, $db_user, $db_password);
if (!$conn) {
    die("MySQL Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Dropping database '$db_name' if exists...\n";
mysqli_query($conn, "DROP DATABASE IF EXISTS `$db_name`") or die("Error dropping database: " . mysqli_error($conn) . "\n");

echo "Creating database '$db_name'...\n";
mysqli_query($conn, "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") or die("Error creating database: " . mysqli_error($conn) . "\n");

mysqli_close($conn);

// Change working directory to Database folder so relative includes resolve correctly
chdir(__DIR__);

// Helper function to run a PHP file and display output
function runScript($filename) {
    echo "\n----------------------------------------\n";
    echo "Running script: $filename\n";
    echo "----------------------------------------\n";
    $cmd = "php " . escapeshellarg($filename);
    $output = [];
    $retval = 0;
    exec($cmd, $output, $retval);
    echo implode("\n", $output) . "\n";
    if ($retval !== 0) {
        echo "Warning: Script $filename returned non-zero code $retval\n";
    }
}

// 2. Run seed_50_students.php first (which imports database.sql because users table doesn't exist yet)
runScript('seed_50_students.php');

// 3. Run all migrations and updates in order
runScript('migrate_teacher_profile.php');
runScript('populate_teacher_initials.php');
runScript('migrate_ct_assignment.php');
runScript('migrate_grade_requests.php');
runScript('migrate_guardian_features.php');
runScript('update_dashboard_courses.php');
runScript('update_all_courses_to_current.php');
runScript('update_grading_scale.php');

// 4. Seed withdrawal requests for teacher portal check
echo "\n----------------------------------------\n";
echo "Seeding additional dummy Withdrawal Requests for TCH-101...\n";
echo "----------------------------------------\n";

$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);
if (!$conn) {
    die("MySQL Connection failed during post-seeding: " . mysqli_connect_error() . "\n");
}

// Clear existing withdraw requests to start fresh
mysqli_query($conn, "TRUNCATE TABLE withdraw_requests");

// Fetch active student enrollments for TCH-101 courses
$enrollments_q = mysqli_query($conn, "
    SELECT e.student_id, e.course_code, e.semester_id, e.midterm_score
    FROM enrollments e
    JOIN courses c ON e.course_code = c.course_code
    WHERE c.teacher_id = 'TCH-101'
    LIMIT 6
");

if ($enrollments_q && mysqli_num_rows($enrollments_q) > 0) {
    $inserted = 0;
    $reasons = [
        "Medical emergency, missed too many classes.",
        "Overloaded semester course load.",
        "Financial difficulties preventing tuition completion.",
        "Personal/family reasons requiring semester drop.",
        "Conflict with job timing."
    ];
    $statuses = ['Pending', 'Pending', 'Approved', 'Rejected', 'Pending', 'Approved'];

    while ($row = mysqli_fetch_assoc($enrollments_q)) {
        $student_id = $row['student_id'];
        $course_code = $row['course_code'];
        $semester_id = $row['semester_id'];
        $reason = mysqli_real_escape_string($conn, $reasons[array_rand($reasons)]);
        $status = $statuses[$inserted % count($statuses)];
        
        $ins_q = "
            INSERT INTO withdraw_requests (student_id, course_code, semester_id, reason, status, is_read)
            VALUES ('$student_id', '$course_code', $semester_id, '$reason', '$status', 0)
        ";
        if (mysqli_query($conn, $ins_q)) {
            $inserted++;
            if ($status === 'Approved') {
                // Set enrollment to dropped/withdrawn
                mysqli_query($conn, "UPDATE enrollments SET status = 'Dropped', grade = 'W', points = 0.00 WHERE student_id = '$student_id' AND course_code = '$course_code' AND semester_id = $semester_id");
            }
        } else {
            echo "Error inserting withdraw request: " . mysqli_error($conn) . "\n";
        }
    }
    echo "Successfully seeded $inserted withdraw requests.\n";
} else {
    echo "No enrollments found for TCH-101 to seed withdraw requests.\n";
}

mysqli_close($conn);

echo "\n=== Seeding & Setup completed successfully! ===\n";
?>
