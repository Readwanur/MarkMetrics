<?php
// Database/seed_50_students.php
header('Content-Type: text/plain');

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'MarkMetrics';

// Create connection to MySQL server first to ensure database exists or create it
$conn = mysqli_connect($db_host, $db_user, $db_password);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

// Create database if it doesn't exist
$db_create_sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
if (mysqli_query($conn, $db_create_sql)) {
    echo "Database '$db_name' verified/created.\n";
} else {
    die("Error creating database: " . mysqli_error($conn) . "\n");
}

// Select database
mysqli_select_db($conn, $db_name);

// Check if users table exists. If not, import database.sql
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users';");
if (mysqli_num_rows($table_check) == 0) {
    echo "Database tables do not exist. Importing database.sql...\n";
    $sql_file = __DIR__ . '/database.sql';
    if (file_exists($sql_file)) {
        $queries = file_get_contents($sql_file);
        // Execute multi-query
        if (mysqli_multi_query($conn, $queries)) {
            do {
                // Store first result set
                if ($result = mysqli_store_result($conn)) {
                    mysqli_free_result($result);
                }
            } while (mysqli_next_result($conn));
            echo "database.sql successfully imported.\n";
        } else {
            die("Error importing database.sql: " . mysqli_error($conn) . "\n");
        }
    } else {
        die("Error: database.sql not found at $sql_file\n");
    }
}

// Reconnect to clean state
mysqli_close($conn);
$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);

// Generator data arrays
$first_names = ['Mahir', 'Abrar', 'Sadman', 'Tasin', 'Nafis', 'Sajid', 'Farhan', 'Raihan', 'Zamil', 'Israk', 'Kazi', 'Adib', 'Wasi', 'Anis', 'Zubair', 'Imtiaz', 'Sakib', 'Rafid', 'Rian', 'Zeeshan', 'Mushfiq', 'Tamim', 'Tasnim', 'Nabila', 'Afia', 'Sara', 'Sumaiya', 'Anika', 'Fariha', 'Sadia', 'Sania', 'Jannat', 'Ayesha', 'Mehnaz', 'Maliha', 'Zarin', 'Lamiha', 'Humaira', 'Nuzhat', 'Rida', 'Eshal', 'Ayra', 'Zoya', 'Myra', 'Alina', 'Mariam', 'Zahra', 'Fatima', 'Adiba', 'Haniya'];
$last_names = ['Rahman', 'Islam', 'Ahmed', 'Hasan', 'Khan', 'Chowdhury', 'Uddin', 'Alom', 'Sarker', 'Hossain', 'Ali', 'Jahan', 'Akter', 'Bhuiyan', 'Talukder', 'Patwary', 'Karim', 'Miah', 'Sheikh', 'Zaman'];

$password_plain = '123';
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// We want to generate exactly 50 dummy students
$student_count = 50;
$starting_id_num = 2330800; // Generate IDs like 0112330800, 0112330801, etc.
$department_id = 1; // CS & E department
$program_id = 1; // CSE-BSC program
$academic_year = '2023-2024';
$current_academic_year = '2025-2026';
$enrollment_term = 'Spring 2024';

// Courses to enroll them in:
// Let's check which courses exist and enroll them in CSE-4165 (Advanced Algorithms, semester 1, taught by Mahi Bhai)
// We'll also enroll them in CSE-3301, CSE-4411, and CSE-4450.
$courses_to_enroll = ['CSE-4165', 'CSE-3301', 'CSE-4411', 'CSE-4450'];

echo "Starting generation of $student_count dummy students...\n";

$inserted_users = 0;
$inserted_students = 0;
$inserted_enrollments = 0;

for ($i = 0; $i < $student_count; $i++) {
    $id_num = $starting_id_num + $i;
    $student_id = '011' . $id_num; // Format like 0112330800
    
    // Check if user already exists
    $check_user = mysqli_query($conn, "SELECT id FROM users WHERE id = '$student_id'");
    if (mysqli_num_rows($check_user) > 0) {
        // Already exists, skip inserting user and student, but we might want to check enrollments
        continue;
    }

    $fname = $first_names[array_rand($first_names)];
    $lname = $last_names[array_rand($last_names)];
    $full_name = "$fname $lname";
    
    // Make email unique by appending a counter if needed
    $email = strtolower($fname . "." . $lname . "." . $id_num . "@bscse.uiu.ac.bd");
    
    // Insert User
    $user_sql = "INSERT INTO users (id, name, email, password_hash, role, department_id, status) VALUES 
                 ('$student_id', '$full_name', '$email', '$password_hash', 'student', $department_id, 'Active')";
    
    if (mysqli_query($conn, $user_sql)) {
        $inserted_users++;
        
        // Insert Student Details
        $dob_year = rand(2003, 2005);
        $dob_month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $dob_day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        $dob = "$dob_year-$dob_month-$dob_day";
        
        $total_credits = rand(30, 110) . ".00";
        $cgpa = number_format(rand(220, 400) / 100, 2);
        
        // Parent assignment: optionally select PAR-901 or PAR-902, or NULL
        $parents = ['PAR-901', 'PAR-902', 'NULL'];
        $parent_pick = $parents[array_rand($parents)];
        $parent_val = $parent_pick === 'NULL' ? "NULL" : "'$parent_pick'";
        
        $student_sql = "INSERT INTO students (student_id, program_id, date_of_birth, academic_year, current_academic_year, enrollment_term, parent_id, total_credits_earned, cumulative_gpa) VALUES 
                        ('$student_id', $program_id, '$dob', '$academic_year', '$current_academic_year', '$enrollment_term', $parent_val, $total_credits, $cgpa)";
        
        if (mysqli_query($conn, $student_sql)) {
            $inserted_students++;
            
            // Now, enroll the student in the target courses
            foreach ($courses_to_enroll as $course_code) {
                // Determine course semester_id from course
                $course_q = mysqli_query($conn, "SELECT semester_id FROM courses WHERE course_code = '$course_code'");
                $course_data = mysqli_fetch_assoc($course_q);
                $semester_id = $course_data ? $course_data['semester_id'] : 1;
                
                // Let's generate scores for this enrollment
                // Let some be incomplete (NULL) to test "Pending Marks" feature
                $is_pending = (rand(1, 10) <= 2); // 20% chance of pending marks
                
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
                
                // Insert Enrollment
                $enroll_sql = "INSERT INTO enrollments (student_id, course_code, semester_id, midterm_score, final_score, ct_score, assignment_score, grade, points, status) VALUES 
                               ('$student_id', '$course_code', $semester_id, $midterm, $final, $ct, $assignment, $grade, $points, $status)";
                
                if (mysqli_query($conn, $enroll_sql)) {
                    $inserted_enrollments++;
                } else {
                    echo "Error inserting enrollment for $student_id in $course_code: " . mysqli_error($conn) . "\n";
                }
            }
        } else {
            echo "Error inserting student details for $student_id: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Error inserting user $student_id: " . mysqli_error($conn) . "\n";
    }
}

echo "\nSeeding finished successfully!\n";
echo "Inserted Users: $inserted_users\n";
echo "Inserted Students: $inserted_students\n";
echo "Inserted Enrollments: $inserted_enrollments\n";

mysqli_close($conn);
?>
